<?php

namespace App\Filament\Rep\Resources\GrantResource\Pages;

use App\Filament\Rep\Concerns\ActsForAnOrganization;
use App\Filament\Rep\Resources\GrantResource;
use App\Models\Event;
use App\Services\GrantService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

/**
 * The school's fee-assistance requests, and the one action that creates them.
 *
 * The intro and every string in the form are doc 01 Appendix A verbatim
 * (owner-approved v1). The line about applying not registering you is doing
 * real work: without it, a school applies for a grant and then waits, missing
 * the registration deadline while it does.
 */
class ListGrants extends ListRecords
{
    use ActsForAnOrganization;

    protected static string $resource = GrantResource::class;

    public function getTitle(): string
    {
        return __('Fee assistance grants');
    }

    public function getSubheading(): string
    {
        return $this->membershipNotice() ?? __(
            'If the registration fee is a barrier for your institution, you can request a reduced or '
            ."waived fee for this year's fair. Requests are reviewed by the fair coordinator, and you'll "
            .'hear back by email — usually within a week. Applying does not register you for the fair; '
            .'once your request is decided, register as usual and any approved discount is applied '
            .'automatically.',
        );
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->applyAction(),
        ];
    }

    protected function applyAction(): Action
    {
        return Action::make('apply')
            ->label(__('Request fee assistance'))
            ->icon('heroicon-o-plus')
            // Hidden for pending and retired reps, and when there is no fair
            // left to apply for — an action that can only fail is noise.
            ->visible(fn (): bool => $this->actsForOrganization() && $this->applicableFairs() !== [])
            ->schema([
                Select::make('event_id')
                    ->label(__('Fair'))
                    ->required()
                    ->options(fn (): array => $this->applicableFairs()),

                Textarea::make('justification')
                    ->label(__('Why are you requesting fee assistance?'))
                    ->required()
                    ->maxLength(1000)
                    ->rows(5)
                    ->helperText(__(
                        'A couple of sentences is plenty — e.g., budget constraints, first time '
                        .'attending, non-profit or community program.',
                    )),
            ])
            ->modalHeading(__('Request fee assistance'))
            ->modalSubmitActionLabel(__('Submit request'))
            ->action(function (array $data): void {
                try {
                    app(GrantService::class)->apply(
                        event: Event::query()->findOrFail($data['event_id']),
                        organization: $this->currentOrganization(),
                        rep: $this->currentUser(),
                        justification: $data['justification'],
                    );

                    Notification::make()
                        ->title(__("Request submitted — we'll email you when it's been reviewed."))
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * Fairs still ahead of us that this school has not already applied for.
     *
     * Deliberately not limited to fairs with registration open: lining funding
     * up before registration opens is the point of applying early (doc 10,
     * D-2.6-a).
     *
     * @return array<int, string>
     */
    protected function applicableFairs(): array
    {
        $organization = $this->currentOrganization();

        if ($organization === null) {
            return [];
        }

        return Event::query()
            ->published()
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->reject(fn (Event $event): bool => app(GrantService::class)->hasLiveApplication($event, $organization))
            ->pluck('name', 'id')
            ->all();
    }
}
