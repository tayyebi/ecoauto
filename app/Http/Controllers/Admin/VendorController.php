<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;

final class VendorController extends ContentController
{
    protected function model(): string
    {
        return Vendor::class;
    }

    protected function folder(): string
    {
        return 'vendors';
    }

    protected function noun(): string
    {
        return 'تامین‌کننده';
    }

    protected function rules(?Model $existing): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:191'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }
}
