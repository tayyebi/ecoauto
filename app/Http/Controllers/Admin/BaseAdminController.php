<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Store;
use App\Tenancy\Account;
use Illuminate\Http\Request;

abstract class BaseAdminController extends Controller
{
    protected function user(): User
    {
        /** @var User $user */
        $user = request()->user('landlord');

        return $user;
    }

    /** The account the session is working in. */
    protected function account(): Account
    {
        $account = Store::current();

        abort_if($account === null, 404, 'حسابی انتخاب نشده است.');

        abort_unless($this->user()->canAccess($account), 403, 'دسترسی به این حساب ندارید.');

        return $account;
    }

    /** The account, requiring write access. */
    protected function managedAccount(): Account
    {
        $account = $this->account();

        abort_unless($this->user()->canEdit($account), 403, 'اجازه ویرایش این حساب را ندارید.');

        return $account;
    }

    /**
     * Every admin screen shares this context: the account switcher needs the
     * list of reachable accounts and the impersonation banner needs to know
     * whether the session is borrowed.
     */
    protected function shareContext(Request $request, array $data = []): array
    {
        return array_merge([
            'currentAccount' => Store::current(),
            'availableAccounts' => Store::resolver()->manageableBy($this->user()),
            'impersonator' => Store::accounts()->impersonator(),
            'platformAdmin' => $this->user()->is_platform_admin,
        ], $data);
    }
}
