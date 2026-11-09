<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Storefront\TenantContent;
use Illuminate\View\View;

final class HomeController extends Controller
{
    public function __construct(private readonly TenantContent $content)
    {
    }

    public function index(): View
    {
        return view('storefront.home', [
            'meta' => [
                'title' => $this->content->siteTitle(),
                'description' => $this->content->setting('description'),
            ],
            'rootCategories' => Category::query()
                ->published()
                ->roots()
                ->withCount('products')
                ->orderBy('position')
                ->orderBy('title')
                ->get(),
            'latestProducts' => Product::query()
                ->published()
                ->ordered()
                ->limit(20)
                ->get(),
            'highlights' => $this->content->setting('home.highlights', []),
        ]);
    }
}
