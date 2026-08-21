@extends('core::auth.layout')

@section('title', __('Two-factor authentication'))

@section('content')
    @if (!$pending && !$confirmed)
        <p>{{ __('Two-factor authentication is not enabled on your account.') }}</p>

        <form method="POST" action="{{ route('core.two-factor.enable') }}">
            @csrf
            <button type="submit">{{ __('Enable two-factor authentication') }}</button>
        </form>
    @else
        @if ($confirmed)
            <p>{{ __('Two-factor authentication is enabled.') }}</p>
        @else
            <p>{{ __('Add this to your authenticator app, then enter a code below to finish.') }}</p>
        @endif

        {{-- Enrolment data, and only while enrolment is unfinished — the
             controller passes null once confirmed. The SVG appears only when
             bacon/bacon-qr-code is installed; the otpauth:// URI is always
             available for manual entry or your own renderer. --}}
        @if ($qrCodeSvg)
            <div>{!! $qrCodeSvg !!}</div>
        @endif

        @if ($qrCodeUri)
            <p><code>{{ $qrCodeUri }}</code></p>
        @endif

        @unless ($confirmed)
            <form method="POST" action="{{ route('core.two-factor.confirm') }}">
                @csrf

                <label for="code">{{ __('Authentication code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" required
                    autofocus>

                <button type="submit">{{ __('Confirm') }}</button>
            </form>
        @endunless

        {{-- Shown in the response that generated them and not afterwards, so
             a later visit to this page cannot hand somebody a working set. The
             controller flashes them; see its RECOVERY_CODES_KEY. --}}
        @if ($recoveryCodes !== [])
            <h2>{{ __('Recovery codes') }}</h2>
            <p>{{ __('Store these somewhere safe now — they are not shown again. Each one works once if you lose your device.') }}
            </p>
            <ul>
                @foreach ($recoveryCodes as $recoveryCode)
                    <li><code>{{ $recoveryCode }}</code></li>
                @endforeach
            </ul>
        @endif

        @if ($confirmed)
            <form method="POST" action="{{ route('core.two-factor.recovery-codes') }}">
                @csrf
                <button type="submit">{{ __('Regenerate recovery codes') }}</button>
            </form>
        @endif

        {{-- The same route serves two different situations, so it must not
             claim the same thing in both. Mid-enrolment nothing is enabled
             yet, and a button offering to "disable two-factor authentication"
             describes a state the account is not in.

             The form still renders while pending, deliberately: abandoning a
             half-finished enrolment is a real need, and without it the pending
             secret survives every revisit with no way to clear it. --}}
        <form method="POST" action="{{ route('core.two-factor.disable') }}">
            @csrf
            @method('DELETE')
            <button type="submit">
                {{ $confirmed ? __('Disable two-factor authentication') : __('Cancel setup') }}
            </button>
        </form>
    @endif
@endsection
