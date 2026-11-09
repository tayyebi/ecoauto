<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DECISION (tenancy): there is no account_id column anywhere in this schema.
 * Every storefront account has its own database with an identical copy of these
 * tables, so isolation is physical and every query is implicitly scoped.
 *
 * The storefront schema. Every account that hosts a storefront gets an
 * identical copy of these tables in its own database, so there is no account
 * column anywhere and a missing where() can never leak a sibling's rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 40)->default('tenant');
            $table->string('path');
            $table->string('filename', 191);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('title', 191);
            $table->string('description', 400)->nullable();
            $table->longText('body')->nullable();
            $table->string('layout', 40)->default('default');
            $table->boolean('is_published')->default(true);
            $table->string('meta_title', 191)->nullable();
            $table->string('meta_description', 400)->nullable();
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 40)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('url', 191);
            $table->unsignedInteger('position')->default(0);
            $table->string('target', 20)->default('_self');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug', 191)->unique();
            $table->string('title', 191);
            $table->string('description', 400)->nullable();
            $table->longText('body')->nullable();
            $table->string('icon', 16)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index('parent_id');
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('title', 191);
            $table->string('description', 400)->nullable();
            $table->longText('body')->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 191)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('title', 191);
            $table->string('description', 400)->nullable();
            $table->longText('body')->nullable();
            $table->string('sku', 191)->nullable()->index();
            $table->string('brand', 191)->nullable();
            $table->string('category_label', 191)->nullable();
            $table->string('country_of_origin', 100)->nullable();
            $table->string('material', 191)->nullable();
            $table->string('material_en', 191)->nullable();
            $table->string('gtin', 32)->nullable();
            // Prices are stored as integer minor units of the tenant currency
            // (Iranian rial has no minor unit, so this is whole rial).
            $table->unsignedBigInteger('price')->nullable();
            $table->string('currency', 8)->default('IRR');
            $table->string('image_path', 191)->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'position']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('product_vendor', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'vendor_id']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->string('title', 191);
            $table->string('description', 400)->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('category_post', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'post_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('email', 191)->nullable();
            $table->string('phone', 32)->index();
            $table->string('address', 500)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->string('currency', 8)->default('IRR');
            $table->string('payment_status', 30)->default('unpaid')->index();
            $table->string('payment_gateway', 40)->nullable();
            $table->string('payment_reference', 191)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 191);
            $table->string('sku', 191)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('driver', 40);
            $table->string('reference', 64)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 30)->default('pending')->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'payment_intents', 'order_lines', 'orders', 'customers',
            'category_post', 'posts',
            'product_vendor', 'category_product', 'products',
            'vendors', 'categories', 'menu_items', 'menus',
            'pages', 'media', 'settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
