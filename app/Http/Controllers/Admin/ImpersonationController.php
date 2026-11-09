<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Impersonation;
use App\Models\User;
use App\Services\ImpersonationService;
use App\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DECISION (impersonation): starting and stopping are POST-only and the stop
 * control is rendered on every admin page, so ending impersonation is always
 * one explicit action away and never a prefetchable link.
 *
 * The banner and the stop button are rendered on every admin page, so ending
 * impersonation is always one POST away and never hidden behind a menu.
 */
final class ImpersonationController extends BaseAdminController
{
    public function __construct(private readonly ImpersonationService $impersonation)
    {
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

        $this->impersonation->start($request, $this->user(), $user, $data['reason'] ?? null);

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'اکنون به‌جای '.$user->name.' وارد شده‌اید.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $actor = $this->impersonation->stop($request, $this->user());

        if ($actor === null) {
            return redirect()->route('admin.login');
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('status', 'جانشینی پایان یافت؛ به حساب خود بازگشتید.');
    }

    public function index(Request $request): View
    {
        $query = Impersonation::query()->with(['actor', 'target'])->latest('started_at');

        // Account admins only see activity inside their own subtree; platform
        // staff see everything.
        if (! $this->user()->is_platform_admin) {
            $account = $this->account();
            $ids = [$account->id, ...array_map(
                static fn ($descendant) => $descendant->id,
                Store::resolver()->registry()->descendants($account),
            )];

            $query->whereIn('account_id', $ids);
        }

        return view('admin.impersonations.index', $this->shareContext($request, [
            'meta' => ['title' => 'جانشینی‌ها'],
            'impersonations' => $query->paginate(config('store.per_page.admin'))->withQueryString(),
        ]));
    }
}
