@extends('core::auth.layout')

@section('title', __('Log in'))

@section('content')
    {{--
        PUBLISHED AND OWNED (docs/12) — restyled onto uclemmer/laravel-ui.
        The form's action, field names and the `remember` checkbox are the
        package's contract with its LoginController; only the markup changed.
    --}}
    <form method="POST" action="{{ route('core.login.attempt') }}" class="space-y-4">
        @csrf

        <x-ui::forms.input name="email" type="email" label="{{ __('Email') }}" :value="old('email')" required autofocus
            autocomplete="username" />

        <x-ui::forms.input name="password" type="password" label="{{ __('Password') }}" required
            autocomplete="current-password" />

        <div class="flex items-center justify-between gap-3">
            <x-ui::forms.checkbox name="remember" value="1" label="{{ __('Remember me') }}" />

            <a href="{{ route('core.password.request') }}" class="text-sm text-fg-brand hover:underline">
                {{ __('Forgot your password?') }}
            </a>
        </div>

        <x-ui::button type="submit" class="w-full">{{ __('Log in') }}</x-ui::button>
    </form>

    {{--
        Registration is this application's, not the package's: signing up
        claims or creates an organization and that decides whether the account is
        active immediately (D9). So this links at the app's own route rather
        than `core.register`, which stays disabled.
    --}}
    @if (Route::has('register'))
        <p class="mt-6 border-t border-default pt-6 text-center text-sm text-ink-600">
            {{ __('Representing an organization for the first time?') }}
            <a href="{{ route('register') }}" class="font-medium text-fg-brand hover:underline">
                {{ __('Create an account') }}
            </a>
        </p>
    @endif
@endsection
