<?php

namespace App\Filament\Admin\Resources\MessageResource\RelationManagers;

use App\Models\MessageRecipient;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Who a campaign actually reached, and what happened (doc 07 §3, §4).
 *
 * The email column reads `resolvedEmailStatus()`, which prefers the linked
 * laravel-core log row over the local column: the log is what the transport
 * reported, and `core:prune-email-logs` keeps it honest by promoting stale
 * `sending` rows to `failed`. The local column answers only for rows with no
 * log — SMS-only recipients, or an environment with logging off.
 */
class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Delivery';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('organization_name')
                    ->label(__('School'))
                    ->searchable()
                    ->placeholder(__('Interest list')),

                TextColumn::make('name')
                    ->label(__('Person'))
                    // The generic admissions-email fallback has no name; say so
                    // rather than leaving a blank cell.
                    ->placeholder(__('Admissions office'))
                    ->description(fn (MessageRecipient $record): string => $record->email)
                    ->searchable(['name', 'email']),

                TextColumn::make('email_status')
                    ->label(__('Email'))
                    ->badge()
                    ->state(fn (MessageRecipient $record) => $record->resolvedEmailStatus()),

                TextColumn::make('sms_status')->label(__('SMS'))->badge(),

                TextColumn::make('error')->label(__('Problem'))->placeholder('—')->wrap()->toggleable(),
            ])
            ->defaultSort('organization_name')
            ->paginated([25, 50, 100])
            ->emptyStateHeading(__('Not sent yet'))
            ->emptyStateDescription(__('Recipients are worked out and recorded at the moment the campaign sends.'));
    }
}
