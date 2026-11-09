<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->published()
            ->ordered()
            ->with('categories')
            ->paginate(config('store.per_page.products'))
            ->withQueryString();

        return view('storefront.products.index', [
            'meta' => ['title' => 'محصولات'],
            'products' => $products,
        ]);
    }

    public function show(string $product): View
    {
        $model = Product::query()
            ->published()
            ->with(['categories.parent', 'vendors'])
            ->where('slug', $product)
            ->firstOrFail();

        return view('storefront.products.show', [
            'meta' => [
                'title' => $model->title,
                'description' => $model->description,
                'canonical' => $model->url(),
            ],
            'product' => $model,
            'related' => Product::query()
                ->published()
                ->whereKeyNot($model->getKey())
                ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', $model->categories->pluck('id')))
                ->limit(4)
                ->get(),
        ]);
    }
}
