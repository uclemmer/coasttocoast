{{--
    "Check your email" — the page a newly registered rep lands on when the
    `verified` middleware turns them away (docs/12).

    Uses the shared auth layout, so it matches log in and sign up rather than
    looking like a stray page.
--}}
<x-layouts.auth :title="__('Confirm your email address')">
    <p class="text-sm text-ink-600">
        {{ __('We have sent a link to :email. Open it and your account is ready.', ['email' => auth()->user()->email]) }}
    </p>

    <p class="mt-3 text-sm text-ink-600">
        {{ __('Nothing arrived? It can take a minute, and it is worth checking your spam folder — institutional mail systems are strict.') }}
    </p>

    <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
        @csrf
        <x-ui::button type="submit" class="w-full">{{ __('Send it again') }}</x-ui::button>
    </form>

    <form method="POST" action="{{ route('core.logout') }}" class="mt-3">
        @csrf
        <x-ui::button type="submit" variant="secondary" class="w-full">{{ __('Log out') }}</x-ui::button>
    </form>
</x-layouts.auth>
