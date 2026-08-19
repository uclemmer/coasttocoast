<?php

namespace App\Filament\Rep\Pages\Auth;

use App\Models\User;
use App\Services\OrganizationService;
use App\Support\Phone;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Throwable;

/**
 * A representative's own account (card 3.1).
 *
 * Two things beyond name/email/password:
 *
 *  - **Phone and SMS opt-in.** Storing a number is not consent to use it
 *    (privacy N3), so the toggle is separate and defaults off. The number is
 *    normalised to E.164 on save rather than validated strictly — a rep typing
 *    `(423) 757-2845` should not be told to try again in a format nobody uses.
 *  - **Self-retire.** R2.10: a rep who moves on can step down without waiting
 *    on the coordinator. It is deliberately hard to do by accident — a
 *    confirmation modal, danger colouring, and wording that says what is lost
 *    and what is kept.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),

            TextInput::make('phone')
                ->label(__('Phone'))
                ->tel()
                ->maxLength(20)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Phone::isValid(is_string($value) ? $value : null)) {
                        $fail(__('Enter a phone number we can actually dial, e.g. (423) 757-2845.'));
                    }
                })
                ->dehydrateStateUsing(fn (?string $state): ?string => Phone::normalize($state))
                ->helperText(__('Used only for fair-day logistics, and only if you turn texts on below.')),

            Toggle::make('sms_opt_in')
                ->label(__('Text me fair-day reminders'))
                // Off by default, and having a number is not consent (N3).
                ->helperText(__('Parking, check-in and shipping details on the day. Nothing else, ever.')),

            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
        ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
            $this->retireAction(),
        ];
    }

    /**
     * Step down as a representative of this school.
     *
     * Keeps the account and the history; loses every right to act for the
     * school. Visible only to somebody who currently holds those rights.
     */
    public function retireAction(): Action
    {
        return Action::make('retire')
            ->label(__('I no longer represent this school'))
            ->color('danger')
            ->link()
            ->visible(fn (): bool => $this->currentRep()->actsForOrganization())
            ->requiresConfirmation()
            ->modalHeading(__('Step down as a representative?'))
            ->modalDescription(fn (): string => __(
                'You will keep your account and be able to see :school\'s past registrations, but you '
                .'will no longer be able to register it, apply for grants, or edit its details. The '
                .'coordinator can undo this.',
                ['school' => $this->currentRep()->organization?->name],
            ))
            ->modalSubmitActionLabel(__('Yes, step down'))
            ->action(function (): void {
                $rep = $this->currentRep();

                try {
                    app(OrganizationService::class)->retire($rep, $rep);

                    Notification::make()->title(__('You have stepped down.'))->success()->send();
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    protected function currentRep(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
