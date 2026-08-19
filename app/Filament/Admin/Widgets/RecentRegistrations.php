<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Event;
use App\Models\Registration;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The last ten registrations for the active fair (R3.8).
 *
 * Scoped to the active fair rather than showing everything: after a fair
 * closes, last year's tail is history, and a dashboard that leads with it
 * tells the coordinator nothing about today.
 */
class RecentRegistrations extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return __('Recent registrations');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $fair = Event::active();

                return Registration::query()
                    ->with(['organization', 'grant'])
                    // No active fair: an empty table, not every registration
                    // ever taken.
                    ->when(
                        $fair instanceof Event,
                        fn (Builder $query): Builder => $query->where('event_id', $fair->getKey()),
                        fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                    );
            })
            ->defaultSort('created_at', 'desc')
            ->paginated(false)
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('organization.name')->label(__('School')),
                TextColumn::make('status')->badge(),
                TextColumn::make('payment_method')->label(__('Method'))->badge()->placeholder(__('Free')),
                TextColumn::make('price_cents')
                    ->label(__('Price'))
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->description(fn (Registration $record): ?string => $record->grant?->benefitSummary()),
                TextColumn::make('created_at')->label(__('Registered'))->since(),
            ]);
    }
}
