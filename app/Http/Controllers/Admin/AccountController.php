<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\AuditLogger;
use App\Store;
use App\Tenancy\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * DECISION (tenancy): promoting a tenant to a reseller is never implicit, and a
 * database is never destroyed from a form. Creation is additive; suspension
 * locks a storefront without deleting its data.
 *
 * Only leaves host a storefront, so an account gains a database when it has no
 * sub-accounts and can be provisioned. Promoting a tenant to a reseller is a
 * matter of creating a child and detaching its database, which this screen
 * refuses to do implicitly: a database is never destroyed from a form.
 */
final class AccountController extends BaseAdminController
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): View
    {
        $me = $this->account();

        return view('admin.accounts.index', $this->shareContext($request, [
            'meta' => ['title' => 'حساب‌ها'],
            'account' => $me,
            'children' => Store::resolver()->registry()->children($me),
            'descendants' => Store::resolver()->registry()->descendants($me),
        ]));
    }

    public function create(Request $request): View
    {
        abort_unless($this->user()->canManage($this->account()), 403);

        return view('admin.accounts.create', $this->shareContext($request, [
            'meta' => ['title' => 'حساب جدید'],
            'account' => $this->account(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $parent = $this->account();

        abort_unless($this->user()->canManage($parent), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('landlord.accounts', 'slug')],
            'host' => ['nullable', 'string', 'max:253', Rule::unique('landlord.account_domains', 'host')],
            'storefront' => ['nullable', 'boolean'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['name'], '-', 'fa') ?: 'account-'.Str::lower(Str::random(6));

        $account = Store::provisioner()->createAccount(
            name: $data['name'],
            slug: $slug,
            parent: $parent,
            provision: (bool) ($data['storefront'] ?? false),
        );

        if (! empty($data['host'])) {
            $this->attachDomain($account, $data['host'], true);
        }

        $this->audit->log('account.created', $account, [
            'name' => $account->name,
            'slug' => $account->slug,
            'storefront' => (bool) ($data['storefront'] ?? false),
        ], $account->id);

        return redirect()
            ->route('admin.accounts.show', $account->id)
            ->with('status', 'حساب ایجاد شد.');
    }

    public function show(Request $request, string $account): View
    {
        $model = $this->findAccount($account);

        return view('admin.accounts.show', $this->shareContext($request, [
            'meta' => ['title' => $model->name],
            'account' => $model,
            'children' => Store::resolver()->registry()->children($model),
            'domains' => Store::resolver()->registry()->domainsFor($model),
            'memberships' => \App\Models\Membership::query()->with('user')->where('account_id', $model->id)->get(),
            'canManage' => $this->user()->canManage($model),
        ]));
    }

    public function edit(Request $request, string $account): View
    {
        $model = $this->findAccount($account);

        abort_unless($this->user()->canManage($model), 403);

        return view('admin.accounts.edit', $this->shareContext($request, [
            'meta' => ['title' => 'ویرایش '.$model->name],
            'account' => $model,
            'parent' => $model->parentId !== null
                ? Store::resolver()->registry()->find($model->parentId)
                : null,
            'domains' => Store::resolver()->registry()->domainsFor($model),
        ]));
    }

    public function update(Request $request, string $account): RedirectResponse
    {
        $model = $this->findAccount($account);

        abort_unless($this->user()->canManage($model), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'plan' => ['nullable', 'string', 'max:40'],
        ]);

        Store::databases()->landlord()->table('accounts')
            ->where('id', $model->id)
            ->update([
                'name' => $data['name'],
                'plan' => $data['plan'] ?? $model->plan,
                'updated_at' => now(),
            ]);

        $this->audit->log('account.updated', $model, $data, $model->id);

        return redirect()
            ->route('admin.accounts.show', $model->id)
            ->with('status', 'حساب به‌روزرسانی شد.');
    }

    public function suspend(Request $request, string $account): RedirectResponse
    {
        $model = $this->findAccount($account);

        abort_unless($this->user()->canManage($model), 403);

        Store::databases()->landlord()->table('accounts')
            ->where('id', $model->id)
            ->update(['status' => 'suspended', 'suspended_at' => now(), 'updated_at' => now()]);

        $this->audit->log('account.suspended', $model, [], $model->id);

        return back()->with('status', 'حساب تعلیق شد.');
    }

    public function resume(Request $request, string $account): RedirectResponse
    {
        $model = $this->findAccount($account);

        abort_unless($this->user()->canManage($model), 403);

        Store::databases()->landlord()->table('accounts')
            ->where('id', $model->id)
            ->update(['status' => 'active', 'suspended_at' => null, 'updated_at' => now()]);

        $this->audit->log('account.resumed', $model, [], $model->id);

        return back()->with('status', 'حساب فعال شد.');
    }

    public function addDomain(Request $request, string $account): RedirectResponse
    {
        $model = $this->findAccount($account);

        abort_unless($this->user()->canManage($model), 403);

        $data = $request->validate([
            'host' => ['required', 'string', 'max:253', Rule::unique('landlord.account_domains', 'host')],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $this->attachDomain($model, $data['host'], (bool) ($data['is_primary'] ?? false));

        $this->audit->log('account.domain.added', $model, ['host' => $data['host']], $model->id);

        return back()->with('status', 'دامنه اضافه شد.');
    }

    public function provision(Request $request, string $account): RedirectResponse
    {
        $model = $this->findAccount($account);

        abort_unless($this->user()->canManage($model), 403);

        // A reseller account is an internal node: it cannot host a storefront.
        if (Store::resolver()->registry()->children($model) !== []) {
            return back()->withErrors([
                'provision' => 'این حساب زیرحساب دارد و نمی‌تواند فروشگاه داشته باشد.',
            ]);
        }

        try {
            if (! $model->hasDatabase()) {
                $model = Store::provisioner()->attachDatabase($model);
            }

            Store::provisioner()->provision($model);
        } catch (Throwable $exception) {
            return back()->withErrors(['provision' => $exception->getMessage()]);
        }

        $this->audit->log('account.provisioned', $model, [], $model->id);

        return back()->with('status', 'پایگاه‌داده فروشگاه آماده شد.');
    }

    private function findAccount(string $id): Account
    {
        $model = Store::resolver()->registry()->find($id);

        abort_if($model === null, 404);

        abort_unless($this->user()->canAccess($model), 403, 'دسترسی به این حساب ندارید.');

        return $model;
    }

    private function attachDomain(Account $account, string $host, bool $primary): void
    {
        Store::databases()->landlord()->table('account_domains')->insert([
            'account_id' => $account->id,
            'host' => Str::lower(trim($host)),
            'is_primary' => $primary,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
