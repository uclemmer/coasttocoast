<?php

namespace App\Filament\Rep\Resources\RegistrationResource\Pages;

use App\Filament\Rep\Resources\RegistrationResource;
use App\Models\Registration;
use App\Services\ReceiptPdf;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One registration in detail, with its receipt (card 3.3).
 *
 * The retry-payment button attaches here with card 4.1.
 */
class ViewRegistration extends ViewRecord
{
    protected static string $resource = RegistrationResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->receiptAction(),
        ];
    }

    /**
     * Download the receipt.
     *
     * Confirmed registrations only. A receipt for money that has not arrived
     * is exactly the document a finance office would file and forget about,
     * and then everyone is surprised in April.
     */
    protected function receiptAction(): Action
    {
        return Action::make('receipt')
            ->label(__('Download receipt'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (Registration $record): bool => app(ReceiptPdf::class)->isAvailableFor($record))
            ->action(function (Registration $record): StreamedResponse {
                $pdf = app(ReceiptPdf::class);

                return response()->streamDownload(
                    fn () => print ($pdf->render($record)),
                    $pdf->filenameFor($record),
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }
}
