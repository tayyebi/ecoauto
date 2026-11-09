<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DECISION (data): the legacy Hugo site is not kept around. Its content was
 * exported once into a committed JSON snapshot, so the storefront's data is
 * reproducible from git while the running installation stores it in MySQL.
 *
 * Seeds a tenant database from a content snapshot produced by
 * `tools/import-hugo.py`.
 *
 * The snapshot is the archived legacy content (ecoauto.ir as of commit 876b6f2)
 * so the storefront's data survives without keeping the Hugo site around.
 */
final class EcoautoContentSeeder extends Seeder
{
    /** @var array<string, int> */
    private array $categoryIds = [];

    /** @var array<string, int> */
    private array $vendorIds = [];

    /**
     * Connection to seed. When null the seeder uses the default connection,
     * which the provisioner has already pointed at the tenant database.
     */
    private ?string $connection = null;

    public function setConnection(?string $connection): void
    {
        $this->connection = $connection;
    }

    private array $snapshot = [];

    /** An Eloquent builder bound to the tenant connection. */
    private function model(string $class): \Illuminate\Database\Eloquent\Builder
    {
        $query = $class::query();

        return $this->connection !== null ? $query->setConnection($this->connection) : $query;
    }

    public function run(): void
    {
        $this->snapshot = $this->loadSnapshot();

        DB::connection($this->connection)->transaction(function () {
            $this->seedSettings();
            $this->seedCategories();
            $this->seedVendors();
            $this->seedProducts();
            $this->seedPosts();
            $this->seedPages();
            $this->seedMenus();
        });
    }

    public function snapshotPath(): string
    {
        return database_path('seeders/data/ecoauto/content.json');
    }

    private function loadSnapshot(): array
    {
        $path = $this->snapshotPath();

        if (! is_file($path)) {
            throw new RuntimeException("Content snapshot missing at {$path}.");
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException('Content snapshot is not a JSON object.');
        }

        return $data;
    }

