<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $entry)
    <url>
        <loc>{{ url($entry['loc']) }}</loc>
        <priority>{{ $entry['priority'] }}</priority>
    </url>
@endforeach
</urlset>
