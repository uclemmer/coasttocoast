<?php

namespace App\Filament\Admin\Resources\RegistrationResource\Pages;

use App\Filament\Admin\Resources\RegistrationResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    /**
     * No DeleteAction. Registrations are never removed once payment exists
     * (doc 03, data lifecycle) — cancelling is the reversible equivalent and
     * leaves the audit trail.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }
}
