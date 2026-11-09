<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

final class CategoryController extends ContentController
{
    protected function model(): string
    {
        return Category::class;
    }

    protected function folder(): string
    {
        return 'categories';
    }

    protected function noun(): string
    {
        return 'دسته‌بندی';
    }

    protected function rules(?Model $existing): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:16'],
            'parent_id' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    protected function prepare(array $data, ?Model $existing): array
    {
        $data = parent::prepare($data, $existing);

        // A category may not become its own ancestor.
        if ($existing !== null && (int) ($data['parent_id'] ?? 0) === (int) $existing->getKey()) {
            $data['parent_id'] = null;
        }

        $data['position'] = (int) ($data['position'] ?? 0);

        return $data;
    }

    protected function formData(): array
    {
        return [
            'parents' => Category::query()->orderBy('title')->get(),
        ];
    }
}
