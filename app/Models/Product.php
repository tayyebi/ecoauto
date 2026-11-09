<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = [
        'slug', 'title', 'description', 'body', 'sku', 'brand', 'category_label',
        'country_of_origin', 'material', 'material_en', 'gtin',
        'price', 'currency', 'image_path', 'is_published', 'is_featured', 'position',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderByDesc('id');
    }

    /**
     * The legacy catalogue stored two shapes of price: a numeric `price` and a
     * set of `price_amount`/`price_currency` fields, where 0 meant "on request".
     * Both collapse to a nullable integer here, so null means "ask us".
     */
    public function hasPrice(): bool
    {
        return $this->price !== null && $this->price > 0;
    }

    public function priceLabel(): ?string
    {
        return $this->hasPrice() ? format_price($this->price, $this->currency) : null;
    }

    public function imageUrl(): ?string
    {
        return media_url($this->image_path);
    }

    public function url(): string
    {
        return route('products.show', ['product' => $this->slug], absolute: false);
    }

    /** Raw amount the payment placeholder should charge. */
    public function amount(): int
    {
        return (int) ($this->price ?? 0);
    }
}
