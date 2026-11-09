@extends('admin.partials.form')

@section('fields')
    <div class="field-row">
        <label>ایمیل
            <input type="email" name="email" value="{{ old('email', $item->email ?? '') }}" dir="ltr">
        </label>
        <label>تلفن
            <input name="phone" value="{{ old('phone', $item->phone ?? '') }}" dir="ltr">
        </label>
        <label>وبسایت
            <input name="website" value="{{ old('website', $item->website ?? '') }}" dir="ltr">
        </label>
    </div>
@endsection
