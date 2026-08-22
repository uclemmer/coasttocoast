<?php

namespace App\Livewire\Staff\Grants\Concerns;

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Models\Grant;
use App\Services\GrantService;
use App\Support\Money;
use Throwable;

/**
 * The three decisions a coordinator makes on a grant (docs/13).
 *
 * Shared by the list and the detail screen because both offer all three, and
 * the Filament resource put them in one place for the same reason.
 *
 * THERE IS NO CREATE OR EDIT, and that is the design, carried over verbatim
 * from `GrantResource`'s docblock: a grant is *applied for* by a school through
 * the portal and *decided* here. An edit form would let someone set
 * `status = approved` without choosing a benefit — which `Event::priceFor()`
 * reads as "no discount", so the school would be told it had a grant and then
 * charged in full. Routing every change through `GrantService` makes that
 * unrepresentable, and this trait exists to keep it that way.
 *
 * The service's exception messages are written as user-facing copy, so they are
 * shown verbatim. That is not laziness: it means the screen cannot drift from
 * the rule, because there is no second copy of the wording.
 */
trait DecidesGrants
{
    /** The grant a decision dialog is open for. */
    public ?int $deciding = null;

    public string $benefitType = '';

    public string $customPriceDollars = '';

    public string $percentOff = '';

    public string $reason = '';

    public function startApprove(int $grantId): void
    {
        $this->openDecision($grantId, 'approve-grant');
    }

    public function startDeny(int $grantId): void
    {
        $this->openDecision($grantId, 'deny-grant');
    }

    public function startRevoke(int $grantId): void
    {
        $this->openDecision($grantId, 'revoke-grant');
    }

    protected function openDecision(int $grantId, string $modal): void
    {
        $this->resetDecisionForm();
        $this->deciding = $grantId;
        $this->dispatch('ui-modal-open', id: $modal);
    }

    /** The grant a dialog is open for, or null. Used by the views. */
    public function decidingGrant(): ?Grant
    {
        return $this->deciding === null ? null : Grant::query()->find($this->deciding);
    }

    public function approve(GrantService $service): void
    {
        $grant = $this->grantForDecision();

        if ($grant === null) {
            return;
        }

        /*
         * The two amount fields are required only for the benefit that uses
         * them. Filament expressed this as `visible()` plus `required()` on
         * each; here the rules are assembled from the chosen benefit, which is
         * the same statement made once instead of twice.
         */
        $rules = ['benefitType' => ['required', 'string', 'in:'.collect(GrantBenefit::cases())
            ->map(fn (GrantBenefit $case): string => $case->value)->implode(',')]];

        if ($this->benefitType === GrantBenefit::CustomPrice->value) {
            $rules['customPriceDollars'] = ['required', 'numeric', 'min:0'];
        }

        if ($this->benefitType === GrantBenefit::PercentOff->value) {
            $rules['percentOff'] = ['required', 'integer', 'min:1', 'max:100'];
        }

        $this->validate($rules);

        $benefit = GrantBenefit::from($this->benefitType);

        $this->runDecision(
            fn () => $service->approve(
                grant: $grant,
                coordinator: $this->currentUser(),
                benefit: $benefit,
                // Dollars in the box, cents in the column. Money::toCents is
                // the only conversion in the app and stays that way; see the
                // dollars/cents note in docs/13.
                customPriceCents: $benefit === GrantBenefit::CustomPrice
                    ? Money::toCents($this->customPriceDollars)
                    : null,
                percentOff: $benefit === GrantBenefit::PercentOff ? (int) $this->percentOff : null,
            ),
            __('Approved.'),
            'approve-grant',
        );
    }

    public function deny(GrantService $service): void
    {
        $grant = $this->grantForDecision();

        if ($grant === null) {
            return;
        }

        // Required because "denied", with nothing else, is how you lose a
        // school for good. The service refuses a blank one too.
        $this->validate(['reason' => ['required', 'string']]);

        $this->runDecision(
            fn () => $service->deny($grant, $this->currentUser(), $this->reason),
            __('Denied.'),
            'deny-grant',
        );
    }

    public function revoke(GrantService $service): void
    {
        $grant = $this->grantForDecision();

        if ($grant === null) {
            return;
        }

        $this->validate(['reason' => ['nullable', 'string']]);

        $this->runDecision(
            fn () => $service->revoke($grant, $this->currentUser(), $this->reason === '' ? null : $this->reason),
            __('Revoked.'),
            'revoke-grant',
        );
    }

    /**
     * Resolve and authorise the grant a decision is being made on.
     *
     * The id came from the browser. Filament checked `can('update', $record)`
     * on each action's `visible()`; a hidden button is not a guard, so the
     * check happens here, where the decision actually lands.
     */
    protected function grantForDecision(): ?Grant
    {
        $grant = Grant::query()->find($this->deciding);

        if ($grant === null) {
            $this->toast(__('That application could not be found.'), 'danger');

            return null;
        }

        $this->authorize('update', $grant);

        return $grant;
    }

    protected function runDecision(callable $operation, string $success, string $modal): void
    {
        try {
            $operation();
        } catch (Throwable $e) {
            // Shown verbatim: the service writes these to be read.
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->resetDecisionForm();
        $this->refreshAfterDecision();

        $this->dispatch('ui-modal-close', id: $modal);
        $this->toast($success);
    }

    protected function resetDecisionForm(): void
    {
        $this->deciding = null;
        $this->benefitType = '';
        $this->customPriceDollars = '';
        $this->percentOff = '';
        $this->reason = '';
        $this->resetValidation();
    }

    /** Whether the coordinator may still act on this grant. */
    public function canDecide(Grant $grant): bool
    {
        return $grant->status === GrantStatus::Pending
            && $this->currentUser()->can('update', $grant);
    }

    public function canRevoke(Grant $grant): bool
    {
        return $grant->status === GrantStatus::Approved
            && ! $grant->isUsed()
            && $this->currentUser()->can('update', $grant);
    }

    /** Each screen drops whatever it cached. */
    abstract protected function refreshAfterDecision(): void;
}
