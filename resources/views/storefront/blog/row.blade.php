<article class="list-row">
    <a class="list-row-link" href="{{ $post->url() }}">
        <div>
            <h3>{{ $post->title }}</h3>
            <p>{{ $post->description }}</p>
            <p class="meta">
                <time datetime="{{ $post->published_at?->toDateString() }}">{{ fa_date($post->published_at) }}</time>
                @foreach ($post->categories as $category)
                    <span>·</span><a href="{{ $category->url() }}">{{ $category->title }}</a>
                @endforeach
            </p>
        </div>
        <span class="list-row-arrow" aria-hidden="true">←</span>
    </a>
</article>
