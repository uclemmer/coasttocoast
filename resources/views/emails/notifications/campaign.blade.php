{{--
    A coordinator's campaign (doc 07 §3).

    `$body` is markdown she wrote in the composer, rendered to HTML here. It is
    trusted authored content — only someone holding `messages.send` can write
    it. Laravel's markdown converter does NOT escape raw HTML by default
    (measured 2026-10-07, docs/24 §5): `<div>`, `<img onerror>` and
    `javascript:` links render live, and only a short list of tags such as
    `<script>` is neutralised. So a pasted fragment reaches the inbox as HTML.
    Escaping it is an open decision, recorded there.

    `:campaign="true"` adds the CAN-SPAM explanation line to the footer.
--}}
<x-emails::layout :title="$subject" :campaign="true" :preview="$preview ?? $subject">
    {!! \Illuminate\Support\Str::markdown($body ?? '') !!}
</x-emails::layout>
