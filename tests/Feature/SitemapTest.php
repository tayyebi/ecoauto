<?php

declare(strict_types=1);

namespace Tests\Feature;

use DOMDocument;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class SitemapTest extends TestCase
{
    public function test_the_xml_sitemap_renders_a_document_a_strict_parser_accepts(): void
    {
        $xml = view('storefront.sitemap-xml', [
            'urls' => collect([
                ['loc' => '/', 'priority' => '1.0'],
                ['loc' => '/products/', 'priority' => '0.9'],
            ]),
        ])->render();

        // DECISION (data): the declaration has to be the first bytes of the
        // response. A declaration that is preceded by whitespace - or by the
        // leaked source of the template that was supposed to emit it - is not a
        // declaration at all, and strict parsers reject the whole document.
        $this->assertStringStartsWith(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n",
            $xml,
            'the sitemap must open with the XML declaration and nothing before it',
        );

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertTrue($loaded, 'the rendered sitemap is not well-formed XML');
        $this->assertSame('urlset', $document->documentElement->nodeName);
        $this->assertSame(
            'http://www.sitemaps.org/schemas/sitemap/0.9',
            $document->documentElement->namespaceURI,
        );

        $locations = [];

        foreach ($document->getElementsByTagName('loc') as $loc) {
            $locations[] = $loc->textContent;
        }

        $this->assertSame([url('/'), url('/products/')], $locations);
    }

    public function test_no_template_leaves_a_short_open_tag_for_the_blade_lexer(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = File::get($file->getRealPath());

            // Strip exactly what Blade removes before it reaches the lexer:
            // comments first, then the blocks it lifts out and restores
            // afterwards. What is left is what token_get_all() sees.
            $source = (string) preg_replace('/\{\{--(.*?)--\}\}/s', '', $source);
            $source = (string) preg_replace('/(?<!@)@php(.*?)@endphp/s', '', $source);
            $source = (string) preg_replace('/(?<!@)@verbatim(.*?)@endverbatim/s', '', $source);

            if (preg_match('/<\?(?!php\b|=)/', $source)) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        // DECISION (data): Blade compiles a template by running PHP's own lexer
        // over it and compiling only the inline-HTML chunks. `<?xml` is not
        // special to that lexer - with short_open_tag on it is a short open
        // tag, so the {!! !!} around it is never compiled and the raw Blade
        // source is written into the compiled template, which is then a parse
        // error. This is asserted over the source rather than over a rendered
        // response because short_open_tag is PHP_INI_SYSTEM: a host that runs
        // the tests with it off would otherwise pass no matter what the
        // templates contain.
        $this->assertSame([], $offenders, implode("\n", [
            'These templates leave a `<?` that is not a PHP open tag for the Blade lexer.',
            'Emit it from a Blade PHP block instead, which Blade lifts out before it lexes.',
        ]));
    }
}
