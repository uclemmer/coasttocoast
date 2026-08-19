<?php

namespace App\Filament\Admin\Resources\FaqItemResource\Pages;

use App\Filament\Admin\Resources\FaqItemResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFaqItems extends ListRecords
{
    protected static string $resource = FaqItemResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
