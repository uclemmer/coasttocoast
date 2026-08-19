<?php

namespace App\Filament\Admin\Resources\SponsorResource\Pages;

use App\Filament\Admin\Resources\SponsorResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSponsor extends EditRecord
{
    protected static string $resource = SponsorResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
