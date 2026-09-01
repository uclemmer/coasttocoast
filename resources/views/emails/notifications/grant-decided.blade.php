<x-emails::layout :title="__('Fee assistance')"
                 :preview="$approved
                    ? __('Good news about your fee assistance request.')
                    : __('About your fee assistance request.')">

    <p>{{ __('Hello,') }}</p>

    @if ($approved)
        {{-- Mirrors the portal's status line word for word (doc 01 Appendix A),
             so an organization reading both does not find them disagreeing. --}}
        <p>{{ __('Good news — your registration fee for :event is :benefit. The discount is applied automatically when you register.', [
            'event' => $grant->event?->name,
            'benefit' => $grant->benefitSummary(),
        ]) }}</p>

        <x-emails::button :url="url('/')">
            {{ __('Register now') }}
        </x-emails.components.button>
    @else
        <p>{{ __("We weren't able to approve fee assistance for :event this year.", [
            'event' => $grant->event?->name,
        ]) }}</p>

        {{-- Always included. "Denied", with nothing else, is how an organization is
             lost for good. --}}
        @if (filled($grant->denial_reason))
            <p>{{ $grant->denial_reason }}</p>
        @endif

        <p>{{ __('Standard registration is still open, and we would be glad to have :organization there.', [
            'organization' => $grant->organization?->name,
        ]) }}</p>
    @endif
</x-emails::layout>
