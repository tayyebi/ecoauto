<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Storefront\TenantContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SettingsController extends BaseAdminController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TenantContent $content,
    ) {
    }

    public function edit(Request $request): View
    {
        $this->storefrontAccount();

        return view('admin.settings.edit', $this->shareContext($request, [
            'meta' => ['title' => 'تنظیمات'],
            'settings' => $this->content->all(),
        ]));
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $this->managedStorefrontAccount();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:400'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'string', 'max:191'],
        ]);

        foreach ([
            'site.title' => $data['title'],
            'site.description' => $data['description'] ?? null,
            'contact.phone' => $data['phone'] ?? null,
            'contact.email' => $data['email'] ?? null,
            'contact.address' => $data['address'] ?? null,
            'contact.hours' => $data['hours'] ?? null,
        ] as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->content->forget();

        $this->audit->log('settings.updated', $account, $data, $account->id);

        return back()->with('status', 'تنظیمات ذخیره شد.');
    }
}
