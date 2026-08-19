{{--
    The printable receipt (card 3.3), rendered by dompdf.

    Table-based layout with inline styles on purpose: dompdf supports a narrow
    slice of CSS and no flexbox or grid at all, so this is written the way an
    HTML email is. It is not an exception to the Filament-only UI directive —
    a PDF has no Filament to render it.

    Every figure comes from the registration's own snapshot. Nothing here
    recomputes a price.
--}}
@php
    use App\Support\Money;
    use App\Support\Phone;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt — {{ $registration->event?->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        .lines td { padding: 6px 0; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .lines td.label { width: 40%; color: #6b7280; }
        .total td { padding: 10px 0; font-size: 15px; font-weight: bold; }
        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ $registration->event?->name }}</h1>
    <p class="muted">Registration receipt</p>

    <table class="lines">
        <tr>
            <td class="label">Institution</td>
            <td>
                {{ $registration->organization?->name }}
                @if ($address = $registration->organization?->formattedAddress())
                    <br><span class="muted">{!! nl2br(e($address)) !!}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Representative</td>
            <td>
                {{ $registration->rep_name }}<br>
                <span class="muted">{{ $registration->rep_email }}</span>
                @if ($registration->rep_phone)
                    <br><span class="muted">{{ Phone::forHumans($registration->rep_phone) }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Fair date</td>
            <td>{{ $registration->event?->starts_at?->format('l, F j, Y') }}</td>
        </tr>
        <tr>
            <td class="label">Venue</td>
            <td>
                {{ $registration->event?->venue_name }}<br>
                <span class="muted">{!! nl2br(e($registration->event?->venue_address ?? '')) !!}</span>
            </td>
        </tr>
        <tr>
            <td class="label">Registration fee</td>
            <td>{{ Money::format($registration->event?->price_cents) }}</td>
        </tr>

        {{-- Shown only when a grant actually moved the price, so a full-price
             receipt does not carry a confusing empty discount line. --}}
        @if ($registration->grant)
            <tr>
                <td class="label">Fee assistance applied</td>
                <td>{{ $registration->grant->benefitSummary() }}</td>
            </tr>
        @endif

        <tr>
            <td class="label">Payment method</td>
            <td>
                {{ $registration->payment_method?->getLabel() ?? 'Covered in full by a fee assistance grant' }}
                @if ($payment?->check_number)
                    <br><span class="muted">Check #{{ $payment->check_number }},
                        received {{ $payment->check_received_on?->format('F j, Y') }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Confirmed</td>
            <td>{{ $registration->confirmed_at?->format('F j, Y') }}</td>
        </tr>
        <tr>
            <td class="label">Receipt number</td>
            <td>{{ str_pad((string) $registration->getKey(), 6, '0', STR_PAD_LEFT) }}</td>
        </tr>
    </table>

    <table class="total">
        <tr>
            <td>Total paid</td>
            <td style="text-align: right;">{{ Money::format($registration->price_cents) }}</td>
        </tr>
    </table>

    <div class="footer">
        {{ config('app.name') }}<br>
        {{ $fair['name'] ?? '' }} ·
        {{ $fair['address_line1'] ?? '' }},
        {{ $fair['city'] ?? '' }}, {{ $fair['state'] ?? '' }} {{ $fair['postal_code'] ?? '' }}<br>
        {{ $fair['email'] ?? '' }} · {{ $fair['phone'] ?? '' }}
    </div>
</body>
</html>
