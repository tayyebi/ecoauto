@php
    $current = account();
    $name = app(\App\Services\Storefront\TenantContent::class)->siteTitle();
    $url = url('/');
@endphp
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $name,
    'url' => $url,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
</script>
