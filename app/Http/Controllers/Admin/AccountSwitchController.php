<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\AuditLogger;
use App\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switch the account the admin session is working in.
 *
 * Allowed for any account in the user's reachable set, which includes
 * descendants inherited from an ancestor membership. POST only.
 */
final class AccountSwitchController extends BaseAdminController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account' => ['required', 'string'],
        ]);

        $account = Store::resolver()->registry()->find($data['account']);

        abort_if($account === null, 404);

        abort_unless($this->user()->canAccess($account), 403, 'دسترسی به این حساب ندارید.');

        $request->session()->put('store.account', $account->id);

        $this->audit->log('account.switched', $account, ['account' => $account->slug], $account->id);

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'حساب فعال به '.$account->name.' تغییر کرد.');
    }
}
