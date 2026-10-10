<nav class="waybill-pagination" role="navigation" aria-label="Waybill pagination">
    @if($paginator->onFirstPage())
        <span class="waybill-page-direction is-disabled" aria-disabled="true">Previous</span>
    @else
        <a class="waybill-page-direction" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
    @endif

    <form class="waybill-page-controls" method="GET" action="{{ request()->url() }}">
        @foreach(request()->except(['page', 'per_page']) as $name => $value)
            @if(is_scalar($value) && $value !== '')
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @endforeach

        <label class="waybill-page-number">
            <span>Page</span>
            <input type="number" name="page" value="{{ $paginator->currentPage() }}" min="1" max="{{ max(1, $paginator->lastPage()) }}" aria-label="Page number">
            <span>of {{ max(1, $paginator->lastPage()) }}</span>
        </label>

        <select name="per_page" aria-label="Rows per page" onchange="this.form.page.value=1; this.form.submit()">
            @foreach([10, 25, 50, 100] as $size)
                <option value="{{ $size }}" @selected($paginator->perPage() === $size)>{{ $size }} rows</option>
            @endforeach
        </select>
    </form>

    @if($paginator->hasMorePages())
        <a class="waybill-page-direction" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
    @else
        <span class="waybill-page-direction is-disabled" aria-disabled="true">Next</span>
    @endif
</nav>
