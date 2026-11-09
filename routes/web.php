<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Storefront;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
|
| DECISION (urls): every path below is byte-identical to the legacy Hugo site,
| trailing slashes included, because those URLs are already indexed. New
| routes are added deliberately, never by regenerating links.
|--------------------------------------------------------------------------
|
| The URL structure is identical to the legacy site, trailing slashes and all:
|
|   /                        /products/            /products/{slug}/
|   /categories/             /categories/{slug}/   /vendors/
|   /vendors/{slug}/         /blog/                /blog/{slug}/
|   /blog/page/{n}/          /about/  /contact/  /sitemap/  /sitemap.xml
|
| The account is resolved from the hostname by the ResolveAccount middleware.
|
*/

Route::get('/', [Storefront\HomeController::class, 'index'])->name('home');

Route::get('/products', [Storefront\ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [Storefront\ProductController::class, 'show'])->name('products.show');

Route::get('/categories', [Storefront\CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{category}', [Storefront\CategoryController::class, 'show'])->name('categories.show');

Route::get('/vendors', [Storefront\VendorController::class, 'index'])->name('vendors.index');
Route::get('/vendors/{vendor}', [Storefront\VendorController::class, 'show'])->name('vendors.show');

Route::get('/blog', [Storefront\BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/page/{page}', [Storefront\BlogController::class, 'index'])
    ->whereNumber('page')
    ->name('blog.page');
Route::get('/blog/{post}', [Storefront\BlogController::class, 'show'])->name('blog.show');

Route::get('/sitemap', [Storefront\SitemapController::class, 'page'])->name('sitemap.page');
Route::get('/sitemap.xml', [Storefront\SitemapController::class, 'xml'])->name('sitemap.xml');

// Tenant media lives under ./data and is served per account.
Route::get('/media/{path}', [\App\Http\Controllers\MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

// Fixed pages keep their legacy slugs. Anything else at the root is resolved
// against the tenant's own `pages` table so tenants can publish extra pages.
Route::get('/about', [Storefront\PageController::class, 'about'])->name('pages.about');
Route::get('/contact', [Storefront\PageController::class, 'contact'])->name('pages.contact');
Route::get('/{slug}', [Storefront\PageController::class, 'show'])
    ->where('slug', '[^/]+')
    ->name('pages.show');

/*
|--------------------------------------------------------------------------
| Cart and checkout
|--------------------------------------------------------------------------
|
| Sessions and forms only: no JavaScript anywhere in the storefront.
|
*/

Route::get('/cart', [CheckoutController::class, 'cart'])->name('cart.show');
Route::post('/cart/add', [CheckoutController::class, 'add'])->name('cart.add');
Route::post('/cart/update', [CheckoutController::class, 'update'])->name('cart.update');
Route::post('/cart/remove', [CheckoutController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/callback/{reference}', [CheckoutController::class, 'callback'])->name('checkout.callback');
Route::get('/checkout/thanks/{number}', [CheckoutController::class, 'thanks'])->name('checkout.thanks');

/*
|--------------------------------------------------------------------------
| Control plane
|--------------------------------------------------------------------------
|
| The admin panel lives on the platform host and acts on whichever account the
| session has selected. Tenant hostnames never expose it.
|
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:landlord')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->name('login.store');
    });

    Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');

    Route::middleware('auth:landlord')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Account switching and impersonation are POST-only: a GET that swaps
        // identity is a CSRF hole and a prefetch hazard.
        Route::post('switch-account', [Admin\AccountSwitchController::class, 'store'])->name('accounts.switch');
        Route::post('impersonate/{user}', [Admin\ImpersonationController::class, 'store'])
            ->name('impersonate.store');
        Route::post('impersonation/stop', [Admin\ImpersonationController::class, 'destroy'])
            ->name('impersonate.stop');

        Route::get('accounts', [Admin\AccountController::class, 'index'])->name('accounts.index');
        Route::get('accounts/create', [Admin\AccountController::class, 'create'])->name('accounts.create');
        Route::post('accounts', [Admin\AccountController::class, 'store'])->name('accounts.store');
        Route::get('accounts/{account}', [Admin\AccountController::class, 'show'])->name('accounts.show');
        Route::get('accounts/{account}/edit', [Admin\AccountController::class, 'edit'])->name('accounts.edit');
        Route::put('accounts/{account}', [Admin\AccountController::class, 'update'])->name('accounts.update');
        Route::post('accounts/{account}/suspend', [Admin\AccountController::class, 'suspend'])->name('accounts.suspend');
        Route::post('accounts/{account}/resume', [Admin\AccountController::class, 'resume'])->name('accounts.resume');
        Route::post('accounts/{account}/domains', [Admin\AccountController::class, 'addDomain'])->name('accounts.domains.store');
        Route::post('accounts/{account}/provision', [Admin\AccountController::class, 'provision'])->name('accounts.provision');

        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('members', [Admin\MemberController::class, 'index'])->name('members.index');
        Route::post('members', [Admin\MemberController::class, 'store'])->name('members.store');
        Route::put('members/{membership}', [Admin\MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{membership}', [Admin\MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index');
        Route::get('impersonations', [Admin\ImpersonationController::class, 'index'])->name('impersonations.index');
    });
});

require __DIR__.'/admin-content.php';
