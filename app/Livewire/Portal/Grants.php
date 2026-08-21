<?php

namespace App\Livewire\Portal;

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Livewire\Portal\Concerns\ActsForAnOrganization;
use App\Models\Event;
use App\Models\Grant;
use App\Services\GrantService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

/**
 * Fee assistance grants, from the school's side (card 3.5) — the Livewire
 * replacement for the rep panel's GrantResource and its ListGrants page
 * (docs/12).
 *
 * **All copy here is doc 01 Appendix A verbatim** — owner-approved v1, tweaks
 * go through him. That includes the status sentences, which do real work:
 * "pending" with no further word is how somebody concludes they have been
 * forgotten.
 *
 * The two actions are `apply` and `withdraw`, and neither implements its own
 * rules: `GrantService` owns whether an application is allowed and what
 * withdrawing does. This component collects a fair and a justification, calls
 * the service, and reports what came back — including the failures, which
 * arrive as exceptions carrying a message written to be read.
 *
 * Browsable by pending and retired reps; both actions are hidden from them.
 * `actsForOrganization()` is checked again inside each action rather than only
 * in the view, because a hidden button is a UI convenience and not a guard.
 */
#[Layout('components.layouts.portal', ['title' => 'Fee assistance', 'heading' => 'Fee assistance grants'])]
class Grants extends Component
{
    use ActsForAnOrganization;

    /** The fair being applied for, while the apply dialog is open. */
    #[Validate('required|integer|exists:events,id')]
    public ?int $event_id = null;

    #[Validate('required|string|max:1000')]
    public string $justification = '';

    /** The request the withdraw dialog is asking about. */
    public ?int $withdrawing = null;

    /**
     * This school's requests, newest first.
     *
     * @return Collection<int, Grant>
     */
    #[Computed]
    public function grants(): Collection
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return collect();
        }

        return Grant::query()
            ->with('event')
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Fairs still ahead of us that this school has not already applied for.
     *
     * Deliberately not limited to fairs with registration open: lining funding
     * up before registration opens is the point of applying early (doc 10,
     * D-2.6-a).
     *
     * @return Collection<int, Event>
     */
    #[Computed]
    public function applicableFairs(): Collection
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return collect();
        }

        return Event::query()
            ->published()
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->reject(fn (Event $event): bool => app(GrantService::class)->hasLiveApplication($event, $organization))
            ->values();
    }

    /** Whether the "request fee assistance" button has anything to offer. */
    #[Computed]
    public function canApply(): bool
    {
        return $this->actsForOrganization() && $this->applicableFairs->isNotEmpty();
    }

    public function apply(GrantService $service): void
    {
        if (! $this->actsForOrganization()) {
            $this->notifyMembershipRefusal();

            return;
        }

        $this->validate();

        try {
            $service->apply(
                event: Event::query()->findOrFail($this->event_id),
                organization: $this->currentOrganization(),
                rep: $this->currentUser(),
                justification: $this->justification,
            );
        } catch (Throwable $e) {
            // The service's messages are written for a rep to read, so they are
            // shown rather than swallowed behind something generic.
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset('event_id', 'justification');
        unset($this->grants, $this->applicableFairs, $this->canApply);

        $this->dispatch('ui-modal-close', id: 'apply-for-grant');
        $this->toast(__("Request submitted — we'll email you when it's been reviewed."));
    }

    public function confirmWithdraw(int $grantId): void
    {
        $this->withdrawing = $grantId;
        $this->dispatch('ui-modal-open', id: 'withdraw-grant');
    }

    public function withdraw(GrantService $service): void
    {
        if (! $this->actsForOrganization()) {
            $this->notifyMembershipRefusal();

            return;
        }

        /*
         * Scoped to this school's grants, not `find()`. Without the scope a
         * crafted id would withdraw somebody else's request — the id arrives
         * from the browser, and a confirmation dialog is not authorization.
         */
        $grant = Grant::query()
            ->where('organization_id', $this->currentOrganization()?->id)
            ->find($this->withdrawing);

        if ($grant === null) {
            $this->toast(__('That request could not be found.'), 'danger');

            return;
        }

        try {
            $service->withdraw($grant, $this->currentUser());
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->withdrawing = null;
        unset($this->grants, $this->applicableFairs, $this->canApply);

        $this->dispatch('ui-modal-close', id: 'withdraw-grant');
        $this->toast(__('Request withdrawn.'));
    }

    /**
     * The status sentences, verbatim from doc 01 Appendix A.
     *
     * A method on the component rather than on the model: this is presentation
     * for one screen, and putting it on `Grant` would invite the coordinator's
     * admin screens to reuse copy written for a school to read.
     */
    public function statusCopy(Grant $grant): string
    {
        return match ($grant->status) {
            GrantStatus::Pending => __("Your request is being reviewed. We'll email you as soon as there's a decision."),
            GrantStatus::Approved => __(
                'Good news — your registration fee for :event is :benefit. The discount is applied '
                .'automatically when you register.',
                [
                    'event' => (string) $grant->event?->name,
                    'benefit' => $this->benefitPhrase($grant),
                ],
            ),
            GrantStatus::Denied => __(
                "We weren't able to approve fee assistance this year. :reason Standard registration is "
                .'still open.',
                ['reason' => (string) $grant->denial_reason],
            ),
            GrantStatus::Revoked => __('This grant has been withdrawn by the coordinator. :reason', [
                'reason' => (string) $grant->denial_reason,
            ]),
            GrantStatus::Withdrawn => __('You withdrew this request.'),
        };
    }

    /** "waived", "$50.00" or "25% off", to slot into the approved sentence. */
    protected function benefitPhrase(Grant $grant): string
    {
        return match ($grant->benefit_type) {
            GrantBenefit::Free => __('waived'),
            GrantBenefit::CustomPrice => Money::format($grant->custom_price_cents),
            GrantBenefit::PercentOff => __(':percent% off', ['percent' => (int) $grant->percent_off]),
            null => __('reduced'),
        };
    }

    public function render(): View
    {
        return view('livewire.portal.grants');
    }
}
