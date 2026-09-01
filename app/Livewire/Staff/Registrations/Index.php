<?php

namespace App\Livewire\Staff\Registrations;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Event;
use App\Models\Registration;
use App\Services\RegistrationService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The registration list (R3.4) — the Livewire replacement for the admin
 * panel's RegistrationResource list and its two header actions (docs/13).
 *
 * THE EXPORT AND THE TABLE SHARE ONE QUERY, and that is the whole feature.
 * The point is that a coordinator applies a filter, presses export and gets
 * *that* list. Filament achieved it with `getFilteredTableQuery()`; here
 * `filteredQuery()` is the single builder both the table and the CSV read, so
 * they cannot drift apart. Rebuilding the filters beside the export would be
 * the bug this note exists to prevent.
 *
 * Streamed rather than queued, for the same reason: an export that arrives by
 * email a minute later, ignoring the filters, is not the same feature. Fair
 * sizes are in the hundreds.
 *
 * THERE IS NO DELETE. A registration is cancelled through `RegistrationService`
 * so the seat is released and the record of what happened survives.
 */
#[Layout('components.layouts.staff', ['title' => 'Registrations', 'heading' => 'Registrations'])]
class Index extends Component
{
    use ActsForStaff;

    public string $search = '';

    public string $eventId = '';

    public string $status = '';

    public string $paymentMethod = '';

    /** '' (all), 'yes', 'no' — Filament's TernaryFilter on show_on_roster. */
    public string $onRoster = '';

    /** '' (all), 'yes' (has a grant), 'no' (none). */
    public string $hasGrant = '';

    /** The registration the cancel dialog is asking about. */
    public ?int $cancelling = null;

    public string $cancelReason = '';

    public function mount(): void
    {
        $this->abortUnlessStaff();
        $this->authorize('viewAny', Registration::class);
    }

    /**
     * The one query the table and the export both read.
     *
     * @return Builder<Registration>
     */
    public function filteredQuery(): Builder
    {
        return Registration::query()
            ->when($this->eventId !== '', fn ($query) => $query->where('event_id', $this->eventId))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->paymentMethod !== '', fn ($query) => $query->where('payment_method', $this->paymentMethod))
            ->when($this->onRoster !== '', fn ($query) => $query->where('show_on_roster', $this->onRoster === 'yes'))
            ->when($this->hasGrant === 'yes', fn ($query) => $query->whereNotNull('grant_id'))
            ->when($this->hasGrant === 'no', fn ($query) => $query->whereNull('grant_id'))
            ->when($this->search !== '', fn ($query) => $query->where(function ($inner): void {
                $inner->where('rep_name', 'like', '%'.$this->search.'%')
                    ->orWhere('rep_email', 'like', '%'.$this->search.'%')
                    ->orWhereHas(
                        'organization',
                        fn ($organization) => $organization->where('name', 'like', '%'.$this->search.'%'),
                    );
            }));
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registrations(): Collection
    {
        return $this->filteredQuery()
            ->with(['organization', 'event', 'grant'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function fairs(): Collection
    {
        return Event::query()->orderByDesc('starts_at')->get();
    }

    public function updated(): void
    {
        unset($this->registrations);
    }

    /** CSV of whatever the table is currently showing. See the class note. */
    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Registration::class);

        $rows = $this->filteredQuery()->with(['organization', 'event', 'grant'])->get();

        $filename = 'registrations-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'Organization', 'Fair', 'Status', 'Payment method', 'Price', 'Grant',
                'Contact name', 'Contact email', 'Contact phone',
                'On roster', 'Registered', 'Confirmed',
            ]);

            foreach ($rows as $registration) {
                fputcsv($handle, [
                    $registration->organization?->name,
                    $registration->event?->name,
                    $registration->status->getLabel(),
                    $registration->payment_method?->getLabel() ?? 'Free',
                    Money::format($registration->price_cents),
                    $registration->grant?->benefitSummary() ?? '',
                    $registration->rep_name,
                    $registration->rep_email,
                    $registration->rep_phone ?? '',
                    $registration->show_on_roster ? 'yes' : 'no',
                    $registration->created_at?->toDateString(),
                    $registration->confirmed_at?->toDateString() ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function confirmCancel(int $registrationId): void
    {
        $this->cancelling = $registrationId;
        $this->cancelReason = '';
        $this->dispatch('ui-modal-open', id: 'cancel-registration');
    }

    public function cancel(RegistrationService $service): void
    {
        $registration = Registration::query()->find($this->cancelling);

        if ($registration === null) {
            $this->toast(__('That registration could not be found.'), 'danger');

            return;
        }

        $this->authorize('cancel', $registration);

        try {
            $service->cancel($registration, $this->cancelReason === '' ? null : $this->cancelReason);
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->cancelling = null;
        $this->cancelReason = '';
        unset($this->registrations);

        $this->dispatch('ui-modal-close', id: 'cancel-registration');
        $this->toast(__('Registration cancelled.'));
    }

    public function canCancel(Registration $registration): bool
    {
        return $this->currentUser()->can('cancel', $registration);
    }

    /** @return array<int, RegistrationStatus> */
    public function statuses(): array
    {
        return RegistrationStatus::cases();
    }

    /** @return array<int, PaymentMethod> */
    public function paymentMethods(): array
    {
        return PaymentMethod::cases();
    }

    public function render(): View
    {
        return view('livewire.staff.registrations.index');
    }
}
