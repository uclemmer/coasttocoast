<?php

namespace App\Filament\Rep\Resources;

use App\Enums\GrantBenefit;
use App\Enums\GrantStatus;
use App\Filament\Rep\Resources\GrantResource\Pages\ListGrants;
use App\Models\Grant;
use App\Models\User;
use App\Services\GrantService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Fee assistance grants, from the school's side (card 3.5).
 *
 * All copy on this page is doc 01 Appendix A verbatim — owner-approved v1,
 * tweaks go through him. That includes the status sentences, which do real
 * work: "pending" with no further word is how somebody concludes they have
 * been forgotten.
 *
 * Scoped to the rep's organization, like every portal resource.
 */
class GrantResource extends Resource
{
    protected static ?string $model = Grant::class;

    protected static ?int $navigationSort = 20;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-lifebuoy';

    public static function getNavigationLabel(): string
    {
        return __('Fee assistance');
    }

    public static function getModelLabel(): string
    {
        return __('request');
    }

    public static function getEloquentQuery(): Builder
    {
        $organizationId = auth()->user()?->organization_id;

        return parent::getEloquentQuery()
            ->with(['event'])
            ->when(
                $organizationId !== null,
                fn (Builder $query): Builder => $query->where('organization_id', $organizationId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
    }

    /**
     * Portal authorization, not `GrantPolicy` — see the note on the rep
     * RegistrationResource. The policy answers a coordinator's question; this
     * answers a school's.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->organization_id !== null;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Grant
            && $record->organization_id === auth()->user()?->organization_id;
    }

    public static function canCreate(): bool
    {
        // Applying is a header action on the list page, not a create page:
        // an application is one textarea, and a whole page for it would be
        // ceremony (Appendix A: "one screen, no wizard").
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.name')->label(__('Fair'))->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('outcome')
                    ->label(__('Outcome'))
                    ->state(fn (Grant $record): string => static::statusCopy($record))
                    ->wrap(),
                TextColumn::make('created_at')->label(__('Requested'))->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No requests yet'))
            ->emptyStateDescription(__('If the registration fee is a barrier for your institution, you can ask for a reduced or waived fee.'))
            ->recordActions([
                static::withdrawAction(),
            ]);
    }

    /**
     * The status sentences, verbatim from doc 01 Appendix A.
     */
    public static function statusCopy(Grant $grant): string
    {
        return match ($grant->status) {
            GrantStatus::Pending => __("Your request is being reviewed. We'll email you as soon as there's a decision."),
            GrantStatus::Approved => __(
                'Good news — your registration fee for :event is :benefit. The discount is applied '
                .'automatically when you register.',
                [
                    'event' => (string) $grant->event?->name,
                    'benefit' => static::benefitPhrase($grant),
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

    /**
     * "waived", "$50.00" or "25% off", to slot into the approved sentence.
     */
    protected static function benefitPhrase(Grant $grant): string
    {
        return match ($grant->benefit_type) {
            GrantBenefit::Free => __('waived'),
            GrantBenefit::CustomPrice => Money::format($grant->custom_price_cents),
            GrantBenefit::PercentOff => __(':percent% off', ['percent' => (int) $grant->percent_off]),
            null => __('reduced'),
        };
    }

    /**
     * Take a pending request back. The only status that frees the school to
     * apply again for the same fair.
     */
    protected static function withdrawAction(): Action
    {
        return Action::make('withdraw')
            ->label(__('Withdraw'))
            ->color('gray')
            ->visible(function (Grant $record): bool {
                /** @var User|null $user */
                $user = auth()->user();

                return $record->status === GrantStatus::Pending && $user?->actsForOrganization() === true;
            })
            ->requiresConfirmation()
            ->modalHeading(__('Withdraw this request?'))
            ->modalDescription(__('You can submit a new one for the same fair afterwards.'))
            ->action(function (Grant $record): void {
                try {
                    app(GrantService::class)->withdraw($record, auth()->user());

                    Notification::make()->title(__('Request withdrawn.'))->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListGrants::route('/'),
        ];
    }
}
