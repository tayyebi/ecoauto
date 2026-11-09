<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A blog post. Named `Post` rather than `BlogPost`: the collection lives at
 * /blog/, but keeping the model name generic lets tenants reuse it elsewhere.
 */
class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'slug', 'title', 'description', 'body', 'published_at', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function url(): string
    {
        return route('blog.show', ['post' => $this->slug], absolute: false);
    }
}
