<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>ورود | {{ config('store.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin admin-login">
<main class="login-card">
    <h1>{{ config('store.name') }}</h1>
    <p class="muted">ورود به پنل مدیریت</p>

    @if ($errors->any())
        <ul class="errors" role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="post" action="{{ route('admin.login.store') }}" class="stack">
        @csrf
        <label>ایمیل
            <input type="email" name="email" value="{{ old('email') }}" required autofocus dir="ltr">
        </label>
        <label>رمز عبور
            <input type="password" name="password" required dir="ltr">
        </label>
        <label class="inline">
            <input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار
        </label>
        <button type="submit" class="button button-primary">ورود</button>
    </form>
</main>
</body>
</html>
