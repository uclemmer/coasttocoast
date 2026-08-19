{{--
    A coordinator's campaign (doc 07 §3).

    `$body` is markdown she wrote in the composer, rendered to HTML here. It is
    trusted authored content — only someone holding `messages.send` can write
    it — but it is rendered with Laravel's markdown converter, which escapes
    raw HTML by default, so a pasted fragment cannot break the layout.

    `:campaign="true"` adds the CAN-SPAM explanation line to the footer.
--}}
<x-emails::layout :title="$subject" :campaign="true" :preview="$preview ?? $subject">
    {!! \Illuminate\Support\Str::markdown($body ?? '') !!}
</x-emails::layout>
