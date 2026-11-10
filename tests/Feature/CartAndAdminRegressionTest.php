<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\User;
use App\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CartAndAdminRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'database.default' => 'landlord',
            'database.connections.landlord' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'store.default_account' => null,
        ]);
        DB::purge('landlord');

        foreach ([
            'landlord/0001_01_01_000000_create_accounts_table.php',
            'landlord/0001_01_01_000001_create_users_table.php',
            'landlord/0001_01_01_000003_create_audit_logs_table.php',
            'tenant/0001_01_01_000000_create_tenant_schema.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    public function test_admin_login_starts_the_session_and_accepts_credentials(): void
    {
        User::query()->create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password',
        ]);

        $this->get('/admin/login')->assertOk();
        $this->post('/admin/login', [
            'email' => 'admin@example.test', 'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('landlord');
    }

    public function test_selected_admin_account_is_resolved_before_route_bindings(): void
    {
        DB::table('accounts')->insert([
            'id' => 'selected', 'path' => '/selected/', 'slug' => 'selected', 'name' => 'Selected',
        ]);

        Route::bind('probe', fn () => Store::current()?->id ?? 'none');
        Route::middleware('web')->get('/admin/account-probe/{probe}', fn ($probe) => $probe);

        $this->withSession(['store.account' => 'selected'])
            ->get('/admin/account-probe/anything')
            ->assertOk()->assertSeeText('selected');
    }

    public function test_storefront_uses_hostname_instead_of_selected_admin_account(): void
    {
        DB::table('accounts')->insert([
            ['id' => 'selected', 'path' => '/selected/', 'slug' => 'selected', 'name' => 'Selected'],
            ['id' => 'host', 'path' => '/host/', 'slug' => 'host', 'name' => 'Host'],
        ]);
        DB::table('account_domains')->insert(['account_id' => 'host', 'host' => 'localhost']);
        Route::middleware('web')->get('/account-probe/test', fn () => Store::current()?->id ?? 'none');

        $this->withSession(['store.account' => 'selected'])
            ->get('/account-probe/test')->assertOk()->assertSeeText('host');
    }

    public function test_cart_actions_redirect_and_preserve_session_state(): void
    {
        $product = Product::query()->create(['slug' => 'test-product', 'title' => 'Product', 'is_published' => true]);

        $this->post('/cart/add', ['product' => 'test-product', 'quantity' => 2])
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('store.cart.none.'.$product->id, 2)
            ->assertSessionHas('status');

        $this->withSession(['store.cart' => ['none' => [$product->id => 2]]])
            ->post('/cart/update', ['quantities' => [$product->id => 3]])
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('store.cart.none.'.$product->id, 3);

        $this->withSession(['store.cart' => ['none' => [$product->id => 3]]])
            ->post('/cart/remove', ['product' => $product->id])
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('store.cart.none', []);
    }

    public function test_empty_checkout_redirects_to_cart(): void
    {
        $this->get('/checkout')->assertRedirect(route('cart.show'));
        $this->post('/checkout', [
            'name' => 'Customer', 'phone' => '123456', 'address' => 'Example street',
        ])->assertRedirect(route('cart.show'));
    }

    public function test_payment_callback_redirects_for_missing_failed_and_paid_orders(): void
    {
        $this->get('/checkout/callback/missing')
            ->assertRedirect(route('home'))->assertSessionHasErrors('payment');

        $failed = Order::query()->create([
            'number' => 'FAILED', 'subtotal' => 100, 'total' => 100,
            'payment_reference' => 'failed-ref',
        ]);
        $this->get('/checkout/callback/failed-ref')
            ->assertRedirect(route('checkout.thanks', ['number' => 'FAILED']));
        $this->assertSame('failed', $failed->fresh()->payment_status);

        $paid = Order::query()->create([
            'number' => 'PAID', 'subtotal' => 100, 'total' => 100,
            'payment_reference' => 'paid-ref',
        ]);
        PaymentIntent::query()->create([
            'order_id' => $paid->id, 'driver' => 'placeholder', 'reference' => 'paid-ref',
            'amount' => 100, 'status' => 'pending',
        ]);
        $this->get('/checkout/callback/paid-ref')
            ->assertRedirect(route('checkout.thanks', ['number' => 'PAID']));
        $this->assertSame('paid', $paid->fresh()->payment_status);
    }
}
