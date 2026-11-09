<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;

final class PostController extends ContentController
{
    protected function model(): string
    {
        return Post::class;
    }

    protected function folder(): string
    {
        return 'posts';
    }

    protected function noun(): string
    {
        return 'نوشته';
    }

    protected function rules(?Model $existing): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'string'],
            // Jalali date as typed by an operator, e.g. ۱۴۰۳/۰۵/۱۲.
            'published_at' => ['nullable', 'string', 'max:10'],
            'is_published' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer'],
        ];
    }

    protected function prepare(array $data, ?Model $existing): array
    {
        $data = parent::prepare($data, $existing);

        $data['published_at'] = isset($data['published_at'])
            ? jalali_to_datetime((string) $data['published_at']) ?? now()
            : ($existing?->published_at ?? now());

        return $data;
    }

    protected function afterSave(Model $model, array $data): void
    {
        $model->categories()->sync(array_map('intval', $data['categories'] ?? []));
    }

    protected function formData(): array
    {
        return ['categories' => Category::query()->orderBy('title')->get()];
    }
}
