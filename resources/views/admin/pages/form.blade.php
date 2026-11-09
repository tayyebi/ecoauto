@extends('admin.partials.form')

@section('fields')
    <div class="field-row">
        <label>عنوان متا
            <input name="meta_title" value="{{ old('meta_title', $item->meta_title ?? '') }}">
        </label>
        <label>توضیح متا
            <input name="meta_description" value="{{ old('meta_description', $item->meta_description ?? '') }}">
        </label>
    </div>
@endsection
