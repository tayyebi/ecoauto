@extends('admin.partials.form')

@section('fields')
    <label>تاریخ انتشار (شمسی، مثلا ۱۴۰۴/۰۱/۱۵)
        <input name="published_at" value="{{ old('published_at', $item?->published_at ? fa_date($item->published_at) : '') }}" dir="ltr">
    </label>

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
@endsection
