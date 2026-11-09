<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    protected $fillable = ['order_id', 'driver', 'reference', 'amount', 'status', 'payload'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'payload' => 'array'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
