<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Models\Product;
use App\Store;
use Illuminate\Contracts\Session\Session;

/**
 * DECISION (tenancy): the cart key includes the account id so a visitor cannot
 * carry one storefront's items into another's checkout.
 *
 * DECISION (no-javascript): the cart lives in the session and is driven by plain
 * POST-and-redirect forms; there is no client state to hydrate.
 *
 * The cart key carries the account id so that a visitor browsing two
 * storefronts never sees one tenant's items on the other's checkout.
 */
final class Cart
{
    private const KEY = 'store.cart';

    public function __construct(private readonly Session $session)
    {
    }

    /** @return array<int, int> product id => quantity */
    public function items(): array
    {
        $all = $this->session->get(self::KEY, []);
        $accountId = Store::current()?->id ?? 'none';

        return $all[$accountId] ?? [];
    }

    public function add(int $productId, int $quantity = 1): void
    {
        $items = $this->items();
        $items[$productId] = min(99, ($items[$productId] ?? 0) + max(1, $quantity));

        $this->persist($items);
    }

    public function setQuantity(int $productId, int $quantity): void
    {
        $items = $this->items();

        if ($quantity < 1) {
            unset($items[$productId]);
        } else {
            $items[$productId] = min(99, $quantity);
        }

        $this->persist($items);
    }

    public function remove(int $productId): void
    {
        $items = $this->items();
        unset($items[$productId]);
        $this->persist($items);
    }

    public function clear(): void
    {
        $this->persist([]);
    }

    public function count(): int
    {
        return array_sum($this->items());
    }

    public function isEmpty(): bool
    {
        return $this->items() === [];
    }

    /**
     * Resolve cart rows against the catalogue, dropping anything unpublished
     * or removed since it was added.
     *
     * @return array{lines: list<array{product: Product, quantity: int, total: int}>, subtotal: int}
     */
    public function resolve(): array
    {
        $items = $this->items();

        if ($items === []) {
            return ['lines' => [], 'subtotal' => 0];
        }

        $products = Product::query()
            ->published()
            ->whereIn('id', array_keys($items))
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0;

        foreach ($items as $productId => $quantity) {
            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $total = (int) $product->amount() * (int) $quantity;
            $subtotal += $total;

            $lines[] = ['product' => $product, 'quantity' => (int) $quantity, 'total' => $total];
        }

        return ['lines' => $lines, 'subtotal' => $subtotal];
    }

    private function persist(array $items): void
    {
        $all = $this->session->get(self::KEY, []);
        $accountId = Store::current()?->id ?? 'none';

        $all[$accountId] = $items;

        $this->session->put(self::KEY, $all);
    }
}
