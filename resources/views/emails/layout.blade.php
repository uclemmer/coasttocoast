{{--
    The one layout every email in this application renders through — receipts,
    check instructions, grant decisions, admin alerts and campaigns alike
    (doc 07 §1). A receipt and a reminder should look like the same
    organization sent them.

    Restyled 2026-09-01 onto the design handoff's `email-template.html`
    (docs/16): green header bar with the standing wordmark slot, an eyebrow /
    headline / body card, and a footer that carries the postal address.

    Table-based, 600px, inline styles, absolute URLs. That is not laziness: an
    email client is not a browser. Outlook renders through Word, Gmail strips
    <style> blocks in some contexts, and a Vite-hashed asset path resolves to
    nothing at all outside the site. This is the one place in the app where
    hand-written HTML is unavoidable.

    ARIAL AND GEORGIA, NOT THE SITE'S THREE FAMILIES. Montserrat, Caveat and
    Source Sans 3 are self-hosted for the web pages; a web font in an inbox is
    unreliable at best and stripped at worst, so the handoff specifies
    email-safe stacks instead and this is the one surface where the brand
    typography does not follow. The colours still do.

    THE ONE <style> BLOCK IS A PROGRESSIVE ENHANCEMENT, not a dependency. It
    only narrows the 600px table on a phone. A client that drops it renders the
    fixed-width desktop layout, which is the ordinary outcome and not a broken
    one — every rule that matters is inline.

    Slots and props:
      $slot        the message body
      $title       the <title>, and the headline unless $heading overrides it
      $heading     the visible headline, when it should differ from the title
      $eyebrow     the small green line above the headline — the event and
                   venue, where the message has one in hand
      $preview     one line shown in the inbox list before it is opened
      $campaign    true adds the CAN-SPAM explanation line to the footer
--}}
@props([
    'title' => null,
    'heading' => null,
    'eyebrow' => null,
    'preview' => null,
    'campaign' => false,
])
@php
    $brand = (array) config('fair.brand');
    $contact = (array) config('fair.contact');
    $color = $brand['color_primary'] ?? '#188042';
    $title ??= config('app.name');
    $heading ??= $title;
    $addressLine = trim(implode(' ', array_filter([
        $contact['address_line1'] ?? null,
        $contact['address_line2'] ?: null,
        ($contact['city'] ?? null) ? $contact['city'].',' : null,
        $contact['state'] ?? null,
        $contact['postal_code'] ?? null,
    ])));

    $sans = "Arial, Helvetica, sans-serif";
    $serif = "Georgia, 'Times New Roman', serif";
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title }}</title>
    {{-- Outlook renders through Word at 120dpi unless told otherwise, which
         scales every pixel dimension in the message by a quarter. --}}
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 24px !important; padding-right: 24px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#eef2ef;">

    {{-- The inbox preview line. Hidden in the body, read by the client. --}}
    @isset($preview)
        <span style="display:none; font-size:1px; color:#eef2ef; line-height:1px;
                     max-height:0; max-width:0; opacity:0; overflow:hidden;">{{ $preview }}</span>
    @endisset

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background-color:#eef2ef;">
        <tr>
            <td align="center" style="padding:24px 12px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                       class="container" style="width:600px; max-width:600px;">

                    {{-- Header bar --}}
                    <tr>
                        <td bgcolor="{{ $color }}" align="center" class="px"
                            style="background-color:{{ $color }}; padding:22px 40px; border-radius:8px 8px 0 0;">
                            @if (! empty($brand['logo_url']))
                                {{-- Absolute, served from public/. A Vite asset path is not
                                     resolvable in a mail client. --}}
                                <img src="{{ $brand['logo_url'] }}" alt="{{ config('app.name') }}"
                                     height="36" style="display:block; border:0; height:36px;">
                            @else
                                <div style="font-family:{{ $sans }}; font-weight:bold; font-size:20px;
                                            line-height:26px; mso-line-height-rule:exactly; color:#ffffff;
                                            letter-spacing:1px; text-transform:uppercase;">{{ config('app.name') }}</div>
                                <div style="font-family:{{ $serif }}; font-style:italic; font-size:14px;
                                            line-height:20px; mso-line-height-rule:exactly; color:#b8f0ca;
                                            padding-top:2px;">{{ __('Chattanooga, Tennessee') }}</div>
                            @endif
                        </td>
                    </tr>

                    {{-- Body card --}}
                    <tr>
                        <td bgcolor="#ffffff" class="px"
                            style="background-color:#ffffff; padding:40px 40px 36px; border-radius:0 0 8px 8px;">
                            @if ($eyebrow)
                                <div style="font-family:{{ $sans }}; font-weight:bold; font-size:12px;
                                            line-height:16px; mso-line-height-rule:exactly; letter-spacing:2px;
                                            text-transform:uppercase; color:{{ $color }};
                                            padding-bottom:10px;">{{ $eyebrow }}</div>
                            @endif

                            <div style="font-family:{{ $sans }}; font-weight:bold; font-size:28px;
                                        line-height:34px; mso-line-height-rule:exactly; color:#22302a;
                                        padding-bottom:16px;">{{ $heading }}</div>

                            {{-- The body inherits Georgia from here, so a plain <p>
                                 in a notification view needs no font of its own. --}}
                            <div style="font-family:{{ $serif }}; font-size:16px; line-height:26px;
                                        mso-line-height-rule:exactly; color:#3d4a43;">
                                {{ $slot }}
                            </div>
                        </td>
                    </tr>

                    {{-- Footer, on the page background rather than inside the card,
                         so the white block reads as the message and this reads as
                         the sender. --}}
                    <tr>
                        <td align="center" class="px"
                            style="padding:26px 40px;
                                   font-family:{{ $sans }}; font-size:12px; line-height:19px;
                                   mso-line-height-rule:exactly; color:#6b776f;">

                            {{-- CAN-SPAM: campaigns must say why the recipient got this
                                 and carry a physical address. Transactional mail does not
                                 need the first, and gets the address anyway. --}}
                            @if ($campaign)
                                <p style="margin:0 0 6px;">
                                    {{ __('You are receiving this because your institution registered for a Coast to Coast College Fair.') }}
                                </p>
                            @endif

                            <p style="margin:0;">
                                <strong style="color:#22302a;">{{ config('app.name') }}</strong><br>
                                @if (! empty($contact['name'])){{ $contact['name'] }}<br>@endif
                                @if ($addressLine){{ $addressLine }}<br>@endif
                                @if (! empty($contact['phone'])){{ $contact['phone'] }} &middot; @endif
                                @if (! empty($contact['email']))
                                    <a href="mailto:{{ $contact['email'] }}"
                                       style="color:{{ $color }}; text-decoration:underline;">{{ $contact['email'] }}</a>
                                @endif
                            </p>

                            {{-- The handoff's footer also carries "Unsubscribe" and
                                 "Email preferences" links. Neither route exists yet --
                                 one-click unsubscribe is uclemmer/laravel-postmaster's
                                 subscription feature, which has not landed. Linking them
                                 now would ship two dead links to every recipient of a
                                 campaign, which is worse than not offering them. Tracked
                                 in docs/16. --}}
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
