@extends('layouts.admin')

@section('content')
    <form method="post" action="{{ route('admin.accounts.store') }}" class="stack card">
        @csrf
        <p class="muted">این حساب زیرمجموعه «{{ $account->name }}» ساخته می‌شود.</p>

        <label>نام حساب
            <input name="name" value="{{ old('name') }}" required>
        </label>
        <label>شناسه (اختیاری)
            <input name="slug" value="{{ old('slug') }}" dir="ltr" placeholder="ecoauto-dealer">
        </label>
        <label>دامنه (اختیاری)
            <input name="host" value="{{ old('host') }}" dir="ltr" placeholder="shop.example.ir">
        </label>
        <label class="inline">
            <input type="checkbox" name="storefront" value="1" @checked(old('storefront'))>
            این حساب فروشگاه داشته باشد (پایگاه‌داده ساخته می‌شود)
        </label>
        <p class="hint">
            حسابی که فروشگاه دارد نمی‌تواند زیرحساب بپذیرد. اگر بعدا زیرحساب اضافه کنید،
            فروشگاه این حساب غیرفعال می‌شود.
        </p>

        <button type="submit" class="button button-primary">ایجاد حساب</button>
    </form>
@endsection
