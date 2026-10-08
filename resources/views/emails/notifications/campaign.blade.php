{{--
    A coordinator's campaign (doc 07 §3).

    `$body` is markdown she wrote in the composer, rendered to HTML here. It is
    trusted authored content — only someone holding `messages.send` can write
    it. It renders through `App\Support\Markdown`, which escapes raw HTML and
    drops `javascript:`-style links (owner, 2026-10-08; docs/24 §5): Laravel's
    own converter does neither by default, so a pasted fragment used to reach
    the inbox as live HTML. Markdown formatting is unaffected.

    `:campaign="true"` adds the CAN-SPAM explanation line to the footer.
--}}
<x-emails::layout :title="$subject" :campaign="true" :preview="$preview ?? $subject">
    {!! \App\Support\Markdown::render($body ?? '') !!}
</x-emails::layout>
