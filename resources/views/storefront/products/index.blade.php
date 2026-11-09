@extends('layouts.storefront')

@section('content')
    <header class="page-header">
        <h1>محصولات</h1>
        <p>تمامی محصولات قابل ارائه در این فروشگاه را در این صفحه مشاهده می‌کنید.</p>
    </header>

    <div class="grid grid-products">
        @foreach ($products as $product)
            @include('storefront.products.card', ['product' => $product])
        @endforeach
    </div>

    {{ $products->links() }}
@endsection
