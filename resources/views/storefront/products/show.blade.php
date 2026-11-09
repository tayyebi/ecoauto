@extends('layouts.storefront')

@push('head')
@include('storefront.products.schema', ['product' => $product])
@endpush

@section('content')
    <nav class="breadcrumb" aria-label="مسیر">
        <a href="/">خانه</a>
        @foreach ($product->categories as $category)
            <span>/</span><a href="{{ $category->url() }}">{{ $category->title }}</a>
        @endforeach
        <span>/</span><span>{{ $product->title }}</span>
    </nav>

    <article class="product-detail">
        <div class="product-detail-media">
            @if ($image = $product->imageUrl())
                <img src="{{ $image }}" alt="{{ $product->title }}" width="400" height="400">
            @else
                <div class="card-image placeholder">تصویر محصول</div>
            @endif
        </div>

        <div class="product-detail-body">
            <h1>{{ $product->title }}</h1>
            <p class="lead">{{ $product->description }}</p>

            <dl class="spec-list">
                @foreach ([
                    'کد فنی' => $product->sku,
                    'برند' => $product->brand,
                    'دسته' => $product->category_label,
                    'کشور سازنده' => $product->country_of_origin,
                    'جنس' => $product->material,
                ] as $label => $value)
                    @if ($value)
                        <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
                    @endif
                @endforeach
            </dl>

            @if ($product->vendors->isNotEmpty())
                <p class="vendors">
                    <span>تامین‌کنندگان:</span>
                    @foreach ($product->vendors as $vendor)
                        <a href="{{ $vendor->url() }}">{{ $vendor->title }}</a>@if (! $loop->last), @endif
                    @endforeach
                </p>
            @endif

            <p class="price price-large">{{ $product->priceLabel() ?? 'تماس بگیرید' }}</p>

            @if ($product->hasPrice())
                <form method="post" action="{{ route('cart.add', absolute: false) }}" class="add-form">
                    @csrf
                    <input type="hidden" name="product" value="{{ $product->slug }}">
                    <label>تعداد
                        <input type="number" name="quantity" value="1" min="1" max="99">
                    </label>
                    <button type="submit" class="button">افزودن به سبد خرید</button>
                </form>
            @endif

            <div class="prose">{!! $product->body !!}</div>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="section">
            <h2>محصولات مرتبط</h2>
            <div class="grid grid-products">
                @foreach ($related as $item)
                    @include('storefront.products.card', ['product' => $item])
                @endforeach
            </div>
        </section>
    @endif
@endsection
