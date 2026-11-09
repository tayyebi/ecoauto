@extends('layouts.storefront')

@section('content')
    <header class="page-header">
        <h1>تامین‌کنندگان</h1>
        <p>لیست تامین‌کنندگان و تولیدکنندگان قطعات خودرو</p>
    </header>

    <div class="grid grid-vendors">
        @foreach ($vendors as $vendor)
            <a class="card" href="{{ $vendor->url() }}">
                <span class="card-title">{{ $vendor->title }}</span>
                <span class="card-meta">{{ $vendor->description }}</span>
                <span class="card-count">
                    @if ($vendor->phone)<span dir="ltr">{{ $vendor->phone }}</span>@endif
                </span>
            </a>
        @endforeach
    </div>

    {{ $vendors->links() }}
@endsection
