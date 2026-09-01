{{--
    One organization in a list inside an email — used by the coordinator's digests
    and by any campaign that names who is coming (doc 07 §1).

    Text only, no logo: images in email are blocked by default in most clients,
    so a roster built from them would read as a column of empty boxes.
--}}
@props(['name', 'detail' => null])

<p style="margin:0 0 4px; font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
          font-size:14px; color:#18181b;">
    {{ $name }}@if ($detail)<span style="color:#71717a;"> — {{ $detail }}</span>@endif
</p>
