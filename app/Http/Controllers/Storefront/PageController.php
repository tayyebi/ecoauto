<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

final class PageController extends Controller
{
    public function about(): View
    {
        return $this->render('about', 'درباره ما');
    }

    public function contact(): View
    {
        return $this->render('contact', 'تماس با ما');
    }

    public function show(string $slug): View
    {
        return $this->render($slug, $slug);
    }

    private function render(string $slug, string $fallbackTitle): View
    {
        $page = Page::query()->published()->where('slug', $slug)->first();

        abort_if($page === null, 404);

        return view('storefront.pages.show', [
            'meta' => [
                'title' => $page->meta_title ?: $page->title,
                'description' => $page->meta_description ?: $page->description,
                'canonical' => '/'.$slug.'/',
            ],
            'page' => $page,
            'fallbackTitle' => $fallbackTitle,
        ]);
    }
}
