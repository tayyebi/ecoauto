<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = ['menu_id', 'label', 'url', 'position', 'target'];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
