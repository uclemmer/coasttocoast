<?php

namespace App\Filament\Admin\Resources\FaqItemResource\Pages;

use App\Filament\Admin\Resources\FaqItemResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFaqItem extends EditRecord
{
    protected static string $resource = FaqItemResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
