<?php

namespace App\Livewire\Staff;

use App\Enums\GrantStatus;
use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Event;
use App\Models\Grant;
use App\Models\Registration;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The staff landing page (docs/13) — replaces the admin panel's two dashboard
 * widgets, `ActiveFairOverview` and `RecentRegistrations`.
 *
 * THE MONEY NUMBERS COME FROM REGISTRATIONS, NOT FROM THE PAYMENTS TABLE, and
 * that is deliberate rather than lazy. "Collected" here means the price each
 * school was quoted and has confirmed against, so it agrees with what the
 * coordinator told them; the payments table answers a different question and
 * would disagree by whatever is in flight.
 *
 * "Awaiting payment" separates out the checks, which are money in the post
 * rather than money lost.
 */
#[Layout('components.layouts.staff', ['title' => 'Overview', 'heading' => 'Overview'])]
class Dashboard extends Component
{
    use ActsForStaff;

    public function mount(): void
    {
        $this->abortUnlessStaff();
    }

    #[Computed]
    public function fair(): ?Event
    {
        return Event::active();
    }

    /**
     * @return array{confirmed:int,collected:int,awaited:int,awaitingChecks:int}|null
     */
    #[Computed]
    public function numbers(): ?array
    {
        $fair = $this->fair;

        if (! $fair instanceof Event) {
            return null;
        }

        $confirmed = $fair->registrations()->confirmed();
        $pending = $fair->registrations()->where('status', RegistrationStatus::PendingPayment);

        return [
            'confirmed' => (clone $confirmed)->count(),
            'collected' => (int) (clone $confirmed)->sum('price_cents'),
            'awaited' => (int) (clone $pending)->sum('price_cents'),
            'awaitingChecks' => (clone $pending)->where('payment_method', PaymentMethod::Check)->count(),
        ];
    }

    /**
     * The ten most recent registrations for the active fair.
     *
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function recent(): Collection
    {
        $fair = $this->fair;

        if (! $fair instanceof Event) {
            return collect();
        }

        return Registration::query()
            ->with(['organization', 'grant'])
            ->where('event_id', $fair->getKey())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    /** Applications waiting on a decision — somebody is on the other end. */
    #[Computed]
    public function pendingGrants(): int
    {
        return Grant::query()->where('status', GrantStatus::Pending)->count();
    }

    public function formatMoney(?int $cents): string
    {
        return Money::format($cents);
    }

    public function render(): View
    {
        return view('livewire.staff.dashboard');
    }
}
