{{--
    An internal alert (doc 07 §2). No eyebrow and no CAN-SPAM line: the
    recipient is the coordinator, not a mailing list.

    `$headline` is passed once, as the layout's title. It used to be repeated
    as a bold first paragraph, which was right when the layout had no visible
    headline of its own and is a duplicate now that it does (docs/16).
--}}
<x-emails::layout :title="$headline" :preview="$headline">
    <x-emails::panel :rows="$rows" :heading="__('Details')" />

    @if ($url)
        <x-emails::button :url="$url">
            {{ $linkLabel ?? __('Open in the admin panel') }}
        </x-emails::button>
    @endif
</x-emails::layout>
