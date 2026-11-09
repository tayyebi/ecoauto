<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\Vendor;
use App\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends BaseAdminController
{
    public function index(Request $request): View
    {
        $account = $this->account();

        $counts = [];

        if ($account->hasDatabase()) {
            $counts = [
                'products' => Product::query()->count(),
                'posts' => Post::query()->count(),
                'categories' => Category::query()->count(),
                'vendors' => Vendor::query()->count(),
                'orders' => Order::query()->count(),
                'revenue' => (int) Order::query()->where('payment_status', 'paid')->sum('total'),
            ];
        }

        return view('admin.dashboard', $this->shareContext($request, [
            'meta' => ['title' => 'داشبورد'],
            'counts' => $counts,
            'children' => Store::resolver()->registry()->children($account),
            'domains' => Store::resolver()->registry()->domainsFor($account),
            'recentOrders' => $account->hasDatabase()
                ? Order::query()->latest()->limit(5)->get()
                : collect(),
        ]));
    }
}
