@if ($paginator->hasPages() || $paginator->total() > 0)
    <div class="pagination">
        <span class="muted small">
            @if ($paginator->total() > 0)
                Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
            @endif
        </span>
        @if ($paginator->hasPages())
            <div class="pages">
                @if ($paginator->onFirstPage())
                    <span class="gap disabled">‹ Prev</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Prev</a>
                @endif
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="gap">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="cur">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>
                @else
                    <span class="gap disabled">Next ›</span>
                @endif
            </div>
        @endif
    </div>
@endif
