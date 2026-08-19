<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Admin\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Registration;
use App\Services\RegistrationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    /**
     * Manual entry goes through `RegistrationService::createManualEntry()`
     * rather than `Registration::create()`.
     *
     * That is what keeps one set of rules in the application. A coordinator
     * entering a registration by hand still gets the duplicate check and the
     * grant-aware price snapshot; what she skips — the membership gate and the
     * registration window — she skips because the service was asked to, not
     * because this page took a different route to the database.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $event = Event::query()->findOrFail($data['event_id']);
        $organization = Organization::query()->findOrFail($data['organization_id']);

        try {
            return app(RegistrationService::class)->createManualEntry(
                event: $event,
                organization: $organization,
                contact: [
                    'rep_name' => $data['rep_name'],
                    'rep_email' => $data['rep_email'],
                    'rep_phone' => $data['rep_phone'] ?? null,
                ],
                method: isset($data['payment_method'])
                    ? PaymentMethod::from($data['payment_method'])
                    : null,
                notes: $data['notes'] ?? null,
            );
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            // Surface the refusal on the field it belongs to rather than
            // letting Filament report a generic failure — a duplicate is about
            // the school, a missing method is about the method.
            throw ValidationException::withMessages([
                'data.organization_id' => $e->getMessage(),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        /** @var Registration $record */
        $record = $this->getRecord();

        return static::getResource()::getUrl('view', ['record' => $record]);
    }
}
