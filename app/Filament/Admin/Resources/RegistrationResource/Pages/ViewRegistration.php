<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Admin\Resources\RegistrationResource;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Payments\CheckPaymentService;
use App\Services\Payments\PaymentGateway;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;
use Throwable;

class ViewRegistration extends ViewRecord
{
    protected static string $resource = RegistrationResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->markCheckReceivedAction(),
            $this->refundAction(),
        ];
    }

    /**
     * The coordinator opening an envelope (card 4.2).
     *
     * The amount defaults to what was owed, so the common case is two fields
     * and a button. Typing a different figure records what actually arrived —
     * this is a ledger of what happened, not of what should have — and a short
     * check gets a warning rather than a refusal, because nobody should be
     * turned away at the door over a dollar.
     */
    protected function markCheckReceivedAction(): Action
    {
        return Action::make('markCheckReceived')
            ->label(__('Mark check received'))
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Registration $record): bool => $record->status === RegistrationStatus::PendingPayment
                && $record->payment_method === PaymentMethod::Check
                && auth()->user()?->can('recordPayment', $record) === true)
            ->schema(fn (Registration $record): array => [
                TextInput::make('check_number')
                    ->label(__('Check number'))
                    ->maxLength(255),

                DatePicker::make('received_on')
                    ->label(__('Received on'))
                    ->default(now())
                    ->required(),

                TextInput::make('amount_dollars')
                    ->label(__('Amount on the check'))
                    ->prefix('$')
                    ->numeric()
                    ->step('0.01')
                    ->default(Money::toDollars($record->price_cents))
                    ->helperText(__('Defaults to what was owed. Change it only if the check differs.')),
            ])
            ->modalHeading(__('Record a check'))
            ->modalDescription(__('This confirms the registration and queues the receipt.'))
            ->action(function (Registration $record, array $data): void {
                $amountCents = Money::toCents($data['amount_dollars'] ?? null);

                try {
                    app(CheckPaymentService::class)->markReceived(
                        registration: $record,
                        coordinator: auth()->user(),
                        checkNumber: $data['check_number'] ?? null,
                        receivedOn: filled($data['received_on'] ?? null)
                            ? Carbon::parse($data['received_on'])
                            : null,
                        amountCents: $amountCents,
                    );

                    if ($amountCents < $record->price_cents) {
                        // Surfaced, not blocked. The alternative is noticing in April.
                        Notification::make()
                            ->title(__('Recorded, but the check is short.'))
                            ->body(__(':paid received against :owed owed.', [
                                'paid' => Money::format($amountCents),
                                'owed' => Money::format($record->price_cents),
                            ]))
                            ->warning()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()->title(__('Check recorded and registration confirmed.'))->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * Send money back (card 4.3).
     *
     * Calls Stripe and stops. The `charge.refunded` webhook owns the status
     * transition, so a refund issued here and one issued from the Stripe
     * dashboard leave the database in exactly the same state — which is the
     * only way the two can be trusted to agree.
     */
    protected function refundAction(): Action
    {
        return Action::make('refund')
            ->label(__('Refund'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->visible(fn (Registration $record): bool => $this->refundablePayment($record) !== null
                && auth()->user()?->can('recordPayment', $record) === true)
            ->schema(fn (Registration $record): array => [
                TextInput::make('amount_dollars')
                    ->label(__('Amount to refund'))
                    ->prefix('$')
                    ->numeric()
                    ->step('0.01')
                    ->default(Money::toDollars($this->refundablePayment($record)?->amount_cents))
                    ->helperText(__('Defaults to the full amount. Reduce it for a partial refund.')),
            ])
            ->requiresConfirmation()
            ->modalHeading(__('Refund this payment?'))
            ->modalDescription(__('Stripe processes the refund. The status here updates when Stripe confirms it.'))
            ->action(function (Registration $record, array $data): void {
                $payment = $this->refundablePayment($record);

                if ($payment === null) {
                    return;
                }

                try {
                    app(PaymentGateway::class)->refund($payment, Money::toCents($data['amount_dollars'] ?? null));

                    Notification::make()
                        ->title(__('Refund sent to Stripe.'))
                        ->body(__('The registration updates once Stripe confirms it.'))
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * The settled card payment on this registration, if there is one.
     *
     * Checks are excluded on purpose: a mailed check is refunded by writing
     * one back, which is not something this application can do, and offering a
     * button that pretends otherwise would be worse than offering none.
     */
    protected function refundablePayment(Registration $registration): ?Payment
    {
        return $registration->payments()
            ->where('method', PaymentMethod::Stripe)
            ->where('status', PaymentStatus::Succeeded)
            ->latest('id')
            ->first();
    }
}
