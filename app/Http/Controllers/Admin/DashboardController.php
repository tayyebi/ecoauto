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
        $account = Store::current();

        if ($account !== null) {
            abort_unless($this->user()->canAccess($account), 403, 'دسترسی به این حساب ندارید.');
        }

        $counts = [];

        if ($account !== null && $account->hasDatabase()) {
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
            'children' => $account !== null ? Store::resolver()->registry()->children($account) : [],
            'domains' => $account !== null ? Store::resolver()->registry()->domainsFor($account) : [],
            'recentOrders' => $account !== null && $account->hasDatabase()
                ? Order::query()->latest()->limit(5)->get()
                : collect(),
        ]));
    }
}
