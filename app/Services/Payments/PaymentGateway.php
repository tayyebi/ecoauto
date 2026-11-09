<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;

/**
 * DECISION (commerce): payments are behind an interface so the placeholder can
 * be replaced without touching checkout code.
 *
 * The seam where a real Iranian gateway (Zarinpal, IDPay, Zibal, ...) would go.
 *
 * Implementations create a remote payment, hand back a redirect URL, and
 * verify a callback. `PlaceholderGateway` satisfies the same contract without
 * any network dependency, so checkout can be exercised end to end today.
 */
interface PaymentGateway
{
    /**
     * Begin a payment for the order.
     *
     * @return array{reference: string, redirect: string}
     */
    public function start(Order $order): array;

    /**
     * Confirm a returned payment.
     *
     * @return array{paid: bool, reference: string, message: string}
     */
    public function verify(string $reference, array $query = []): array;
}
