@extends('core::auth.layout')

@section('title', __('Choose a new password'))

@section('content')
    {{--
        PUBLISHED AND OWNED (docs/12) — restyled onto uclemmer/laravel-ui.

        The email field is deliberately still editable rather than readonly:
        that is how the package ships it, the token is what actually
        authorises the reset, and a readonly field that disagrees with the
        token fails confusingly.
    --}}
    <form method="POST" action="{{ route('core.password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-ui::forms.input name="email" type="email" label="{{ __('Email') }}" :value="old('email', $email)"
            required autocomplete="username" />

        <x-ui::forms.input name="password" type="password" label="{{ __('New password') }}" required autofocus
            autocomplete="new-password" />

        <x-ui::forms.input name="password_confirmation" type="password" label="{{ __('Confirm password') }}"
            required autocomplete="new-password" />

        <x-ui::button type="submit" class="w-full">{{ __('Reset password') }}</x-ui::button>
    </form>
@endsection
