<?php

declare(strict_types=1);

// DECISION (farsi-only): dates are Jalali. The conversion is done
// arithmetically rather than through the intl extension, which is not present
// on every host this project runs on.
if (! function_exists('jalali_to_gregorian')) {
    /**
     * Convert a Jalali date to a Gregorian one.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    function jalali_to_gregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd
            + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);

        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;

            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0);
        $monthDays = [31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        $gm = 1;

        foreach ($monthDays as $length) {
            if ($gd <= $length) {
                break;
            }

            $gd -= $length;
            $gm++;
        }

        return [$gy, $gm, $gd];
    }
}

if (! function_exists('jalali_to_datetime')) {
    /**
     * Parse an operator-entered date.
     *
     * Accepts Jalali (`1403/05/12`, with or without Persian digits) and
     * Gregorian ISO dates (`2024-08-02`), because both appear in real data.
     */
    function jalali_to_datetime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '/' => '-',
        ]);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches) !== 1) {
            return null;
        }

        [, $year, $month, $day] = array_map('intval', $matches);

        // Anything below 1700 is a Jalali year; above it is Gregorian.
        if ($year < 1700) {
            [$year, $month, $day] = jalali_to_gregorian($year, $month, $day);
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day));
    }
}
