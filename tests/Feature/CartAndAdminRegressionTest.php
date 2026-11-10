<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ResolveAccount;
use App\Models\Order;
use App\Models\Page;
use App\Models\PaymentIntent;
use App\Models\Product;
use App\Models\User;
use App\Store;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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

    public function test_web_middleware_starts_session_before_resolving_account_and_binding_models(): void
    {
        $router = $this->app['router'];

        foreach (['admin.login', 'admin.dashboard', 'cart.add'] as $name) {
            $route = $router->getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $middleware = $router->gatherRouteMiddleware($route);

            foreach ([StartSession::class, ResolveAccount::class, SubstituteBindings::class] as $class) {
                $this->assertSame(1, count(array_keys($middleware, $class, true)), $name.' must contain '.$class.' exactly once');
            }

            $this->assertLessThan(
                array_search(ResolveAccount::class, $middleware, true),
                array_search(StartSession::class, $middleware, true),
                $name.' must start the session before resolving the account',
            );
            $this->assertLessThan(
                array_search(SubstituteBindings::class, $middleware, true),
                array_search(ResolveAccount::class, $middleware, true),
                $name.' must resolve the account before binding models',
            );
        }
    }

    public function test_admin_login_starts_the_session_and_accepts_credentials(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));

        User::query()->create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password',
        ]);

        $this->get('/admin/login')->assertOk();
        $this->post('/admin/login', [
            'email' => 'admin@example.test', 'password' => 'secret-password',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('landlord');

        $this->get('/admin')->assertOk();
        $this->get('/admin/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_dashboard_loads_after_login_with_memberships_and_account_switch(): void
    {
        $tenantDb = tempnam(sys_get_temp_dir(), 'store-tenant-');

        try {
            config([
                'database.connections.tenant' => [
                    'driver' => 'sqlite', 'database' => $tenantDb, 'prefix' => '',
                    'foreign_key_constraints' => true,
                ],
            ]);
            DB::purge('tenant');
            $previousDefault = config('database.default');
            config(['database.default' => 'tenant']);
            (require database_path('migrations/tenant/0001_01_01_000000_create_tenant_schema.php'))->up();
            config(['database.default' => $previousDefault]);

            DB::connection('landlord')->table('accounts')->insert([
                [
                    'id' => '01k000000000000000000000aa',
                    'parent_id' => null,
                    'path' => '/01k000000000000000000000aa/',
                    'slug' => 'platform',
                    'name' => 'store',
                    'status' => 'active',
                    'plan' => 'platform',
                    'database_name' => null,
                ],
                [
                    'id' => '01k000000000000000000000bb',
                    'parent_id' => '01k000000000000000000000aa',
                    'path' => '/01k000000000000000000000aa/01k000000000000000000000bb/',
                    'slug' => 'ecoauto',
                    'name' => 'اکواتو',
                    'status' => 'active',
                    'plan' => 'standard',
                    'database_name' => $tenantDb,
                ],
            ]);

            $operator = User::query()->create([
                'name' => 'مدیر سیستم',
                'email' => 'operator@example.test',
                'password' => 'secret-password',
                'is_platform_admin' => true,
                'default_account_id' => '01k000000000000000000000bb',
            ]);
            $operator->memberships()->create([
                'account_id' => '01k000000000000000000000bb',
                'role' => 'owner',
            ]);
            $operator->memberships()->create([
                'account_id' => '01k000000000000000000000aa',
                'role' => 'owner',
            ]);

            $this->post('/admin/login', [
                'email' => 'operator@example.test',
                'password' => 'secret-password',
            ])->assertRedirect(route('admin.dashboard'));

            $this->get('/admin')
                ->assertOk()
                ->assertSeeText('اکواتو');

            $this->post('/admin/switch-account', [
                'account' => '01k000000000000000000000aa',
            ])->assertRedirect(route('admin.dashboard'));

            $this->get('/admin')
                ->assertOk()
                ->assertSeeText('این حساب فروشگاه ندارد');
        } finally {
            @unlink($tenantDb);
        }
    }

    public function test_catch_all_page_route_does_not_shadow_explicit_routes(): void
    {
        Page::query()->create([
            'slug' => 'shipping-policy',
            'title' => 'Shipping Policy',
            'body' => '<p>Fast delivery</p>',
            'is_published' => true,
        ]);

        $this->get('/cart')->assertOk();
        $this->get('/shipping-policy')->assertOk()->assertSeeText('Shipping Policy');
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
