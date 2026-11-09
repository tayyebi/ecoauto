<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Membership;
use App\Models\User;
use App\Services\AuditLogger;
use App\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Members of an account.
 *
 * Roles are per account, and an owner or admin membership on a parent account
 * is inherited by every descendant; there is no way to grant access to a
 * subtree without granting it to the whole subtree.
 */
final class MemberController extends BaseAdminController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        $account = $this->account();

        return view('admin.members.index', $this->shareContext($request, [
            'meta' => ['title' => 'اعضا'],
            'account' => $account,
            'memberships' => Membership::query()
                ->with('user')
                ->where('account_id', $account->id)
                ->get(),
            'roles' => Membership::ROLES,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $this->account();

        abort_unless($this->user()->canManage($account), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:landlord.users,email'],
            'role' => ['required', Rule::in(Membership::ROLES)],
        ]);

        $target = User::query()->where('email', $data['email'])->firstOrFail();

        Membership::query()->updateOrCreate(
            ['user_id' => $target->id, 'account_id' => $account->id],
            ['role' => $data['role'], 'invited_by' => $this->user()->id],
        );

        $this->audit->log('member.added', $target, [
            'account' => $account->slug,
            'role' => $data['role'],
        ], $account->id);

        return back()->with('status', 'عضو اضافه شد.');
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $account = $this->account();

        abort_unless($this->user()->canManage($account), 403);
        abort_unless($membership->account_id === $account->id, 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(Membership::ROLES)],
        ]);

        $membership->update(['role' => $data['role']]);

        $this->audit->log('member.updated', $membership, $data, $account->id);

        return back()->with('status', 'نقش عضو تغییر کرد.');
    }

    public function destroy(Request $request, Membership $membership): RedirectResponse
    {
        $account = $this->account();

        abort_unless($this->user()->canManage($account), 403);
        abort_unless($membership->account_id === $account->id, 404);

        // Never let the last owner walk away from an account.
        $owners = Membership::query()
            ->where('account_id', $account->id)
            ->where('role', 'owner')
            ->count();

        if ($membership->role === 'owner' && $owners <= 1) {
            return back()->withErrors(['member' => 'حداقل یک مالک باید باقی بماند.']);
        }

        $membership->delete();

        $this->audit->log('member.removed', $membership, [], $account->id);

        return back()->with('status', 'عضو حذف شد.');
    }
}
