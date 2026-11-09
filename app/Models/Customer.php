<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'address', 'city', 'postal_code'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
