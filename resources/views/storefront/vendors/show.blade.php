@extends('layouts.storefront')

@section('content')
    <nav class="breadcrumb" aria-label="مسیر">
        <a href="/">خانه</a>
        <span>/</span><a href="{{ route('vendors.index', absolute: false) }}">تامین‌کنندگان</a>
        <span>/</span><span>{{ $vendor->title }}</span>
    </nav>

    <header class="page-header">
        <h1>{{ $vendor->title }}</h1>
        @if ($vendor->description)<p>{{ $vendor->description }}</p>@endif
    </header>

    <div class="contact-grid">
        @if ($vendor->email)
            <div><span class="label">ایمیل</span><a href="mailto:{{ $vendor->email }}">{{ $vendor->email }}</a></div>
        @endif
        @if ($vendor->phone)
            <div><span class="label">تلفن</span><a href="tel:{{ $vendor->phone }}" dir="ltr">{{ $vendor->phone }}</a></div>
        @endif
        @if ($url = $vendor->websiteUrl())
            <div><span class="label">وبسایت</span><a href="{{ $url }}" target="_blank" rel="noopener">{{ $vendor->website }}</a></div>
        @endif
    </div>

    @if ($vendor->body)
        <div class="prose">{!! $vendor->body !!}</div>
    @endif

    @if ($products->isNotEmpty())
        <section class="section">
            <h2>محصولات این تامین‌کننده ({{ fa_number($products->total()) }})</h2>
            <div class="grid grid-products">
                @foreach ($products as $product)
                    @include('storefront.products.card', ['product' => $product])
                @endforeach
            </div>
            {{ $products->links() }}
        </section>
    @endif
@endsection
