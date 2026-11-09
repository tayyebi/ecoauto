<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class OrderController extends BaseAdminController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        $account = $this->account();

        $query = Order::query()->with('customer')->latest();

        if (is_string($status = $request->query('status')) && $status !== '') {
            $query->where('status', $status);
        }

        return view('admin.orders.index', $this->shareContext($request, [
            'meta' => ['title' => 'سفارش‌ها'],
            'account' => $account,
            'status' => $status ?? null,
            'orders' => $query->paginate(config('store.per_page.admin'))->withQueryString(),
        ]));
    }

    public function show(Request $request, Order $order): View
    {
        $this->account();

        return view('admin.orders.show', $this->shareContext($request, [
            'meta' => ['title' => 'سفارش '.$order->number],
            'order' => $order->load(['lines', 'customer', 'paymentIntents']),
        ]));
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $account = $this->managedAccount();

        $data = $request->validate([
            'status' => ['required', Rule::in([
                Order::STATUS_PENDING, Order::STATUS_PAID, Order::STATUS_FAILED,
                Order::STATUS_CANCELLED, Order::STATUS_FULFILLED,
            ])],
            'payment_status' => ['required', Rule::in(['unpaid', 'paid', 'failed', 'refunded'])],
        ]);

        $order->update($data);

        $this->audit->log('order.updated', $order, $data, $account->id);

        return back()->with('status', 'سفارش به‌روزرسانی شد.');
    }
}
