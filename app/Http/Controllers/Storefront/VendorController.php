<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $vendors = Vendor::query()
            ->published()
            ->orderBy('title')
            ->paginate(config('store.per_page.admin'), pageName: 'page')
            ->withQueryString();

        return view('storefront.vendors.index', [
            'meta' => ['title' => 'تامین‌کنندگان'],
            'vendors' => $vendors,
        ]);
    }

    public function show(Request $request, string $vendor): View
    {
        $model = Vendor::query()
            ->published()
            ->where('slug', $vendor)
            ->firstOrFail();

        // The legacy site paginated a vendor's products with `?page=N` on the
        // vendor page itself; the query string is preserved as-is.
        $products = $model->products()
            ->published()
            ->ordered()
            ->paginate(config('store.per_page.vendors'), pageName: 'page')
            ->withQueryString();

        return view('storefront.vendors.show', [
            'meta' => [
                'title' => $model->title,
                'description' => $model->description,
                'canonical' => $model->url(),
            ],
            'vendor' => $model,
            'products' => $products,
        ]);
    }
}
