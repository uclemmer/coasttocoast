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
use Carbon\CarbonImmutable as Carbon;
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
 * organization was quoted and has confirmed against, so it agrees with what the
 * coordinator told them; the payments table answers a different question and
 * would disagree by whatever is in flight.
 *
 * "Awaiting payment" separates out the checks, which are money in the post
 * rather than money lost.
 *
 * The weekly chart and the countdown card come from the design handoff's
 * "Admin Dashboard.dc.html" (docs/16). That file is drawn as a Filament panel;
 * this app has had no Filament since 2026-08-22, so what is taken from it is
 * the information design rather than the implementation.
 */
#[Layout('components.layouts.staff', ['title' => 'Overview', 'heading' => 'Overview'])]
class Dashboard extends Component
{
    use ActsForStaff;

    /** The design's chart is "Last 12 weeks". */
    private const WEEKS_CHARTED = 12;

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

    /**
     * Registrations per week for the last twelve, oldest first — the design's
     * bar chart.
     *
     * GROUPED IN PHP, NOT IN SQL. A `GROUP BY` on a week number needs a
     * date function, and the ones that exist differ by driver: this app runs
     * SQLite in tests and MySQL in production, and `WEEK()` does not exist in
     * the first while `strftime('%W')` does not exist in the second. Twelve
     * weeks of one fair's registrations is a few hundred rows at the very
     * outside, so the grouping happens here and the query stays portable.
     *
     * The buckets are built first and then filled, so a week nobody registered
     * in is a zero-height bar rather than a missing one — which is the whole
     * point of plotting it.
     *
     * @return array{labels:list<string>, values:list<int>}
     */
    #[Computed]
    public function weeklyRegistrations(): array
    {
        $fair = $this->fair;

        if (! $fair instanceof Event) {
            return ['labels' => [], 'values' => []];
        }

        $start = now()->startOfWeek()->subWeeks(self::WEEKS_CHARTED - 1);

        /** @var array<string, int> $buckets */
        $buckets = [];

        for ($week = 0; $week < self::WEEKS_CHARTED; $week++) {
            $buckets[$start->copy()->addWeeks($week)->toDateString()] = 0;
        }

        Registration::query()
            ->where('event_id', $fair->getKey())
            ->where('created_at', '>=', $start)
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$buckets): void {
                $key = $createdAt->copy()->startOfWeek()->toDateString();

                if (array_key_exists($key, $buckets)) {
                    $buckets[$key]++;
                }
            });

        return [
            'labels' => array_map(
                fn (string $date): string => Carbon::parse($date)->format('M j'),
                array_keys($buckets),
            ),
            'values' => array_values($buckets),
        ];
    }

    /**
     * Whole days from now until the fair opens, or null once it has.
     *
     * `startOfDay()` on both sides: the coordinator reads this as "how many
     * days do I have", and diffing two timestamps answers a different question
     * — an event at 18:30 tomorrow is one day away all morning and zero days
     * away all afternoon.
     */
    #[Computed]
    public function daysUntilFair(): ?int
    {
        $fair = $this->fair;

        if (! $fair instanceof Event || $fair->starts_at->isPast()) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($fair->starts_at->copy()->startOfDay());
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
