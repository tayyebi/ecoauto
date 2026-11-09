@extends('layouts.storefront')

@section('content')
    <header class="page-header">
        <h1>وبلاگ</h1>
        <p>مقالات و اخبار دنیای خودروهای برقی</p>
    </header>

    <div class="list">
        @foreach ($posts as $post)
            @include('storefront.blog.row', ['post' => $post])
        @endforeach
    </div>

    {{ $posts->links() }}
@endsection
