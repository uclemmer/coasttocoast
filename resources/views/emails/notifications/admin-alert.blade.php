<x-emails::layout :title="$headline" :preview="$headline">
    <p><strong>{{ $headline }}</strong></p>

    <x-emails::panel :rows="$rows" />

    @if ($url)
        <x-emails::button :url="$url">
            {{ $linkLabel ?? __('Open in the admin panel') }}
        </x-emails.components.button>
    @endif
</x-emails::layout>
