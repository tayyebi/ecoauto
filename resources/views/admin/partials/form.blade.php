@extends('layouts.admin')

@php
    $isUpdate = $item !== null;
    $action = $isUpdate
        ? route('admin.'.$folder.'.update', $item)
        : route('admin.'.$folder.'.store');
@endphp

@section('content')
    <form method="post" action="{{ $action }}" class="stack card">
        @csrf
        @if ($isUpdate)
            @method('put')
        @endif

        <label>عنوان
            <input name="title" value="{{ old('title', $item->title ?? '') }}" required>
        </label>
        <label>شناسه (برای آدرس)
            <input name="slug" value="{{ old('slug', $item->slug ?? '') }}" dir="ltr">
        </label>
        <label>توضیح کوتاه
            <input name="description" value="{{ old('description', $item->description ?? '') }}">
        </label>

        @yield('fields')

        <label>متن (HTML)
            <textarea name="body" rows="14" dir="ltr" class="code">{{ old('body', $item->body ?? '') }}</textarea>
        </label>

        <label class="inline">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $item->is_published ?? true))>
            منتشر شود
        </label>

        <div class="actions">
            <button class="button button-primary" type="submit">ذخیره</button>
            <a class="button button-ghost" href="{{ route('admin.'.$folder.'.index') }}">بازگشت</a>
        </div>
    </form>
@endsection
