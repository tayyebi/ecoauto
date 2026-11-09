<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;

final class ProductController extends ContentController
{
    protected function model(): string
    {
        return Product::class;
    }

    protected function folder(): string
    {
        return 'products';
    }

    protected function noun(): string
    {
        return 'محصول';
    }

    protected function rules(?Model $existing): array
    {
        return [
            'title' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:191'],
            'brand' => ['nullable', 'string', 'max:191'],
            'category_label' => ['nullable', 'string', 'max:191'],
            'country_of_origin' => ['nullable', 'string', 'max:100'],
            'material' => ['nullable', 'string', 'max:191'],
            'material_en' => ['nullable', 'string', 'max:191'],
            'gtin' => ['nullable', 'string', 'max:32'],
            // Rial is a whole-unit currency; the form collects rial directly.
            'price' => ['nullable', 'integer', 'min:0'],
            'image_path' => ['nullable', 'string', 'max:191'],
            'is_published' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer'],
            'vendors' => ['nullable', 'array'],
            'vendors.*' => ['integer'],
        ];
    }

    protected function afterSave(Model $model, array $data): void
    {
        $model->categories()->sync(array_map('intval', $data['categories'] ?? []));
        $model->vendors()->sync(array_map('intval', $data['vendors'] ?? []));
    }

    protected function formData(): array
    {
        return [
            'categories' => Category::query()->orderBy('title')->get(),
            'vendors' => Vendor::query()->orderBy('title')->limit(500)->get(),
        ];
    }
}
