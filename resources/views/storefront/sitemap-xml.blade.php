{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{{-- DECISION (data): the XML declaration is emitted with `{!! !!}`, never with
     a raw `<?php ... ?>` block at the top of the view. A Blade file that opens
     with `<?php ... ?>` breaks the compiler's token pass: the compiled template
     either leaks its source or dies on include, and anything printed during
     rendering lands in the output buffer while Response::send() is still trying
     to send headers - "Cannot modify header information: headers already sent".
     The declaration contains no user data, so unescaped output is safe here.

     The declaration must stay on the first line, before this comment: Blade
     strips the comment but keeps the newlines around it, and an XML
     declaration preceded by whitespace is not a declaration at all - strict
     parsers reject the document. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $entry)
    <url>
        <loc>{{ url($entry['loc']) }}</loc>
        <priority>{{ $entry['priority'] }}</priority>
    </url>
@endforeach
</urlset>
