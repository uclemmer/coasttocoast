<?php

namespace App\Filament\Admin\Resources\SponsorResource\Pages;

use App\Filament\Admin\Resources\SponsorResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSponsors extends ListRecords
{
    protected static string $resource = SponsorResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
