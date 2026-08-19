{{--
    Label + control + error, in the design's form styling.

    Inputs are 15.5px with a 1.5px `field-border`, radius 8, on `field-bg`;
    focus turns the border brand green and removes the outline. The error state
    is the handoff's: danger border, danger-100 background, danger label, and a
    13.5px danger-dark message beneath.

    Props:
      $label, $name, $type, $required, $placeholder, $error
    Pass a slot instead of a type to supply your own control (a textarea, a
    select), and it inherits the label and the error.
--}}
@props([
    'label',
    'name',
    'type' => 'text',
    'required' => false,
    'placeholder' => null,
    'error' => null,
    'hint' => null,
])

@php
    $control = 'w-full rounded-lg border-[1.5px] px-3.5 py-3 font-sans text-[15.5px] text-ink-800 placeholder:text-placeholder focus:outline-none focus:ring-0 '
        .($error
            ? 'border-danger bg-danger-100 focus:border-danger'
            : 'border-field-border bg-field-bg focus:border-brand-600');
@endphp

<div class="grid gap-1.5">
    <label for="{{ $name }}"
           @class([
               'text-[13px] font-semibold uppercase tracking-[0.06em]',
               'text-danger' => $error,
               'text-ink-600' => ! $error,
           ])>
        {{ $label }}@if ($required)<span class="text-danger" aria-hidden="true">&nbsp;*</span>@endif
    </label>

    @if (isset($slot) && trim($slot) !== '')
        {{ $slot }}
    @else
        <input type="{{ $type }}"
               id="{{ $name }}"
               @if ($required) required aria-required="true" @endif
               @if ($error) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
               placeholder="{{ $placeholder }}"
               {{ $attributes->class($control) }}>
    @endif

    @if ($hint && ! $error)
        <p class="text-[13.5px] text-ink-500">{{ $hint }}</p>
    @endif

    @if ($error)
        <p id="{{ $name }}-error" class="text-[13.5px] text-danger-dark">{{ $error }}</p>
    @endif
</div>
