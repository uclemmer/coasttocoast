<?php

namespace App\Filament\Rep\Resources\RegistrationResource\Pages;

use App\Filament\Rep\Concerns\ActsForAnOrganization;
use App\Filament\Rep\Resources\RegistrationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * The portal's landing list: everything the rep's SCHOOL has registered for,
 * not everything this person personally did (card 3.1).
 */
class ListRegistrations extends ListRecords
{
    use ActsForAnOrganization;

    protected static string $resource = RegistrationResource::class;

    public function getSubheading(): ?string
    {
        // The banner that explains a missing button. A page with no explanation
        // reads as broken rather than as pending.
        return $this->membershipNotice();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Register for a fair')),
        ];
    }
}
