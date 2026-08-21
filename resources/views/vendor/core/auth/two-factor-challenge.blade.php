@extends('core::auth.layout')

@section('title', $recovery ? __('Use a recovery code') : __('Two-factor authentication'))

@section('content')
    {{--
        Two modes, never both at once. The authenticator code is the routine
        path; a recovery code is what you reach for when the device is gone.

        Offering both fields together made every ordinary sign-in look at a
        single-use secret it had no business touching, and invited filling in
        both when only one can be spent. See TwoFactorChallengeController::create.
    --}}
    @if ($recovery)
        <p>{{ __('Enter one of the recovery codes you saved when you turned on two-factor authentication. Each code works once.') }}
        </p>

        <form method="POST" action="{{ route('core.two-factor.challenge.attempt') }}">
            @csrf

            <label for="recovery_code">{{ __('Recovery code') }}</label>
            <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code" required autofocus>

            <button type="submit">{{ __('Continue') }}</button>
        </form>

        <a href="{{ route('core.two-factor.challenge') }}">{{ __('Use your authenticator app instead') }}</a>
    @else
        <p>{{ __('Enter the code from your authenticator app.') }}</p>

        <form method="POST" action="{{ route('core.two-factor.challenge.attempt') }}">
            @csrf

            <label for="code">{{ __('Authentication code') }}</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required
                autofocus>

            <button type="submit">{{ __('Continue') }}</button>
        </form>

        <a
            href="{{ route('core.two-factor.challenge', ['recovery' => 1]) }}">{{ __('Lost your device? Use a recovery code') }}</a>
    @endif

    <a href="{{ route('core.login') }}">{{ __('Back to log in') }}</a>
@endsection
