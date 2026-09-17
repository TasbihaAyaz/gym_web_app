@if($paginator->hasPages())
<div class="pagination-wrap">
    <div class="text-mute">
        Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
    </div>
    <ul class="pagination">
        @if($paginator->onFirstPage())
            <li class="disabled"><span>‹</span></li>
        @else
            <li><a href="{{ $paginator->previousPageUrl() }}">‹</a></li>
        @endif

        @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            @if($page == $paginator->currentPage())
                <li class="active"><span>{{ $page }}</span></li>
            @else
                <li><a href="{{ $url }}">{{ $page }}</a></li>
            @endif
        @endforeach

        @if($paginator->hasMorePages())
            <li><a href="{{ $paginator->nextPageUrl() }}">›</a></li>
        @else
            <li class="disabled"><span>›</span></li>
        @endif
    </ul>
</div>
@endif
