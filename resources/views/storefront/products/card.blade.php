<article class="card product-card">
    <a class="card-link" href="{{ $product->url() }}">
        @if ($image = $product->imageUrl())
            <img class="card-image" src="{{ $image }}" alt="{{ $product->title }}" loading="lazy">
        @else
            <div class="card-image placeholder">تصویر محصول</div>
        @endif
        <h3 class="card-title">{{ $product->title }}</h3>
        <p class="card-meta">{{ $product->description }}</p>
    </a>
    <div class="card-footer">
        <span class="price">{{ $product->priceLabel() ?? 'تماس بگیرید' }}</span>
        @if ($product->hasPrice())
            <form method="post" action="{{ route('cart.add', absolute: false) }}">
                @csrf
                <input type="hidden" name="product" value="{{ $product->slug }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="button button-small">افزودن به سبد خرید</button>
            </form>
        @else
            <a class="button button-small button-ghost" href="/contact/">استعلام قیمت</a>
        @endif
    </div>
</article>
