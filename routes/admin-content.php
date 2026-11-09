<?php

declare(strict_types=1);

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant content administration
|--------------------------------------------------------------------------
|
| These routes operate on the account currently selected in the session, so
| the same screen edits whichever storefront the operator has switched into.
| Impersonation switches the effective user, not the target account.
|
*/

Route::prefix('admin')->name('admin.')->middleware('auth:landlord')->group(function () {
    Route::resource('content/products', Admin\ProductController::class)
        ->parameters(['products' => 'product'])
        ->except(['show'])
        ->names('products');

    Route::resource('content/posts', Admin\PostController::class)
        ->parameters(['posts' => 'post'])
        ->except(['show'])
        ->names('posts');

    Route::resource('content/categories', Admin\CategoryController::class)
        ->parameters(['categories' => 'category'])
        ->except(['show'])
        ->names('categories');

    Route::resource('content/vendors', Admin\VendorController::class)
        ->parameters(['vendors' => 'vendor'])
        ->except(['show'])
        ->names('vendors');

    Route::resource('content/pages', Admin\PageController::class)
        ->parameters(['pages' => 'page'])
        ->except(['show'])
        ->names('pages');

    Route::get('content/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('content/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::put('content/orders/{order}', [Admin\OrderController::class, 'update'])->name('orders.update');

    Route::get('settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
});
