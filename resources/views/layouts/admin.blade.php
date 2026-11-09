@php
    $title = $meta['title'] ?? 'پنل';
    $me = auth('landlord')->user();
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} | {{ config('store.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin">
@if ($impersonator)
    <div class="impersonation-banner" role="status">
        <strong>جانشینی فعال:</strong>
        شما به‌جای {{ $me->name }} وارد شده‌اید (جانشین‌کننده: {{ $impersonator->name }}).
        همه اقدامات با نام شما ثبت می‌شود.
        <form method="post" action="{{ route('admin.impersonate.stop') }}" class="inline">
            @csrf
            <button type="submit" class="button button-small">پایان جانشینی</button>
        </form>
    </div>
@endif

<header class="admin-header">
    <div class="admin-header-inner">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <strong>{{ config('store.name') }}</strong>
            <span>پنل مدیریت</span>
        </a>

        @if ($availableAccounts !== [])
            <form method="post" action="{{ route('admin.accounts.switch') }}" class="account-switcher">
                @csrf
                <label for="account">حساب فعال</label>
                <select name="account" id="account" onchange="this.form.submit()">
                    @foreach ($availableAccounts as $option)
                        <option value="{{ $option->id }}" @selected($currentAccount && $currentAccount->id === $option->id)>
                            {{ str_repeat('— ', $option->depth()) }}{{ $option->name }}{{ $option->hasDatabase() ? '' : ' (بدون فروشگاه)' }}
                        </option>
                    @endforeach
                </select>
                {{-- Without JavaScript the select cannot submit itself, so there is
                     always a real button next to it. --}}
                <button type="submit" class="button button-small">تغییر</button>
            </form>
        @endif

        <div class="admin-user">
            <span>{{ $me->name }}</span>
            <form method="post" action="{{ route('admin.logout') }}" class="inline">
                @csrf
                <button type="submit" class="button button-small button-ghost">خروج</button>
            </form>
        </div>
    </div>
</header>

<div class="admin-shell">
    <nav class="admin-sidebar" aria-label="منوی پنل">
        <ul>
            <li><a href="{{ route('admin.dashboard') }}">داشبورد</a></li>
            <li><a href="{{ route('admin.accounts.index') }}">حساب‌ها</a></li>
            <li><a href="{{ route('admin.members.index') }}">اعضا</a></li>
            <li><a href="{{ route('admin.users.index') }}">کاربران</a></li>
            <li><a href="{{ route('admin.products.index') }}">محصولات</a></li>
            <li><a href="{{ route('admin.categories.index') }}">دسته‌بندی‌ها</a></li>
            <li><a href="{{ route('admin.vendors.index') }}">تامین‌کنندگان</a></li>
            <li><a href="{{ route('admin.posts.index') }}">وبلاگ</a></li>
            <li><a href="{{ route('admin.pages.index') }}">برگه‌ها</a></li>
            <li><a href="{{ route('admin.orders.index') }}">سفارش‌ها</a></li>
            <li><a href="{{ route('admin.settings.edit') }}">تنظیمات</a></li>
            <li><a href="{{ route('admin.audit.index') }}">گزارش رویدادها</a></li>
            <li><a href="{{ route('admin.impersonations.index') }}">جانشینی‌ها</a></li>
        </ul>
    </nav>

    <main class="admin-main">
        <h1 class="admin-title">{{ $title }}</h1>

        @if (session('status'))
            <p class="notice" role="status">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <ul class="errors" role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
