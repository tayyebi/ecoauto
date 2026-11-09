@extends('admin.partials.form')

@section('fields')
    <div class="field-row">
        <label>کد فنی
            <input name="sku" value="{{ old('sku', $item->sku ?? '') }}" dir="ltr">
        </label>
        <label>برند
            <input name="brand" value="{{ old('brand', $item->brand ?? '') }}">
        </label>
        <label>کشور سازنده
            <input name="country_of_origin" value="{{ old('country_of_origin', $item->country_of_origin ?? '') }}">
        </label>
    </div>

    <div class="field-row">
        <label>جنس
            <input name="material" value="{{ old('material', $item->material ?? '') }}">
        </label>
        <label>جنس (انگلیسی)
            <input name="material_en" value="{{ old('material_en', $item->material_en ?? '') }}" dir="ltr">
        </label>
        <label>بارکد (GTIN)
            <input name="gtin" value="{{ old('gtin', $item->gtin ?? '') }}" dir="ltr">
        </label>
    </div>

    <div class="field-row">
        <label>قیمت (ریال) — خالی یعنی «تماس بگیرید»
            <input name="price" value="{{ old('price', $item->price ?? '') }}" inputmode="numeric" dir="ltr">
        </label>
        <label>برچسب دسته (متن آزاد)
            <input name="category_label" value="{{ old('category_label', $item->category_label ?? '') }}">
        </label>
        <label>تصویر (مسیر داخل media)
            <input name="image_path" value="{{ old('image_path', $item->image_path ?? '') }}" dir="ltr">
        </label>
    </div>

    <fieldset>
        <legend>دسته‌بندی‌ها</legend>
        @foreach ($categories as $option)
            <label class="inline">
                <input type="checkbox" name="categories[]" value="{{ $option->id }}"
                       @checked(in_array($option->id, old('categories', $item?->categories->pluck('id')->all() ?? [])))>
                {{ $option->title }}
            </label>
        @endforeach
    </fieldset>

    <fieldset>
        <legend>تامین‌کنندگان</legend>
        <div class="chip-list">
            @foreach ($vendors as $option)
                <label class="inline">
                    <input type="checkbox" name="vendors[]" value="{{ $option->id }}"
                           @checked(in_array($option->id, old('vendors', $item?->vendors->pluck('id')->all() ?? [])))>
                    {{ $option->title }}
                </label>
            @endforeach
        </div>
    </fieldset>
@endsection
