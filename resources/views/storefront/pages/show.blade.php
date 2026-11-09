@extends('layouts.storefront')

@section('content')
    <nav class="breadcrumb" aria-label="مسیر">
        <a href="/">خانه</a>
        <span>/</span><span>{{ $page->title }}</span>
    </nav>

    <article class="prose-page">
        <h1>{{ $page->title }}</h1>
        <div class="prose">{!! $page->body !!}</div>
    </article>
@endsection
