<?php

namespace App\Filament\Rep\Resources\RegistrationResource\Pages;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Filament\Rep\Resources\RegistrationResource;
use App\Models\Registration;
use App\Services\Payments\CheckPaymentForm;
use App\Services\Payments\PaymentGateway;
use App\Services\ReceiptPdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
            $this->payAction(),
            $this->checkFormAction(),
            $this->receiptAction(),
        ];
    }

    /**
     * Open (or reopen) Stripe Checkout.
     *
     * The retry path matters more than the happy one: a rep who closed the tab,
     * whose card was declined, or who hit a Stripe outage mid-signup still has
     * their place held, and this is how they finish. Shown only while payment
     * is genuinely outstanding on the card path.
     */
    protected function payAction(): Action
    {
        return Action::make('pay')
            ->label(__('Pay now'))
            ->icon('heroicon-o-credit-card')
            ->visible(fn (Registration $record): bool => $record->status === RegistrationStatus::PendingPayment
                && $record->payment_method === PaymentMethod::Stripe
                && ! $record->isFree())
            ->action(function (Registration $record) {
                try {
                    $this->redirect(app(PaymentGateway::class)->createSession($record)->url);
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title(__('We could not open the payment page. Please try again shortly.'))
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * The printable form to post with a check.
     *
     * Downloadable as well as emailed, because the person who registers is
     * often not the person who writes the checks, and forwarding an email
     * attachment is a step where things get lost.
     */
    protected function checkFormAction(): Action
    {
        return Action::make('checkForm')
            ->label(__('Printable check form'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (Registration $record): bool => $record->status === RegistrationStatus::PendingPayment
                && $record->payment_method === PaymentMethod::Check)
            ->action(function (Registration $record): StreamedResponse {
                $pdf = app(CheckPaymentForm::class);

                return response()->streamDownload(
                    fn () => print ($pdf->render($record)),
                    $pdf->filenameFor($record),
                    ['Content-Type' => 'application/pdf'],
                );
            });
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
