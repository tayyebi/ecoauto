@extends('admin.partials.form')

@section('fields')
    <label>دسته والد
        <select name="parent_id">
            <option value="">— بدون والد —</option>
            @foreach ($parents as $option)
                @continue($item !== null && $option->id === $item->id)
                <option value="{{ $option->id }}" @selected((int) old('parent_id', $item->parent_id ?? 0) === $option->id)>
                    {{ $option->title }}
                </option>
            @endforeach
        </select>
    </label>
    <div class="field-row">
        <label>آیکن
            <input name="icon" value="{{ old('icon', $item->icon ?? '') }}" maxlength="16">
        </label>
        <label>ترتیب
            <input name="position" value="{{ old('position', $item->position ?? 0) }}" inputmode="numeric" dir="ltr">
        </label>
    </div>
@endsection
