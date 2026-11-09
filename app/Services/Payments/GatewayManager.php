<?php

declare(strict_types=1);

namespace App\Services\Payments;

use InvalidArgumentException;

final class GatewayManager
{
    public function driver(?string $name = null): PaymentGateway
    {
        return match ($name ?? config('store.payment.driver', 'placeholder')) {
            'placeholder' => new PlaceholderGateway,
            default => throw new InvalidArgumentException("Unknown payment driver [{$name}]."),
        };
    }
}
