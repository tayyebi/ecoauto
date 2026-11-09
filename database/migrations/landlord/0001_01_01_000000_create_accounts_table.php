<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DECISION (tenancy): `path` is a materialised path so subtree queries are a
 * single indexed prefix match, and permission checks are string comparisons
 * rather than recursion.
 *
 * `path` is a materialised path of account ids ("/id/id/id/"), which makes
 * "everything under this account" a single indexed LIKE query and lets the
 * permission checks compare prefixes instead of recursing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('parent_id', 26)->nullable()->index();
            $table->string('path', 1024)->index();
            $table->string('slug', 100)->unique();
            $table->string('name', 191);
            $table->string('status', 20)->default('active')->index();
            $table->string('plan', 40)->default('standard');
            $table->string('database_name', 64)->nullable()->unique();
            $table->json('settings')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_domains', function (Blueprint $table) {
            $table->id();
            $table->char('account_id', 26)->index();
            $table->string('host', 253)->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::table('account_domains', function (Blueprint $table) {
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_domains');
        Schema::dropIfExists('accounts');
    }
};
