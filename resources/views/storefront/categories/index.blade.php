@extends('layouts.storefront')

@section('content')
    <header class="page-header">
        <h1>دسته‌بندی‌ها</h1>
        <p>دسته‌بندی قطعات و تجهیزات خودروهای برقی</p>
    </header>

    <div class="grid grid-categories">
        @foreach ($categories as $category)
            <a class="card" href="{{ $category->url() }}">
                <span class="card-icon">{{ $category->icon ?: '⚡' }}</span>
                <span class="card-title">{{ $category->title }}</span>
                <span class="card-meta">{{ $category->description }}</span>
                <span class="card-count">{{ fa_number($category->products_count) }} محصول</span>
            </a>
        @endforeach
    </div>
@endsection
