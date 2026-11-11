@php
    use App\Support\Money;

    // Assembled here, not in the tag's attribute — see the note in
    // payment-receipt.blade.php about Blade's component-tag parser.
    $rows = array_merge(
        [__('Registrations') => (string) $total],
        array_map('strval', $counts),
        [__('Collected') => Money::format($collectedCents)],
    );
@endphp
<x-emails::layout :title="__('Registration report')"
                  :eyebrow="trim(($event->starts_at?->format('F j, Y') ?? '').' · '.$event->venue_name, ' ·')"
                  :preview="__(':count registrations for :event so far.', ['count' => $total, 'event' => $event->name])">

    <p>{{ __('Here is every registration for :event as of :date. The full list, with contact details, is attached as a spreadsheet.', [
        'event' => $event->name,
        'date' => now()->format('F j, Y'),
    ]) }}</p>

    <x-emails::panel :heading="__('At a glance')" :rows="$rows" />

    <p>{{ __('This report is sent on the first of every month.') }}</p>
</x-emails::layout>
