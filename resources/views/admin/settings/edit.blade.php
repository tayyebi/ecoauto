@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ route('admin.settings.update') }}" class="stack card">
        @csrf
        @method('put')

        <label>عنوان سایت
            <input name="title" value="{{ old('title', $settings['site.title'] ?? '') }}" required>
        </label>
        <label>توضیح سایت
            <input name="description" value="{{ old('description', $settings['site.description'] ?? '') }}">
        </label>
        <label>تلفن
            <input name="phone" value="{{ old('phone', $settings['contact.phone'] ?? '') }}" dir="ltr">
        </label>
        <label>ایمیل
            <input type="email" name="email" value="{{ old('email', $settings['contact.email'] ?? '') }}" dir="ltr">
        </label>
        <label>نشانی
            <input name="address" value="{{ old('address', $settings['contact.address'] ?? '') }}">
        </label>
        <label>ساعت کاری
            <input name="hours" value="{{ old('hours', $settings['contact.hours'] ?? '') }}">
        </label>

        <button class="button button-primary" type="submit">ذخیره تنظیمات</button>
    </form>
@endsection
