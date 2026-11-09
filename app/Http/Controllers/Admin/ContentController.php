<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * DECISION (tenancy): these screens edit the account selected in the session,
 * never the hostname's account, so an operator can work on a tenant they are
 * not currently visiting.
 *
 * DECISION (data): Persian slugs are preserved verbatim and only suffixed on
 * collision, because the legacy URLs contain them and must keep resolving.
 *
 * Each concrete controller declares the model, the view folder and the field
 * rules; everything else (audit trail, slug handling, publish toggle) is here
 * so the six screens behave identically.
 */
abstract class ContentController extends BaseAdminController
{
    public function __construct(protected readonly AuditLogger $audit)
    {
    }

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** The Blade folder under resources/views/admin. */
    abstract protected function folder(): string;

    /** The audit event prefix, e.g. "product". */
    abstract protected function noun(): string;

    /** @return array<string, mixed> */
    abstract protected function rules(?Model $existing): array;

    /** @return array<string, mixed> */
    protected function prepare(array $data, ?Model $existing): array
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title'], '-', 'fa');
        }

        $data['slug'] = $this->uniqueSlug($data['slug'], $existing);

        $data['is_published'] = (bool) ($data['is_published'] ?? false);

        return $data;
    }

    /** Relations to sync after save; override where a model has pivots. */
    protected function afterSave(Model $model, array $data): void
    {
    }

    /** Extra data for the form view. */
    protected function formData(): array
    {
        return [];
    }

    public function index(Request $request)
    {
        $model = $this->model();
        $query = $model::query();

        if (is_string($search = $request->query('q')) && $search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }

        return view("admin.{$this->folder()}.index", $this->shareContext($request, [
            'meta' => ['title' => $this->noun().'ها'],
            'items' => $query->orderByDesc('id')->paginate(config('store.per_page.admin'))->withQueryString(),
            'search' => $search ?? null,
            'folder' => $this->folder(),
        ]));
    }

    public function create(Request $request)
    {
        $this->managedAccount();

        return view("admin.{$this->folder()}.form", $this->shareContext($request, array_merge([
            'meta' => ['title' => 'افزودن '.$this->noun()],
            'item' => null,
            'folder' => $this->folder(),
        ], $this->formData())));
    }

    public function edit(Request $request, string $id)
    {
        $this->managedAccount();

        $model = $this->model();
        $item = $model::query()->findOrFail($id);

        return view("admin.{$this->folder()}.form", $this->shareContext($request, array_merge([
            'meta' => ['title' => 'ویرایش '.$this->noun()],
            'item' => $item,
            'folder' => $this->folder(),
        ], $this->formData())));
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $this->managedAccount();

        $data = $request->validate($this->rules(null));
        $data = $this->prepare($data, null);

        $model = $this->model();
        $item = $model::query()->create($data);

        $this->afterSave($item, $data);

        $this->audit->log("{$this->noun()}.created", $item, $data, $account->id);

        return redirect()
            ->route("admin.{$this->folder()}.index")
            ->with('status', 'مطلب ایجاد شد.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $account = $this->managedAccount();

        $model = $this->model();
        $item = $model::query()->findOrFail($id);

        $data = $request->validate($this->rules($item));
        $data = $this->prepare($data, $item);

        $item->update($data);

        $this->afterSave($item, $data);

        $this->audit->log("{$this->noun()}.updated", $item, $data, $account->id);

        return redirect()
            ->route("admin.{$this->folder()}.index")
            ->with('status', 'تغییرات ذخیره شد.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $account = $this->managedAccount();

        $model = $this->model();
        $item = $model::query()->findOrFail($id);

        $this->audit->log("{$this->noun()}.deleted", $item, ['title' => $item->title], $account->id);

        $item->delete();

        return redirect()
            ->route("admin.{$this->folder()}.index")
            ->with('status', 'مطلب حذف شد.');
    }

    /**
     * Persian slugs are kept verbatim (the legacy URLs depend on them) and only
     * suffixed when they collide.
     */
    protected function uniqueSlug(string $slug, ?Model $existing): string
    {
        $model = $this->model();
        $base = $slug !== '' ? $slug : (string) Str::lower(Str::random(8));
        $candidate = $base;
        $suffix = 2;

        while (
            $model::query()
                ->where('slug', $candidate)
                ->when($existing !== null, fn ($query) => $query->whereKeyNot($existing->getKey()))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
