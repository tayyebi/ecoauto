<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FULFILLED = 'fulfilled';

    protected $fillable = [
        'number', 'customer_id', 'status', 'subtotal', 'shipping_total', 'total',
        'currency', 'payment_status', 'payment_gateway', 'payment_reference',
        'shipping_address', 'notes', 'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'total' => 'integer',
            'placed_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines()
    {
        return $this->hasMany(OrderLine::class);
    }

    public function paymentIntents()
    {
        return $this->hasMany(PaymentIntent::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
