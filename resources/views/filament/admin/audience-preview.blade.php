{{--
    The recipient list behind an audience preview (doc 07 §3).

    A modal body, so it is a Filament Blade component rather than a schema —
    `modalContent()` takes a view. Deliberately plain: this is a list to scan,
    and a `generic` badge because a coordinator should be able to see at a
    glance how much of a send is going to nobody in particular rather than to a
    named representative.
--}}
<div class="fi-modal-content-ctn">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ trans_choice('{0}Nobody matches this audience right now.|{1}One person.|[2,*]:count people.', $recipients->count(), ['count' => $recipients->count()]) }}
    </p>

    <ul class="mt-3 divide-y divide-gray-200 text-sm dark:divide-white/10">
        @foreach ($recipients as $recipient)
            <li class="py-2">
                <span class="font-medium text-gray-950 dark:text-white">
                    {{ $recipient->name ?? $recipient->email }}
                </span>

                @if ($recipient->name)
                    <span class="text-gray-500 dark:text-gray-400">&lt;{{ $recipient->email }}&gt;</span>
                @endif

                @if ($recipient->organizationName)
                    <span class="text-gray-500 dark:text-gray-400">· {{ $recipient->organizationName }}</span>
                @endif

                @if ($recipient->generic)
                    <span class="text-warning-600 dark:text-warning-400">· {{ __('admissions office — no active rep') }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
