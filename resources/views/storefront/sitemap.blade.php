@extends('layouts.storefront')

@section('content')
    <header class="page-header">
        <h1>نقشه سایت</h1>
        <p>دسترسی سریع به تمام صفحات سایت</p>
    </header>

    <div class="grid grid-categories">
        <section class="card card-static">
            <h2 class="card-title">صفحات اصلی</h2>
            <ul>
                <li><a href="/">صفحه اصلی</a></li>
                @foreach ($pages as $page)
                    <li><a href="/{{ $page->slug }}/">{{ $page->title }}</a></li>
                @endforeach
                <li><a href="/products/">محصولات</a></li>
                <li><a href="/categories/">دسته‌بندی‌ها</a></li>
                <li><a href="/vendors/">تامین‌کنندگان</a></li>
                <li><a href="/blog/">وبلاگ</a></li>
            </ul>
        </section>

        <section class="card card-static">
            <h2 class="card-title">دسته‌بندی‌ها</h2>
            <ul>
                @foreach ($categories as $category)
                    <li><a href="{{ $category->url() }}">{{ $category->title }} ({{ fa_number($category->products_count) }})</a></li>
                @endforeach
            </ul>
        </section>

        <section class="card card-static">
            <h2 class="card-title">تامین‌کنندگان</h2>
            <ul class="cols">
                @foreach ($vendors as $vendor)
                    <li><a href="{{ $vendor->url() }}">{{ $vendor->title }}</a></li>
                @endforeach
            </ul>
        </section>

        <section class="card card-static">
            <h2 class="card-title">محصولات</h2>
            <ul class="cols">
                @foreach ($products as $product)
                    <li><a href="{{ $product->url() }}">{{ $product->title }}</a></li>
                @endforeach
            </ul>
        </section>

        <section class="card card-static">
            <h2 class="card-title">وبلاگ</h2>
            <ul class="cols">
                @foreach ($posts as $post)
                    <li><a href="{{ $post->url() }}">{{ $post->title }}</a></li>
                @endforeach
            </ul>
        </section>
    </div>
@endsection
