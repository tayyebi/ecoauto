<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Impersonation;
use App\Models\User;
use App\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * DECISION (impersonation): the actor's privileges are not merged into the
 * target's. You see exactly what the target sees, which is what makes the
 * audit trail meaningful and prevents privilege escalation by impersonation.
 *
 * DECISION (security): the session id is rotated on start and stop because the
 * identity changes underneath the client.
 *
 * Policy lives on the model (User::canImpersonate); this service only performs
 * the session dance and records the audit trail. While impersonating, the
 * actor's own privileges are not merged into the target's: you see exactly
 * what the target sees, which is what makes the trail meaningful.
 */
final class ImpersonationService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function start(Request $request, User $actor, User $target, ?string $reason = null): Impersonation
    {
        abort_unless($actor->canImpersonate($target), 403, 'شما اجازه جانشینی این کاربر را ندارید.');

        $account = Store::resolver()->manageableBy($target)[0] ?? $target->preferredAccount();

        $record = Impersonation::query()->create([
            'actor_id' => $actor->id,
            'target_id' => $target->id,
            'account_id' => $account?->id,
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'started_at' => now(),
        ]);

        $request->session()->put('store.impersonator_id', $actor->id);
        $request->session()->put('store.impersonation_id', $record->id);

        if ($account !== null) {
            $request->session()->put('store.account', $account->id);
        }

        // Rotate the session id: the identity just changed under the client.
        $request->session()->regenerate();

        Auth::guard('landlord')->login($target);

        Store::accounts()->setImpersonator($actor);

        $this->audit->log('impersonation.started', $target, [
            'reason' => $reason,
            'account_id' => $account?->id,
        ], $account?->id);

        return $record;
    }

    public function stop(Request $request, User $stopper): ?User
    {
        $impersonatorId = $request->session()->pull('store.impersonator_id');
        $recordId = $request->session()->pull('store.impersonation_id');

        if (! is_string($impersonatorId)) {
            return null;
        }

        $actor = User::query()->find($impersonatorId);

        if ($recordId !== null) {
            Impersonation::query()
                ->whereKey($recordId)
                ->whereNull('ended_at')
                ->update([
                    'ended_at' => now(),
                    'ended_by_id' => $stopper->id,
                    'end_reason' => $stopper->id === $actor?->id ? 'manual' : 'revoked',
                ]);
        }

        if ($actor === null) {
            Auth::guard('landlord')->logout();

            return null;
        }

        $request->session()->regenerate();
        Auth::guard('landlord')->login($actor);

        Store::accounts()->setImpersonator(null);

        $this->audit->log('impersonation.stopped', $actor, ['ended_by' => $stopper->id]);

        return $actor;
    }
}
