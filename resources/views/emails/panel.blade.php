{{--
    The details block a receipt or a confirmation hangs its facts on
    (doc 07 §1) — the design handoff's "At a glance" section (docs/16).

    Pass an associative array of label => value; blank values are dropped, so a
    registration with no phone number does not leave an empty row.

    A rule and a heading rather than a bordered grey box, which is what the
    handoff draws. In an inbox the message is already a card; a second card
    inside it reads as a quotation from somewhere else.

    The label column is a fixed 90px because the values wrap and the labels do
    not — left to itself the table gives the widest label the width, and a long
    venue name then squeezes it onto two lines.
--}}
@props(['rows' => [], 'heading' => null])

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;">
    <tr>
        <td height="1" style="height:1px; line-height:1px; font-size:1px;
                              background-color:#dde6df;">&nbsp;</td>
    </tr>
    <tr>
        <td style="padding-top:24px;">
            <div style="font-family:Arial, Helvetica, sans-serif; font-weight:bold; font-size:13px;
                        line-height:18px; mso-line-height-rule:exactly; letter-spacing:1.5px;
                        text-transform:uppercase; color:#22302a;
                        padding-bottom:14px;">{{ $heading ?? __('At a glance') }}</div>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                @foreach ($rows as $label => $value)
                    @continue(blank($value))
                    <tr>
                        <td width="90" valign="top"
                            style="font-family:Arial, Helvetica, sans-serif; font-weight:bold; font-size:14px;
                                   line-height:24px; mso-line-height-rule:exactly;
                                   color:{{ config('fair.brand.color_primary', '#188042') }};">{{ $label }}</td>
                        <td valign="top"
                            style="font-family:Georgia, 'Times New Roman', serif; font-size:14px;
                                   line-height:24px; mso-line-height-rule:exactly;
                                   color:#3d4a43;">{!! nl2br(e($value)) !!}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
