<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class SitemapController extends Controller
{
    /** The human-readable `/sitemap/` page from the legacy site. */
    public function page(): View
    {
        return view('storefront.sitemap', [
            'meta' => ['title' => 'نقشه سایت'],
            'pages' => Page::query()->published()->orderBy('slug')->get(),
            'categories' => Category::query()->published()->roots()->withCount('products')->get(),
            'vendors' => Vendor::query()->published()->orderBy('title')->get(),
            'products' => Product::query()->published()->ordered()->get(),
            'posts' => Post::query()->published()->latestFirst()->get(),
        ]);
    }

    public function xml(): Response
    {
        $urls = collect()
            ->push(['loc' => route('home', absolute: false), 'priority' => '1.0'])
            ->push(['loc' => route('products.index', absolute: false), 'priority' => '0.9'])
            ->push(['loc' => route('categories.index', absolute: false), 'priority' => '0.8'])
            ->push(['loc' => route('vendors.index', absolute: false), 'priority' => '0.7'])
            ->push(['loc' => route('blog.index', absolute: false), 'priority' => '0.8'])
            ->merge(Category::query()->published()->get()->map(fn (Category $c) => [
                'loc' => $c->url(), 'priority' => '0.7',
            ]))
            ->merge(Product::query()->published()->get()->map(fn (Product $p) => [
                'loc' => $p->url(), 'priority' => '0.6',
            ]))
            ->merge(Post::query()->published()->get()->map(fn (Post $p) => [
                'loc' => $p->url(), 'priority' => '0.5',
            ]))
            ->merge(Vendor::query()->published()->get()->map(fn (Vendor $v) => [
                'loc' => $v->url(), 'priority' => '0.4',
            ]));

        return response()
            ->view('storefront.sitemap-xml', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
