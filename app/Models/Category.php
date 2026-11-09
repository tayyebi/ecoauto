<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\Account as TenantAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = [
        'parent_id', 'slug', 'title', 'description', 'body', 'icon', 'position', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'position' => 'integer',
            'parent_id' => 'integer',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('title');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /** Slugs from this category down to the root, used for breadcrumbs. */
    public function ancestors(): array
    {
        $chain = [];
        $node = $this->parent;
        $guard = 0;

        while ($node !== null && $guard++ < 20) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }

    public function url(): string
    {
        return route('categories.show', ['category' => $this->slug], absolute: false);
    }

    /**
     * Bind this instance to an account's database.
     *
     * DECISION (db): `$this->setConnection()` mutates the model and returns
     * the model, so it is not interchangeable with `newQuery()`. Declared
     * return type is `self` and that is what it returns - a builder here would
     * silently break every caller that expects a Category.
     */
    public function forAccount(TenantAccount $account): self
    {
        $this->setConnection($account->connection()->getName());

        return $this;
    }
}
