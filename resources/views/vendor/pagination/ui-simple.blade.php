{{--
    Simple paginator (previous/next only), for `simplePaginate()`. Publishable,
    not a component — see ui.blade.php in this directory for the reasoning and
    the wiring.

        Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.ui-simple');

    Class strings are in this package's own token vocabulary, defined by
    resources/css/theme.css — see docs/01-component-conventions.md.
--}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('Pagination Navigation') }}">
        <ul class="inline-flex -space-x-px text-sm">
            @if ($paginator->onFirstPage())
                <li>
                    <span aria-disabled="true"
                        class="inline-flex items-center text-fg-disabled bg-disabled border border-default-medium shadow-xs font-medium leading-5 rounded-s-base text-sm px-3 py-2 cursor-not-allowed">
                        {!! __('pagination.previous') !!}
                    </span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                        class="inline-flex items-center text-body bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading shadow-xs font-medium leading-5 rounded-s-base text-sm px-3 py-2 focus:outline-none">
                        {!! __('pagination.previous') !!}
                    </a>
                </li>
            @endif

            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                        class="inline-flex items-center text-body bg-neutral-secondary-medium border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading shadow-xs font-medium leading-5 rounded-e-base text-sm px-3 py-2 focus:outline-none">
                        {!! __('pagination.next') !!}
                    </a>
                </li>
            @else
                <li>
                    <span aria-disabled="true"
                        class="inline-flex items-center text-fg-disabled bg-disabled border border-default-medium shadow-xs font-medium leading-5 rounded-e-base text-sm px-3 py-2 cursor-not-allowed">
                        {!! __('pagination.next') !!}
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
