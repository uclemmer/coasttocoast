{{--
    The one layout every email in this application renders through — receipts,
    check instructions, grant decisions, admin alerts and campaigns alike
    (doc 07 §1). A receipt and a reminder should look like the same
    organization sent them.

    Table-based, 600px, inline styles, absolute URLs. That is not laziness: an
    email client is not a browser. Outlook renders through Word, Gmail strips
    <style> blocks in some contexts, and a Vite-hashed asset path resolves to
    nothing at all outside the site. This is the one place in the app where
    hand-written HTML is unavoidable, and it is not an exception to the
    Filament-only directive — there is no Filament in an inbox.

    Slots:
      $slot        the message body
      $preview     one line shown in the inbox list before it is opened
      $campaign    true adds the CAN-SPAM explanation line to the footer
--}}
@php
    $brand = (array) config('fair.brand');
    $contact = (array) config('fair.contact');
    $color = $brand['color_primary'] ?? '#1d4ed8';
    $addressLine = trim(implode(' ', array_filter([
        $contact['address_line1'] ?? null,
        $contact['address_line2'] ?: null,
        ($contact['city'] ?? null) ? $contact['city'].',' : null,
        $contact['state'] ?? null,
        $contact['postal_code'] ?? null,
    ])));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5;">

    {{-- The inbox preview line. Hidden in the body, read by the client. --}}
    @isset($preview)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $preview }}</div>
    @endisset

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#f4f4f5;">
        <tr>
            <td align="center" style="padding:24px 12px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:600px; width:100%;">

                    <tr>
                        <td style="background-color:{{ $color }}; padding:20px 24px; border-radius:6px 6px 0 0;">
                            @if (! empty($brand['logo_url']))
                                {{-- Absolute, served from public/. A Vite asset path is not
                                     resolvable in a mail client. --}}
                                <img src="{{ $brand['logo_url'] }}" alt="{{ config('app.name') }}"
                                     height="36" style="display:block; border:0; height:36px;">
                            @else
                                <span style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                                             font-size:18px; font-weight:700; color:#ffffff;">
                                    {{ config('app.name') }}
                                </span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff; padding:28px 24px;
                                   font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                                   font-size:15px; line-height:1.6; color:#18181b;">
                            {{ $slot }}
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff; padding:0 24px 24px;
                                   border-radius:0 0 6px 6px;
                                   font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                                   font-size:12px; line-height:1.6; color:#71717a;">
                            <hr style="border:0; border-top:1px solid #e4e4e7; margin:0 0 16px;">

                            {{-- CAN-SPAM: campaigns must say why the recipient got this
                                 and carry a physical address. Transactional mail does not
                                 need the first, and gets the address anyway. --}}
                            @if (! empty($campaign))
                                <p style="margin:0 0 8px;">
                                    You are receiving this because your institution registered for a
                                    Coast to Coast College Fair.
                                </p>
                            @endif

                            <p style="margin:0;">
                                <strong>{{ config('app.name') }}</strong><br>
                                @if (! empty($contact['name'])){{ $contact['name'] }}<br>@endif
                                @if ($addressLine){{ $addressLine }}<br>@endif
                                @if (! empty($contact['phone'])){{ $contact['phone'] }} · @endif
                                @if (! empty($contact['email']))
                                    <a href="mailto:{{ $contact['email'] }}"
                                       style="color:{{ $color }};">{{ $contact['email'] }}</a>
                                @endif
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
