<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuditController extends BaseAdminController
{
    public function index(Request $request): View
    {
        $account = $this->account();

        $ids = [$account->id, ...array_map(
            static fn ($descendant) => $descendant->id,
            Store::resolver()->registry()->descendants($account),
        )];

        $query = AuditLog::query()->with(['actor'])->latest('id');

        if (! $this->user()->is_platform_admin) {
            $query->whereIn('account_id', $ids);
        }

        if (is_string($event = $request->query('event')) && $event !== '') {
            $query->where('event', $event);
        }

        return view('admin.audit.index', $this->shareContext($request, [
            'meta' => ['title' => 'گزارش رویدادها'],
            'account' => $account,
            'event' => $event,
            'logs' => $query->paginate(config('store.per_page.admin'))->withQueryString(),
        ]));
    }
}
