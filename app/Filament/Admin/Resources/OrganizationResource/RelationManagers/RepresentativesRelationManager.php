<?php

namespace App\Filament\Admin\Resources\OrganizationResource\RelationManagers;

use App\Enums\MembershipStatus;
use App\Models\User;
use App\Services\OrganizationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The people who speak for a school, and the coordinator's decisions about
 * them (D9, R2.10).
 *
 * Everything here goes through `OrganizationService` rather than writing the
 * columns directly, because each transition has rules and an email attached to
 * it — approving a claim is not "set a string", it is a decision somebody is
 * waiting on.
 */
class RepresentativesRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Representatives';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),

                TextColumn::make('membership_status')
                    ->label(__('Membership'))
                    ->badge()
                    ->placeholder(__('Not a representative')),

                TextColumn::make('membership_approved_at')
                    ->label(__('Approved'))
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('retired_at')
                    ->label(__('Retired'))
                    ->date()
                    ->placeholder('—')
                    ->description(fn (User $record): ?string => $record->retiredBy?->name)
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('membership_status')
                    ->label(__('Membership'))
                    ->options(collect(MembershipStatus::cases())
                        ->mapWithKeys(fn (MembershipStatus $case): array => [$case->value => $case->getLabel()])
                        ->all()),
            ])
            ->recordActions([
                $this->approveAction(),
                $this->denyAction(),
                $this->retireAction(),
                $this->reinstateAction(),
            ]);
    }

    protected function approveAction(): Action
    {
        return Action::make('approveClaim')
            ->label(__('Approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (User $record): bool => $record->isPendingApproval())
            ->requiresConfirmation()
            ->modalHeading(__('Approve this claim?'))
            ->modalDescription(__('They will be able to register the school, apply for grants and edit its profile.'))
            ->action(function (User $record): void {
                app(OrganizationService::class)->approveClaim($record, auth()->user());

                Notification::make()->title(__('Approved.'))->success()->send();
            });
    }

    protected function denyAction(): Action
    {
        return Action::make('denyClaim')
            ->label(__('Deny'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (User $record): bool => $record->isPendingApproval())
            ->schema([
                Textarea::make('reason')
                    ->label(__('Reason (included in the email)'))
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading(__('Deny this claim?'))
            // Not deleted, and not left pending: someone who claimed the wrong
            // school has to be able to sign up again for the right one.
            ->modalDescription(__('Their account is kept but detached from this school, so they can claim a different one.'))
            ->action(function (User $record, array $data): void {
                app(OrganizationService::class)->denyClaim($record, auth()->user(), $data['reason'] ?? null);

                Notification::make()->title(__('Denied.'))->success()->send();
            });
    }

    protected function retireAction(): Action
    {
        return Action::make('retire')
            ->label(__('Retire'))
            ->icon('heroicon-o-archive-box')
            ->color('warning')
            ->visible(fn (User $record): bool => $record->actsForOrganization())
            ->requiresConfirmation()
            ->modalHeading(__('Retire this representative?'))
            ->modalDescription(__('They keep their account and can still see their history. They lose the right to act for this school, and campaigns stop mailing them.'))
            ->action(function (User $record): void {
                app(OrganizationService::class)->retire($record, auth()->user());

                Notification::make()->title(__('Retired.'))->success()->send();
            });
    }

    protected function reinstateAction(): Action
    {
        return Action::make('reinstate')
            ->label(__('Reinstate'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (User $record): bool => $record->isRetired())
            ->requiresConfirmation()
            ->action(function (User $record): void {
                app(OrganizationService::class)->reinstate($record, auth()->user());

                Notification::make()->title(__('Reinstated.'))->success()->send();
            });
    }
}
