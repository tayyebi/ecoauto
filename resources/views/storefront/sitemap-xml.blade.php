@php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; @endphp
{{-- DECISION (data): the XML declaration is emitted from Blade's PHP block, not
     from a `{!! '<?xml ...' !!}` echo. Blade compiles a template by running
     PHP's own lexer over it, and only the inline-HTML chunks are compiled. With
     short_open_tag on - PHP's compiled default, which the image's php.ini
     overrides but a bare `php artisan serve` does not - `<?xml` is a short open
     tag: the opening `{!!` and its closing `!!}` then land in different chunks,
     the echo is never compiled at all, and the raw Blade source is written
     into the compiled template. Requiring that file is a parse error, which is
     the 500 this route returned. Blade lifts a PHP block out before the lexer
     runs and restores it afterwards, so the `<?xml` inside it is never lexed
     and this view compiles the same with short_open_tag either way. The
     declaration holds no user data, so echoing it unescaped is safe.

     Nothing may be printed before the declaration: an XML declaration preceded
     by whitespace is not a declaration at all, and strict parsers reject the
     document. PHP's closing tag swallows exactly one newline, so the line break
     the document needs is echoed here rather than left in the template. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $entry)
    <url>
        <loc>{{ url($entry['loc']) }}</loc>
        <priority>{{ $entry['priority'] }}</priority>
    </url>
@endforeach
</urlset>
