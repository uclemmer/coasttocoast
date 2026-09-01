<x-emails::layout :title="__('Your account')"
                 :preview="$approved
                    ? __('Your account is ready.')
                    : __('About your request.')">

    @if ($approved)
        <p>{{ __('You are confirmed as a representative of :organization.', [
            'organization' => $organization?->name,
        ]) }}</p>

        <p>{{ __('You can now register it for a fair, apply for fee assistance, and keep its details up to date.') }}</p>

        <x-emails::button :url="url('/portal')">
            {{ __('Open the portal') }}
        </x-emails.components.button>
    @else
        <p>{{ __('We were not able to confirm you as a representative of :organization.', [
            'organization' => $organization?->name,
        ]) }}</p>

        @if (filled($reason))
            <p>{{ $reason }}</p>
        @endif

        {{-- The realistic denial is a typo between two similarly named
             institutions, so the way forward matters more than the refusal. --}}
        <p>{{ __('Your account is still active. If you meant a different institution, sign in and request that one instead, or reply to this email and we will sort it out.') }}</p>

        <x-emails::button :url="url('/portal')">
            {{ __('Open the portal') }}
        </x-emails.components.button>
    @endif
</x-emails::layout>
