<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Page;
use Illuminate\Database\Eloquent\Model;

final class PageController extends ContentController
{
    protected function model(): string
    {
        return Page::class;
    }

    protected function folder(): string
    {
        return 'pages';
    }

    protected function noun(): string
    {
        return 'برگه';
    }

    protected function rules(?Model $existing): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:400'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }
}
