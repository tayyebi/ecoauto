<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Impersonation;
use App\Store;
use Illuminate\Support\Facades\Auth;

/**
 * DECISION (impersonation): every entry stores both the actor and the
 * effective user. During impersonation they differ, and the trail must name the
 * human behind the action rather than the identity they borrowed.
 *
 * DECISION (data): the audit log is landlord-side, not per tenant, so one query
 * can answer "who touched this account" without fanning out across databases.
 *
 * Every entry records both who acted (`actor_id`) and who the session was
 * signed in as (`effective_user_id`). During impersonation those differ, which
 * is the whole point: the trail shows the human behind the action.
 */
final class AuditLogger
{
    public function log(
        string $event,
        mixed $subject = null,
        array $changes = [],
        ?string $accountId = null,
    ): AuditLog {
        $actor = Auth::guard('landlord')->user();
        $impersonator = Store::accounts()->impersonator();

        return AuditLog::query()->create([
            'account_id' => $accountId ?? Store::current()?->id,
            'actor_id' => $impersonator?->id ?? $actor?->id,
            'effective_user_id' => $actor?->id,
            'impersonation_id' => $this->activeImpersonationId(),
            'event' => $event,
            'subject_type' => is_object($subject) ? $subject::class : null,
            'subject_id' => $this->subjectKey($subject),
            'changes' => $changes === [] ? null : $this->redact($changes),
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private function subjectKey(mixed $subject): ?string
    {
        if (! is_object($subject)) {
            return null;
        }

        if (method_exists($subject, 'getKey')) {
            $key = $subject->getKey();

            return $key === null ? null : (string) $key;
        }

        return isset($subject->id) ? (string) $subject->id : null;
    }

    public function activeImpersonationId(): ?string
    {
        return Store::accounts()->isImpersonating()
            ? session('store.impersonation_id')
            : null;
    }

    public function activeImpersonation(): ?Impersonation
    {
        $id = session('store.impersonation_id');

        if (! is_string($id) || $id === '') {
            return null;
        }

        return Impersonation::query()->whereKey($id)->whereNull('ended_at')->first();
    }

    /**
     * Never persist credentials in the audit trail.
     */
    private function redact(array $changes): array
    {
        $sensitive = ['password', 'password_confirmation', 'remember_token', 'api_token'];

        foreach (array_keys($changes) as $key) {
            if (in_array($key, $sensitive, true)) {
                $changes[$key] = '[redacted]';
            }
        }

        return $changes;
    }
}
