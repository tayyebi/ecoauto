<?php

declare(strict_types=1);

use App\Store;

// DECISION (farsi-only): prices are rendered with Persian numerals and the
// Persian thousands separator, so no view has to remember to localise them.
if (! function_exists('format_price')) {
    /**
     * Render a price in the tenant currency with Persian digits.
     *
     * Iranian rial carries no minor unit, so the stored integer is the amount.
     */
    function format_price(int|string|null $amount, ?string $currency = null): string
    {
        if ($amount === null || (int) $amount <= 0) {
            return 'تماس بگیرید';
        }

        $grouped = number_format((int) $amount, 0, '.', '،');

        return to_persian_digits($grouped).' '.($currency ?? config('store.currency', 'IRR'));
    }
}

if (! function_exists('to_persian_digits')) {
    function to_persian_digits(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            (string) $value,
        );
    }
}

if (! function_exists('fa_number')) {
    /** Persian numerals, with thousands separators. */
    function fa_number(int|float|string|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return to_persian_digits(number_format((float) $value, 0, '.', '،'));
    }
}

if (! function_exists('fa_date')) {
    /**
     * Format a date as a Jalali (Solar Hijri) string.
     *
     * Laravel's own `jalali` formatter is not available without intl, so the
     * conversion is done arithmetically here and stays dependency-free.
     */
    function fa_date(?DateTimeInterface $moment, string $format = 'Y/m/d'): string
    {
        if ($moment === null) {
            return '';
        }

        [$jy, $jm, $jd] = gregorian_to_jalali(
            (int) $moment->format('Y'),
            (int) $moment->format('n'),
            (int) $moment->format('j'),
        );

        $parts = [
            'Y' => (string) $jy,
            'm' => str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
            'd' => str_pad((string) $jd, 2, '0', STR_PAD_LEFT),
            'H' => $moment->format('H'),
            'i' => $moment->format('i'),
        ];

        $out = strtr($format, $parts);

        return to_persian_digits($out);
    }
}

if (! function_exists('gregorian_to_jalali')) {
    /**
     * Convert a Gregorian date to the Jalali calendar.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    function gregorian_to_jalali(int $gy, int $gm, int $gd): array
    {
        $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400) + $gd + $gDaysInMonth[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }
}

if (! function_exists('media_url')) {
    /** Resolve a stored image path to a public URL, tolerating legacy data. */
    function media_url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return '/media/'.ltrim($path, '/');
    }
}

if (! function_exists('account')) {
    /** The account serving the current request. */
    function account(): ?App\Tenancy\Account
    {
        return Store::current();
    }
}
