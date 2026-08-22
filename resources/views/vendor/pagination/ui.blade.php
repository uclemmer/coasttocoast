{{--
    Paginator view for Laravel's paginator, NOT a component.

    Class strings are in this package's own token vocabulary, defined by
    resources/css/theme.css — see docs/01-component-conventions.md. They were
    Flowbite's until the R2 rebuild re-expressed them in those tokens; the
    structure is unchanged, the vocabulary is not.

    This is a publishable view rather than an `<x-ui::pagination>` component
    because Laravel already has a first-class mechanism for exactly this, and a
    component would wrap it without improving it. Decided in
    docs/04-build-plan.md, phase 3.

        php artisan vendor:publish --tag=ui-pagination

    lands this in resources/views/vendor/pagination/. Then either point one
    paginator at it:

        {{ $posts->links('vendor.pagination.ui') }}

    or make it the default for the whole app, in a service provider's boot():

        Illuminate\Pagination\Paginator::defaultView('vendor.pagination.ui');
        Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.ui-simple');

    NOTE: once published, this file is the host's. It stops receiving package
    updates — which is the point of publishing, but worth knowing.

    Because it lives outside resources/views/, it is NOT in the `ui::` view
    namespace and cannot be rendered from this package. It also means the
    `@source` line a host adds for the package's components does not cover it —
    but it does not need to, because published views live under the host's own
    resources/views/, which Tailwind scans anyway.
--}}
@if ($paginator->hasPages())
    <nav aria-label="{{ __('Pagination Navigation') }}">
        <ul class="flex -space-x-px text-sm">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li>
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                        class="flex items-center justify-center text-fg-disabled bg-disabled box-border border border-default-medium font-medium rounded-s-base text-sm px-3 h-9 cursor-not-allowed">
                        {!! __('pagination.previous') !!}
                    </span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                        aria-label="{{ __('pagination.previous') }}"
                        class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading font-medium rounded-s-base text-sm px-3 h-9 focus:outline-none">
                        {!! __('pagination.previous') !!}
                    </a>
                </li>
            @endif

            {{-- Page numbers --}}
            @foreach ($elements as $element)
                {{-- The "..." separator --}}
                @if (is_string($element))
                    <li aria-disabled="true">
                        <span
                            class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium font-medium text-sm w-9 h-9">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li aria-current="page">
                                <a href="{{ $url }}"
                                    class="flex items-center justify-center text-fg-brand bg-neutral-tertiary-medium box-border border border-default-medium hover:text-fg-brand font-medium text-sm w-9 h-9 focus:outline-none">{{ $page }}</a>
                            </li>
                        @else
                            <li>
                                <a href="{{ $url }}"
                                    aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                                    class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading font-medium text-sm w-9 h-9 focus:outline-none">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"
                        class="flex items-center justify-center text-body bg-neutral-secondary-medium box-border border border-default-medium hover:bg-neutral-tertiary-medium hover:text-heading font-medium rounded-e-base text-sm px-3 h-9 focus:outline-none">
                        {!! __('pagination.next') !!}
                    </a>
                </li>
            @else
                <li>
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                        class="flex items-center justify-center text-fg-disabled bg-disabled box-border border border-default-medium font-medium rounded-e-base text-sm px-3 h-9 cursor-not-allowed">
                        {!! __('pagination.next') !!}
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
