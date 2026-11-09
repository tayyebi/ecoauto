<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vendor extends Model
{
    protected $fillable = [
        'slug', 'title', 'description', 'body', 'email', 'phone', 'website', 'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function websiteUrl(): ?string
    {
        if ($this->website === null || $this->website === '') {
            return null;
        }

        return str_starts_with($this->website, 'http') ? $this->website : 'https://'.$this->website;
    }

    public function url(): string
    {
        return route('vendors.show', ['vendor' => $this->slug], absolute: false);
    }
}
