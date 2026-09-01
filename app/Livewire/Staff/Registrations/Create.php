<?php

namespace App\Livewire\Staff\Registrations;

use App\Enums\PaymentMethod;
use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Services\RegistrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Manual entry (R3.4) — a phone call, a form that arrived in the post.
 * Replaces the admin panel's CreateRegistration page (docs/13).
 *
 * IT DOES NOT WRITE THE MODEL. `RegistrationService::createManualEntry()` does,
 * and that is the whole point: it runs the same rules the portal does, minus
 * the membership and window checks. Duplicates are still refused and the price
 * is still read from the fair and any approved grant. Creating the row here
 * would take a price snapshot nobody agreed.
 *
 * Organization and fair are chosen only at creation and never editable afterwards —
 * moving a registration to another fair would carry a price that was never
 * agreed for it.
 */
#[Layout('components.layouts.staff', ['title' => 'Manual registration', 'heading' => 'Add a manual registration'])]
class Create extends Component
{
    use ActsForStaff;

    public string $event_id = '';

    public string $organization_id = '';

    public string $payment_method = '';

    public string $rep_name = '';

    public string $rep_email = '';

    public string $rep_phone = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->abortUnlessStaff();
        $this->authorize('create', Registration::class);
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function fairs(): Collection
    {
        return Event::query()->orderByDesc('starts_at')->get();
    }

    /**
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function organizations(): Collection
    {
        return Organization::query()->orderBy('name')->get();
    }

    /** @return array<int, PaymentMethod> */
    public function paymentMethods(): array
    {
        return PaymentMethod::cases();
    }

    public function save(RegistrationService $service): void
    {
        $this->authorize('create', Registration::class);

        $this->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'payment_method' => ['nullable', 'string', 'in:'.collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $case): string => $case->value)->implode(',')],
            'rep_name' => ['required', 'string', 'max:255'],
            'rep_email' => ['required', 'email', 'max:255'],
            'rep_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $registration = $service->createManualEntry(
                event: Event::query()->findOrFail($this->event_id),
                organization: Organization::query()->findOrFail($this->organization_id),
                contact: [
                    'rep_name' => $this->rep_name,
                    'rep_email' => $this->rep_email,
                    'rep_phone' => $this->rep_phone === '' ? null : $this->rep_phone,
                ],
                method: $this->payment_method === '' ? null : PaymentMethod::from($this->payment_method),
                notes: $this->notes === '' ? null : $this->notes,
            );
        } catch (Throwable $e) {
            /*
             * Attached to the organization field rather than raised as a toast: the
             * refusal that actually happens here is "this organization is already
             * registered for this fair", and the answer is to change that
             * field. Filament keyed the same exception to the same input.
             */
            $this->addError('organization_id', $e->getMessage());

            return;
        }

        session()->flash('status', __('Registration added.'));

        $this->redirect(route('staff.registrations.show', $registration), navigate: false);
    }

    /**
     * Name the two selects as the form labels them.
     *
     * Laravel derives an attribute name from the key, so `organization_id`
     * fails as "the organization id field is required" — naming a foreign key
     * this form never shows. The selects are labelled "Fair" and
     * "Organization"; the failures should say the same words.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'event_id' => __('fair'),
            'organization_id' => __('organization'),
        ];
    }

    public function render(): View
    {
        return view('livewire.staff.registrations.create');
    }
}
