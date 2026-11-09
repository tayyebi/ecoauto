<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BlogController extends Controller
{
    public function index(Request $request): View
    {
        // `/blog/` is page one and `/blog/page/2/` is the rest, which keeps the
        // first page canonical instead of duplicating it at two URLs.
        $page = (int) $request->route('page', 1);

        $posts = Post::query()
            ->published()
            ->with('categories')
            ->latestFirst()
            ->paginate(config('store.per_page.blog'), pageName: 'page', page: $page)
            ->withQueryString();

        return view('storefront.blog.index', [
            'meta' => ['title' => 'وبلاگ'],
            'posts' => $posts,
        ]);
    }

    public function show(string $post): View
    {
        $model = Post::query()
            ->published()
            ->with('categories')
            ->where('slug', $post)
            ->firstOrFail();

        return view('storefront.blog.show', [
            'meta' => [
                'title' => $model->title,
                'description' => $model->description,
                'canonical' => $model->url(),
            ],
            'post' => $model,
            'related' => Post::query()
                ->published()
                ->whereKeyNot($model->getKey())
                ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', $model->categories->pluck('id')))
                ->latestFirst()
                ->limit(5)
                ->get(),
        ]);
    }
}
