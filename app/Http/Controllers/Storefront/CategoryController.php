<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use Illuminate\View\View;

final class CategoryController extends Controller
{
    public function index(): View
    {
        return view('storefront.categories.index', [
            'meta' => ['title' => 'دسته‌بندی‌ها'],
            'categories' => Category::query()
                ->published()
                ->roots()
                ->withCount('products')
                ->orderBy('position')
                ->orderBy('title')
                ->get(),
        ]);
    }

    /**
     * A category page lists both products and posts, exactly as the legacy
     * taxonomy template did: the category is a cross-section of both
     * collections rather than belonging to only one.
     */
    public function show(string $category): View
    {
        $model = Category::query()
            ->published()
            ->where('slug', $category)
            ->firstOrFail();

        $categoryIds = [$model->id, ...$model->children()->pluck('id')->all()];

        return view('storefront.categories.show', [
            'meta' => [
                'title' => $model->title,
                'description' => $model->description,
                'canonical' => $model->url(),
            ],
            'category' => $model,
            'ancestors' => $model->ancestors(),
            'children' => $model->children()->published()->withCount('products')->get(),
            'products' => Product::query()
                ->published()
                ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', $categoryIds))
                ->ordered()
                ->paginate(config('store.per_page.products'), pageName: 'page')
                ->withQueryString(),
            'posts' => Post::query()
                ->published()
                ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', $categoryIds))
                ->latestFirst()
                ->limit(20)
                ->get(),
        ]);
    }
}
