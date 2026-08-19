<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Filament\Admin\Resources\RegistrationResource;
use App\Models\Registration;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Add a manual registration')),
            $this->exportAction(),
        ];
    }

    /**
     * CSV of whatever the table is currently showing.
     *
     * Streamed rather than queued through Filament's exporter: the whole point
     * is that the coordinator applies a filter, presses export and gets that
     * list. A queued export that arrives by email a minute later, ignoring the
     * filters, would not be the same feature. Fair sizes here are in the
     * hundreds, so streaming is comfortably within budget.
     */
    protected function exportAction(): Action
    {
        return Action::make('export')
            ->label(__('Export CSV'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function (): StreamedResponse {
                $rows = $this->getFilteredTableQuery()
                    ->with(['organization', 'event', 'grant'])
                    ->get();

                $filename = 'registrations-'.now()->format('Y-m-d').'.csv';

                return response()->streamDownload(function () use ($rows): void {
                    $handle = fopen('php://output', 'wb');

                    fputcsv($handle, [
                        'School', 'Fair', 'Status', 'Payment method', 'Price', 'Grant',
                        'Contact name', 'Contact email', 'Contact phone',
                        'On roster', 'Registered', 'Confirmed',
                    ]);

                    foreach ($rows as $registration) {
                        /** @var Registration $registration */
                        fputcsv($handle, [
                            $registration->organization?->name,
                            $registration->event?->name,
                            $registration->status->getLabel(),
                            $registration->payment_method?->getLabel() ?? 'Free',
                            Money::format($registration->price_cents),
                            $registration->grant?->benefitSummary() ?? '',
                            $registration->rep_name,
                            $registration->rep_email,
                            $registration->rep_phone ?? '',
                            $registration->show_on_roster ? 'yes' : 'no',
                            $registration->created_at?->toDateString(),
                            $registration->confirmed_at?->toDateString() ?? '',
                        ]);
                    }

                    fclose($handle);
                }, $filename, ['Content-Type' => 'text/csv']);
            });
    }
}
