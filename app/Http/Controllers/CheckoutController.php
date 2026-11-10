<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\Cart\Cart;
use App\Services\Payments\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * DECISION (no-javascript): every action is POST-then-redirect. Quantities are
 * edited in one form and applied together, which is why the cart form exists at
 * all rather than inline per-row buttons.
 *
 * Entirely form-based: every action is a POST that redirects (PRG), so the
 * storefront works with JavaScript disabled and survives a refresh.
 */
final class CheckoutController extends Controller
{
    public function __construct(
        private readonly Cart $cart,
        private readonly GatewayManager $gateways,
        private readonly AuditLogger $audit,
    ) {
    }

    public function cart(): View
    {
        $resolved = $this->cart->resolve();

        return view('storefront.cart.show', [
            'meta' => ['title' => 'سبد خرید'],
            'lines' => $resolved['lines'],
            'subtotal' => $resolved['subtotal'],
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product' => ['required', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::query()->published()->where('slug', $data['product'])->firstOrFail();

        $this->cart->add((int) $product->getKey(), (int) ($data['quantity'] ?? 1));

        return redirect()
            ->route('cart.show')
            ->with('status', 'محصول به سبد خرید افزوده شد.');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:99'],
        ]);

        foreach ($data['quantities'] as $productId => $quantity) {
            $this->cart->setQuantity((int) $productId, (int) $quantity);
        }

        return redirect()->route('cart.show')->with('status', 'سبد خرید به‌روز شد.');
    }

    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate(['product' => ['required', 'integer']]);

        $this->cart->remove((int) $data['product']);

        return redirect()->route('cart.show')->with('status', 'کالا از سبد خرید حذف شد.');
    }

    public function show(): View|RedirectResponse
    {
        $resolved = $this->cart->resolve();

        if ($resolved['lines'] === []) {
            return redirect()->route('cart.show');
        }

        return view('storefront.checkout.show', [
            'meta' => ['title' => 'تسویه حساب'],
            'lines' => $resolved['lines'],
            'subtotal' => $resolved['subtotal'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $resolved = $this->cart->resolve();

        if ($resolved['lines'] === []) {
            return redirect()->route('cart.show');
        }

        $customer = Customer::query()->create($data);

        $order = Order::query()->create([
            'number' => $this->nextOrderNumber(),
            'customer_id' => $customer->id,
            'status' => Order::STATUS_PENDING,
            'subtotal' => $resolved['subtotal'],
            'shipping_total' => 0,
            'total' => $resolved['subtotal'],
            'currency' => config('store.currency', 'IRR'),
            'payment_status' => 'unpaid',
            'shipping_address' => trim(($data['city'] ?? '').' '.$data['address']),
            'notes' => $data['notes'] ?? null,
            'placed_at' => now(),
        ]);

        foreach ($resolved['lines'] as $line) {
            OrderLine::query()->create([
                'order_id' => $order->id,
                'product_id' => $line['product']->getKey(),
                'title' => $line['product']->title,
                'sku' => $line['product']->sku,
                'quantity' => $line['quantity'],
                'unit_price' => (int) $line['product']->amount(),
                'line_total' => $line['total'],
            ]);
        }

        $this->cart->clear();

        $this->audit->log('order.placed', $order, [
            'number' => $order->number,
            'total' => $order->total,
        ]);

        $payment = $this->gateways->driver()->start($order);

        $order->update([
            'payment_gateway' => config('store.payment.driver'),
            'payment_reference' => $payment['reference'],
        ]);

        return redirect()->away($payment['redirect']);
    }

    public function callback(Request $request, string $reference): RedirectResponse
    {
        $result = $this->gateways->driver()->verify($reference, $request->query());

        $order = Order::query()->where('payment_reference', $reference)->first();

        if ($order === null) {
            return redirect()->route('home')->withErrors([
                'payment' => 'سفارش مربوط به این پرداخت یافت نشد.',
            ]);
        }

        if (! $result['paid']) {
            $order->update(['status' => Order::STATUS_FAILED, 'payment_status' => 'failed']);

            $this->audit->log('order.payment_failed', $order, ['reference' => $reference]);

            return redirect()
                ->route('checkout.thanks', ['number' => $order->number])
                ->with('status', $result['message']);
        }

        $order->update(['status' => Order::STATUS_PAID, 'payment_status' => 'paid']);

        $this->audit->log('order.paid', $order, ['reference' => $reference]);

        return redirect()
            ->route('checkout.thanks', ['number' => $order->number])
            ->with('status', $result['message']);
    }

    public function thanks(string $number): View
    {
        $order = Order::query()
            ->with('lines')
            ->where('number', $number)
            ->firstOrFail();

        return view('storefront.checkout.thanks', [
            'meta' => ['title' => 'سفارش ثبت شد'],
            'order' => $order,
        ]);
    }

    private function nextOrderNumber(): string
    {
        do {
            $number = 'ST-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
