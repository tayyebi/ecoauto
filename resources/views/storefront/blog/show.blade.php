@extends('layouts.storefront')

@section('content')
    <nav class="breadcrumb" aria-label="مسیر">
        <a href="/">خانه</a>
        <span>/</span><a href="{{ route('blog.index', absolute: false) }}">وبلاگ</a>
        <span>/</span><span>{{ $post->title }}</span>
    </nav>

    <article class="prose-page">
        <h1>{{ $post->title }}</h1>
        <p class="meta">
            <time datetime="{{ $post->published_at?->toDateString() }}">{{ fa_date($post->published_at) }}</time>
            @foreach ($post->categories as $category)
                <span>·</span><a href="{{ $category->url() }}">{{ $category->title }}</a>
            @endforeach
        </p>
        <div class="prose">{!! $post->body !!}</div>
    </article>

    @if ($related->isNotEmpty())
        <section class="section">
            <h2>مطالب مرتبط</h2>
            <div class="list">
                @foreach ($related as $item)
                    @include('storefront.blog.row', ['post' => $item])
                @endforeach
            </div>
        </section>
    @endif
@endsection
