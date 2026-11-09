@php
    $content = app(\App\Services\Storefront\TenantContent::class);
    $siteTitle = $content->siteTitle();
    $title = isset($meta['title']) ? $meta['title'] : null;
    $description = $meta['description'] ?? $content->siteDescription();
    $canonical = $meta['canonical'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' | '.$siteTitle : $siteTitle }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @if ($canonical)
        <link rel="canonical" href="{{ url($canonical) }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/store.css') }}">
    @include('storefront.partials.schema-organization')
    @stack('head')
</head>
<body>
<a class="skip-link" href="#main">رفتن به محتوای اصلی</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/">
            @if ($logo = media_url('logo.webp'))
                <img src="{{ $logo }}" alt="{{ $siteTitle }}" width="40" height="40">
            @endif
            <span>{{ $siteTitle }}</span>
        </a>

        <nav class="site-nav" aria-label="منوی اصلی">
            <ul>
                @foreach ($content->mainMenu() as $item)
                    <li>
                        <a href="{{ $item['url'] }}"
                           @class(['is-active' => request()->getPathInfo() === rtrim(parse_url($item['url'], PHP_URL_PATH) ?? '/', '/')])
                           @if (($item['target'] ?? '_self') !== '_self') target="{{ $item['target'] }}" rel="noopener" @endif>{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <a class="cart-link" href="{{ route('cart.show', absolute: false) }}">
            سبد خرید
            @php($cartCount = app(\App\Services\Cart\Cart::class)->count())
            @if ($cartCount > 0)
                <span class="badge">{{ fa_number($cartCount) }}</span>
            @endif
        </a>
    </div>
</header>

@if (session('status'))
    <div class="container">
        <p class="notice" role="status">{{ session('status') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="container">
        <ul class="errors" role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<main id="main" class="container">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <h2 class="footer-title">{{ $siteTitle }}</h2>
            <p>{{ $content->setting('site.description') }}</p>
        </div>
        <div>
            <h2 class="footer-title">دسترسی</h2>
            <ul>
                <li><a href="/about/">درباره ما</a></li>
                <li><a href="/contact/">تماس با ما</a></li>
                <li><a href="/sitemap/">نقشه سایت</a></li>
                <li><a href="/vendors/">تامین‌کنندگان</a></li>
            </ul>
        </div>
        <div>
            <h2 class="footer-title">ارتباط</h2>
            <ul>
                @if ($phone = $content->setting('contact.phone'))
                    <li><a href="tel:{{ $phone }}" dir="ltr">{{ $phone }}</a></li>
                @endif
                @if ($email = $content->setting('contact.email'))
                    <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                @endif
                @if ($address = $content->setting('contact.address'))
                    <li>{{ $address }}</li>
                @endif
            </ul>
        </div>
    </div>
    <div class="container footer-bottom">
        &copy; {{ fa_date(now(), 'Y') }} {{ $siteTitle }} — تمامی حقوق محفوظ است.
    </div>
</footer>
</body>
</html>
