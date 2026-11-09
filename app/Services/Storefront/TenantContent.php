<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Menu;
use App\Models\Setting;
use App\Store;
use Illuminate\Support\Facades\Cache;

/**
 * DECISION (tenancy): the cache key carries the account id. A shared cache
 * store with an unqualified key is the classic multi-tenant data leak.
 *
 * Reads the per-tenant settings, with a per-account cache key so a shared
 * process cannot hand one tenant's settings to another.
 */
final class TenantContent
{
    /** @var array<string, array<string, mixed>> */
    private array $settings = [];

    public function all(): array
    {
        $account = Store::current();

        if ($account === null) {
            return [];
        }

        if (isset($this->settings[$account->id])) {
            return $this->settings[$account->id];
        }

        return $this->settings[$account->id] = Cache::remember(
            "tenant:{$account->id}:settings",
            now()->addMinutes(10),
            fn () => Setting::query()->get()
                ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value])
                ->all(),
        );
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->all(), $key, $default);

        return $value === null || $value === '' ? $default : $value;
    }

    public function siteTitle(): string
    {
        return (string) $this->setting('site.title', Store::current()?->name ?? config('store.name'));
    }

    public function siteDescription(): ?string
    {
        return $this->setting('site.description');
    }

    /** The primary navigation, falling back to a sensible default shape. */
    public function mainMenu(): array
    {
        $menu = Menu::forHandle('main');

        if ($menu !== null && $menu->items->isNotEmpty()) {
            return $menu->items->map(fn ($item) => [
                'label' => $item->label,
                'url' => $item->url,
                'target' => $item->target,
            ])->all();
        }

        return [
            ['label' => 'صفحه اصلی', 'url' => '/', 'target' => '_self'],
            ['label' => 'محصولات', 'url' => '/products/', 'target' => '_self'],
            ['label' => 'دسته‌بندی‌ها', 'url' => '/categories/', 'target' => '_self'],
            ['label' => 'تامین‌کنندگان', 'url' => '/vendors/', 'target' => '_self'],
            ['label' => 'وبلاگ', 'url' => '/blog/', 'target' => '_self'],
            ['label' => 'درباره ما', 'url' => '/about/', 'target' => '_self'],
            ['label' => 'تماس با ما', 'url' => '/contact/', 'target' => '_self'],
        ];
    }

    public function forget(): void
    {
        $account = Store::current();

        if ($account !== null) {
            unset($this->settings[$account->id]);
            Cache::forget("tenant:{$account->id}:settings");
        }
    }
}
