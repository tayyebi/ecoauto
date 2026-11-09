<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impersonation sessions.
 *
 * A row is written when a user starts impersonating another user, and closed
 * when they stop, when the session expires, or when a platform admin revokes
 * it. The audit log records the individual actions taken in between.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->char('account_id', 26)->nullable()->index();
            $table->string('reason', 191)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('end_reason', 40)->nullable();
            $table->timestamps();

            $table->index(['target_id', 'ended_at']);
            $table->index(['actor_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonations');
    }
};
