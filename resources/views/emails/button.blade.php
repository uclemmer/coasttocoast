{{--
    A call to action (doc 07 §1).

    A table wrapping an anchor, not a styled <a> alone: Outlook renders through
    Word, which ignores padding on inline elements, so a plain button collapses
    to a bare link there. The table is what makes it a button everywhere.
--}}
@props(['url', 'color' => null])
@php $color ??= config('fair.brand.color_primary', '#1d4ed8'); @endphp

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;">
    <tr>
        <td style="background-color:{{ $color }}; border-radius:4px;">
            <a href="{{ $url }}"
               style="display:inline-block; padding:11px 22px; color:#ffffff; text-decoration:none;
                      font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                      font-size:15px; font-weight:600;">{{ $slot }}</a>
        </td>
    </tr>
</table>
