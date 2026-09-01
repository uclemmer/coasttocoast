{{--
    A call to action (doc 07 §1), restyled onto the design handoff's
    `email-template.html` (docs/16).

    A table wrapping an anchor, not a styled <a> alone: Outlook renders through
    Word, which ignores padding on inline elements, so a plain button collapses
    to a bare link there. The table is what makes it a button everywhere.

    `display:block` on the anchor rather than `inline-block`, because the cell
    is already sized by the padding and Word measures the two differently.
--}}
@props(['url', 'color' => null])
@php $color ??= config('fair.brand.color_primary', '#188042'); @endphp

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;">
    <tr>
        <td bgcolor="{{ $color }}" style="background-color:{{ $color }}; border-radius:6px;">
            <a href="{{ $url }}"
               style="display:block; padding:14px 32px; color:#ffffff; text-decoration:none;
                      font-family:Arial, Helvetica, sans-serif; font-weight:bold; font-size:14px;
                      line-height:18px; mso-line-height-rule:exactly; letter-spacing:1px;
                      text-transform:uppercase;">{{ $slot }}</a>
        </td>
    </tr>
</table>
