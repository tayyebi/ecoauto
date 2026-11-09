@extends('layouts.storefront')

@section('content')
    <nav class="breadcrumb" aria-label="مسیر">
        <a href="/">خانه</a>
        @foreach ($ancestors as $ancestor)
            <span>/</span><a href="{{ $ancestor->url() }}">{{ $ancestor->title }}</a>
        @endforeach
        <span>/</span><span>{{ $category->title }}</span>
    </nav>

    <header class="page-header">
        <h1>{{ $category->title }}</h1>
        @if ($category->description)<p>{{ $category->description }}</p>@endif
    </header>

    @if ($children->isNotEmpty())
        <div class="grid grid-categories">
            @foreach ($children as $child)
                <a class="card" href="{{ $child->url() }}">
                    <span class="card-icon">{{ $child->icon ?: '⚡' }}</span>
                    <span class="card-title">{{ $child->title }}</span>
                    <span class="card-count">{{ fa_number($child->products_count) }} محصول</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($products->isNotEmpty())
        <h2>محصولات</h2>
        <div class="grid grid-products">
            @foreach ($products as $product)
                @include('storefront.products.card', ['product' => $product])
            @endforeach
        </div>
        {{ $products->links() }}
    @endif

    @if ($posts->isNotEmpty())
        <h2>مطالب</h2>
        <div class="list">
            @foreach ($posts as $post)
                @include('storefront.blog.row', ['post' => $post])
            @endforeach
        </div>
    @endif
@endsection
