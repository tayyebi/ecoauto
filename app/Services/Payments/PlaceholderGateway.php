<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentIntent;
use Illuminate\Support\Str;

/**
 * DECISION (commerce): the gateway is a seam, not a stub to be deleted. It
 * satisfies the same contract a real Iranian gateway would, so checkout, the
 * callback and the order state machine are exercised end to end today, and
 * swapping in Zarinpal/IDPay is one class plus one config line.
 *
 * It creates an intent and returns the visitor to the storefront, where the
 * callback marks the order paid. This exists so that the whole checkout path
 * is exercised and testable before a real gateway is wired; the intent's
 * payload records that no money moved.
 */
final class PlaceholderGateway implements PaymentGateway
{
    public function start(Order $order): array
    {
        $reference = 'SIM-'.Str::upper(Str::random(12));

        PaymentIntent::query()->create([
            'order_id' => $order->id,
            'driver' => 'placeholder',
            'reference' => $reference,
            'amount' => $order->total,
            'status' => 'pending',
            'payload' => [
                'simulated' => true,
                'currency' => $order->currency,
                'note' => 'پرداخت آزمايشی؛ مبلغی برداشت نمی‌شود.',
            ],
        ]);

        return [
            'reference' => $reference,
            'redirect' => route('checkout.callback', ['reference' => $reference], absolute: false),
        ];
    }

    public function verify(string $reference, array $query = []): array
    {
        $intent = PaymentIntent::query()->where('reference', $reference)->first();

        if ($intent === null) {
            return ['paid' => false, 'reference' => $reference, 'message' => 'تراکنش یافت نشد.'];
        }

        if ($intent->status === 'paid') {
            return ['paid' => true, 'reference' => $reference, 'message' => 'این سفارش پیش‌تر پرداخت شده است.'];
        }

        // A real gateway would call the provider here and abort on failure.
        $intent->update(['status' => 'paid']);

        return ['paid' => true, 'reference' => $reference, 'message' => 'پرداخت آزمايشی با موفقیت ثبت شد.'];
    }
}