    private function seedSettings(): void
    {
        $site = $this->snapshot['site'] ?? [];
        $sections = $this->snapshot['sections'] ?? [];

        $settings = [
            'site.title' => $site['title'] ?? null,
            'site.description' => $site['description'] ?? null,
            'section.blog.title' => $sections['blog']['title'] ?? null,
            'section.blog.description' => $sections['blog']['description'] ?? null,
            'section.products.title' => $sections['products']['title'] ?? null,
            'section.products.description' => $sections['products']['description'] ?? null,
            'section.vendors.title' => $sections['vendors']['title'] ?? null,
            'section.categories.title' => $sections['categories']['title'] ?? null,
            'contact.phone' => '+989120982276',
            'contact.email' => 'tayyebimohammadreza@gmail.com',
            'contact.address' => 'تهران، ایران',
            'contact.hours' => 'شنبه تا پنجشنبه ۹ الی ۱۸',
            'home.highlights' => [
                ['title' => 'ضمانت اصالت', 'body' => 'تمامی محصولات دارای گارانتی اصالت و سلامت کالا می‌باشند'],
                ['title' => 'تامین تخصصی', 'body' => 'تخصص ما قطعات خودروهای برقی است؛ از باتری تا موتور'],
                ['title' => 'پشتیبانی فنی', 'body' => 'تیم فنی ما در تمام مراحل خرید همراه شماست'],
            ],
        ];

        foreach ($settings as $key => $value) {
            if ($value === null) {
                continue;
            }

            $this->model(Setting::class)->updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) ? $value : (string) $value],
            );
        }
    }

    private function seedCategories(): void
    {
        $categories = $this->snapshot['categories'] ?? [];
        $pending = $categories;

        // Parents may appear after their children in the snapshot, so loop
        // until every parent has been created (the tree is only two levels).
        $guard = 0;

        while ($pending !== [] && $guard++ < 10) {
            $remaining = [];

            foreach ($pending as $category) {
                $parent = $category['parent'] ?? null;

                if ($parent !== null && ! isset($this->categoryIds[$parent])) {
                    $remaining[] = $category;

                    continue;
                }

                $model = $this->model(Category::class)->create([
                    'parent_id' => $parent !== null ? $this->categoryIds[$parent] : null,
                    'slug' => $category['slug'],
                    'title' => $category['title'],
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'position' => 0,
                    'is_published' => true,
                ]);

                $this->categoryIds[$category['slug']] = (int) $model->getKey();
            }

            if ($remaining === $pending) {
                break;
            }

            $pending = $remaining;
        }
    }

    private function seedVendors(): void
    {
        foreach ($this->snapshot['vendors'] ?? [] as $vendor) {
            $model = $this->model(Vendor::class)->create([
                'slug' => $vendor['slug'],
                'title' => $vendor['title'],
                'description' => $vendor['description'],
                'body' => $this->body($vendor['body'] ?? null, (bool) ($vendor['body_is_html'] ?? false)),
                'email' => $vendor['email'],
                'phone' => $vendor['phone'],
                'website' => $vendor['website'],
                'is_published' => true,
            ]);

            $this->vendorIds[$vendor['slug']] = (int) $model->getKey();
        }
    }

    private function seedProducts(): void
    {
        foreach ($this->snapshot['products'] ?? [] as $product) {
            $model = $this->model(Product::class)->create([
                'slug' => $product['slug'],
                'title' => $product['title'],
                'description' => $product['description'],
                'body' => $product['body'],
                'sku' => $product['sku'],
                'brand' => $product['brand'],
                'category_label' => $product['category_label'],
                'country_of_origin' => $product['country_of_origin'],
                'material' => $product['material'],
                'material_en' => $product['material_en'],
                'gtin' => $product['gtin'],
                'price' => $product['price'],
                'currency' => $product['currency'] ?? 'IRR',
                'image_path' => $this->imagePath($product['image'] ?? null),
                'is_published' => true,
                'position' => 0,
            ]);

            $categoryIds = array_values(array_filter(
                array_map(fn (string $slug) => $this->categoryIds[$slug] ?? null, $product['categories'] ?? []),
            ));

            $vendorIds = array_values(array_filter(
                array_map(fn (string $slug) => $this->vendorIds[$slug] ?? null, $product['vendors'] ?? []),
            ));

            if ($categoryIds !== []) {
                $model->categories()->sync($categoryIds);
            }

            if ($vendorIds !== []) {
                $model->vendors()->sync($vendorIds);
            }
        }
    }

    private function seedPosts(): void
    {
        foreach ($this->snapshot['posts'] ?? [] as $post) {
            $model = $this->model(Post::class)->create([
                'slug' => $post['slug'],
                'title' => $post['title'],
                'description' => $post['description'],
                'body' => $post['body'],
                'published_at' => $post['published_at'] !== null
                    ? date('Y-m-d H:i:s', strtotime((string) $post['published_at']))
                    : now(),
                'is_published' => true,
            ]);

            $categoryIds = array_values(array_filter(
                array_map(fn (string $slug) => $this->categoryIds[$slug] ?? null, $post['categories'] ?? []),
            ));

            if ($categoryIds !== []) {
                $model->categories()->sync($categoryIds);
            }
        }
    }

    private function seedPages(): void
    {
        foreach ($this->snapshot['pages'] ?? [] as $page) {
            $this->model(Page::class)->create([
                'slug' => $page['slug'],
                'title' => $page['title'],
                'description' => $page['description'],
                'body' => $page['body'],
                'layout' => $page['slug'] === 'sitemap' ? 'sitemap' : 'default',
                'is_published' => true,
            ]);
        }
    }

    private function seedMenus(): void
    {
        $menu = $this->model(Menu::class)->create(['handle' => 'main', 'name' => 'منوی اصلی']);

        foreach ([
            ['label' => 'صفحه اصلی', 'url' => '/'],
            ['label' => 'محصولات', 'url' => '/products/'],
            ['label' => 'دسته‌بندی‌ها', 'url' => '/categories/'],
            ['label' => 'درباره ما', 'url' => '/about/'],
            ['label' => 'تماس با ما', 'url' => '/contact/'],
            ['label' => 'تامین‌کنندگان', 'url' => '/vendors/'],
            ['label' => 'وبلاگ', 'url' => '/blog/'],
        ] as $position => $item) {
            $menu->items()->create($item + ['position' => $position]);
        }
    }

    /**
     * Legacy vendor bodies were plain text; wrap them so they render as a
     * paragraph instead of being escaped into one long line.
     */
    private function body(?string $body, bool $isHtml): ?string
    {
        if ($body === null || trim($body) === '') {
            return null;
        }

        return $isHtml ? $body : nl2br(e($body), false);
    }

    /**
     * Map the legacy image reference onto the tenant media layout.
     *
     * The legacy catalogue pointed at `/images/...`; media is now served from
     * the tenant's own prefix, so only the path inside media is stored.
     */
    private function imagePath(?string $image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        return ltrim((string) preg_replace('#^/images/#', '', $image), '/');
    }
}
