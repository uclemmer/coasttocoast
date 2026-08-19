{{--
    The mail-in registration form (card 4.2), rendered by dompdf.

    Same constraints as the receipt: tables and inline styles, because dompdf
    has no flexbox or grid. Reads the registration's snapshot, so a school with
    a grant is asked for what it actually owes rather than the list price — a
    full-price check from a half-price school means a refund nobody wanted.
--}}
@php
    use App\Support\Money;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Registration form — {{ $registration->event?->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 22px 0 6px; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        .lines td { padding: 5px 0; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .lines td.label { width: 38%; color: #6b7280; }
        .amount { margin-top: 14px; padding: 10px 12px; border: 2px solid #1f2937; }
        .amount .figure { font-size: 20px; font-weight: bold; }
        .instructions li { margin-bottom: 5px; }
        .footer { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ $registration->event?->name }}</h1>
    <p class="muted">Mail-in registration form</p>

    <h2>Your registration</h2>
    <table class="lines">
        <tr>
            <td class="label">Registration number</td>
            {{-- Quoted on the check, because a check carrying only a school
                 name is how a payment ends up unmatched in a drawer. --}}
            <td><strong>{{ str_pad((string) $registration->getKey(), 6, '0', STR_PAD_LEFT) }}</strong></td>
        </tr>
        <tr>
            <td class="label">Institution</td>
            <td>{{ $registration->organization?->name }}</td>
        </tr>
        <tr>
            <td class="label">Representative</td>
            <td>{{ $registration->rep_name }} · <span class="muted">{{ $registration->rep_email }}</span></td>
        </tr>
        <tr>
            <td class="label">Fair date</td>
            <td>{{ $registration->event?->starts_at?->format('l, F j, Y') }}</td>
        </tr>
        <tr>
            <td class="label">Venue</td>
            <td>{!! nl2br(e($registration->event?->venue_address ?? '')) !!}</td>
        </tr>
        @if ($registration->grant)
            <tr>
                <td class="label">Fee assistance applied</td>
                <td>{{ $registration->grant->benefitSummary() }}
                    <span class="muted">(list price {{ Money::format($registration->event?->price_cents) }})</span></td>
            </tr>
        @endif
    </table>

    <div class="amount">
        Amount due <span class="figure">{{ Money::format($registration->price_cents) }}</span>
    </div>

    <h2>How to pay</h2>
    <ol class="instructions">
        <li>Make the check payable to <strong>Coast to Coast College Fair</strong>.</li>
        <li>Write registration number
            <strong>{{ str_pad((string) $registration->getKey(), 6, '0', STR_PAD_LEFT) }}</strong>
            on the memo line.</li>
        <li>Mail this form with the check to:
            <div style="margin: 6px 0 0 14px;">
                {{ $fair['name'] ?? '' }}<br>
                {{ $fair['address_line1'] ?? '' }}<br>
                @if (! empty($fair['address_line2'])){{ $fair['address_line2'] }}<br>@endif
                {{ $fair['city'] ?? '' }}, {{ $fair['state'] ?? '' }} {{ $fair['postal_code'] ?? '' }}
            </div>
        </li>
    </ol>

    <p class="muted">
        Your place at the fair is already held. It is confirmed, and a receipt sent, once the check
        arrives. Questions: {{ $fair['email'] ?? '' }} · {{ $fair['phone'] ?? '' }}
    </p>

    <div class="footer">{{ config('app.name') }}</div>
</body>
</html>
