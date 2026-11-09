<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = ['handle', 'name'];

    public function items()
    {
        return $this->hasMany(MenuItem::class)->orderBy('position');
    }

    public static function forHandle(string $handle): ?self
    {
        return static::with('items')->where('handle', $handle)->first();
    }
}
