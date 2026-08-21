@extends('core::auth.layout')

@section('title', __('Reset your password'))

@section('content')
    {{-- PUBLISHED AND OWNED (docs/12) — restyled onto uclemmer/laravel-ui. --}}
    <p class="mb-4 text-sm text-ink-600">
        {{ __('Enter your email address and we will send you a password reset link.') }}
    </p>

    <form method="POST" action="{{ route('core.password.email') }}" class="space-y-4">
        @csrf

        <x-ui::forms.input name="email" type="email" label="{{ __('Email') }}" :value="old('email')" required
            autofocus autocomplete="username" />

        <x-ui::button type="submit" class="w-full">{{ __('Email password reset link') }}</x-ui::button>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('core.login') }}" class="text-fg-brand hover:underline">{{ __('Back to log in') }}</a>
    </p>
@endsection
