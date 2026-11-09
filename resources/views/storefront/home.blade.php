@extends('layouts.storefront')

@section('content')
    <section class="hero">
        <div>
            <h1>قطعات اصلی خودروهای برقی</h1>
            <p class="lead">{{ app(\App\Services\Storefront\TenantContent::class)->setting('site.description', 'قطعات و تجهیزات خودروهای برقی') }}</p>
            <a class="button" href="{{ route('products.index', absolute: false) }}">مشاهده محصولات</a>
        </div>
        @if ($hero = media_url('hero-image.webp'))
            <img class="hero-image" src="{{ $hero }}" alt="قطعات خودروهای برقی" width="450" height="300">
        @endif
    </section>

    <section class="section">
        <h2>دسته‌بندی محصولات</h2>
        <div class="grid grid-categories">
            @foreach ($rootCategories as $category)
                <a class="card" href="{{ $category->url() }}">
                    <span class="card-icon">{{ $category->icon ?: '⚡' }}</span>
                    <span class="card-title">{{ $category->title }}</span>
                    <span class="card-meta">{{ fa_number($category->products_count) }} محصول</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="section">
        <h2>آخرین محصولات</h2>
        <div class="grid grid-products">
            @foreach ($latestProducts as $product)
                @include('storefront.products.card', ['product' => $product])
            @endforeach
        </div>
    </section>

    @if (! empty($highlights))
        <section class="section">
            <h2>چرا {{ app(\App\Services\Storefront\TenantContent::class)->siteTitle() }}؟</h2>
            <div class="grid grid-categories">
                @foreach ($highlights as $highlight)
                    <div class="card">
                        <span class="card-icon">✓</span>
                        <span class="card-title">{{ $highlight['title'] ?? '' }}</span>
                        <span class="card-meta">{{ $highlight['body'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
