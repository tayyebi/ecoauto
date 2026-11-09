<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class UserController extends BaseAdminController
{
    public function index(Request $request): View
    {
        $account = $this->account();

        // Users reachable from this account: its own members, the members of
        // every descendant, and (for platform staff) everyone on the platform.
        $ids = [$account->id, ...array_map(
            static fn ($descendant) => $descendant->id,
            Store::resolver()->registry()->descendants($account),
        )];

        $query = User::query()->with('memberships');

        if (! $this->user()->is_platform_admin) {
            $query->whereHas('memberships', fn ($builder) => $builder->whereIn('account_id', $ids));
        }

        return view('admin.users.index', $this->shareContext($request, [
            'meta' => ['title' => 'کاربران'],
            'account' => $account,
            'users' => $query->orderBy('name')->paginate(config('store.per_page.admin'))->withQueryString(),
        ]));
    }
}
