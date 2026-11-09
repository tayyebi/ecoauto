<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->title,
    'description' => $product->description,
    'sku' => $product->sku,
    'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand] : null,
    'image' => $product->imageUrl() ? url($product->imageUrl()) : null,
    'offers' => [
        '@type' => 'Offer',
        'url' => url($product->url()),
        'priceCurrency' => $product->currency,
        'price' => $product->hasPrice() ? (string) $product->price : null,
        'availability' => $product->hasPrice() ? 'https://schema.org/InStock' : 'https://schema.org/PreOrder',
    ],
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
