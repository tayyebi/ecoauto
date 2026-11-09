@if ($paginator->hasPages())
    <nav class="pagination" aria-label="صفحه‌بندی">
        @if ($paginator->onFirstPage())
            <span class="pagination-link is-disabled">صفحه قبل</span>
        @else
            <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">صفحه قبل</a>
        @endif

        <span class="pagination-info">
            صفحه {{ fa_number($paginator->currentPage()) }} از {{ fa_number($paginator->lastPage()) }}
        </span>

        @if ($paginator->hasMorePages())
            <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next">صفحه بعد</a>
        @else
            <span class="pagination-link is-disabled">صفحه بعد</span>
        @endif
    </nav>
@endif
