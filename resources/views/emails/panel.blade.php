{{--
    The details box a receipt or a confirmation hangs its facts on (doc 07 §1).

    Pass an associative array of label => value; blank values are dropped, so a
    registration with no phone number does not leave an empty row.
--}}
@props(['rows' => [], 'heading' => null])

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:#fafafa; border:1px solid #e4e4e7; border-radius:4px; margin:16px 0;">
    @if ($heading)
        <tr>
            <td colspan="2" style="padding:12px 16px 0; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                                   font-size:13px; font-weight:700; color:#3f3f46; text-transform:uppercase;
                                   letter-spacing:0.04em;">{{ $heading }}</td>
        </tr>
    @endif
    @foreach ($rows as $label => $value)
        @continue(blank($value))
        <tr>
            <td style="padding:6px 8px 6px 16px; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                       font-size:14px; color:#71717a; vertical-align:top; white-space:nowrap;">{{ $label }}</td>
            <td style="padding:6px 16px 6px 0; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
                       font-size:14px; color:#18181b;">{!! nl2br(e($value)) !!}</td>
        </tr>
    @endforeach
</table>
