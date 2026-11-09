<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug', 'title', 'description', 'body', 'layout', 'is_published',
        'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function url(): string
    {
        return '/'.$this->slug.'/';
    }
}
