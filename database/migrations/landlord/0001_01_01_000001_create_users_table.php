<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level users and their memberships.
 *
 * Users are global: one person, one login, possibly several accounts. What a
 * user may do inside an account is expressed by a membership row, and access to
 * sub-accounts is inherited from an ancestor membership with the owner or admin
 * role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('email', 191)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_platform_admin')->default(false);
            $table->char('default_account_id', 26)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 191)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('account_id', 26)->index();
            $table->string('role', 20)->default('viewer');
            $table->char('invited_by', 26)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'account_id']);
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
