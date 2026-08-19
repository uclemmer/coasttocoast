<?php

namespace App\Filament\Admin\Resources\EventResource\Pages;

use App\Filament\Admin\Resources\EventResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Nothing to mutate here: the fee field converts dollars to cents through
 * `dehydrateStateUsing()` on the component itself, so create and edit cannot
 * disagree about it.
 */
class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;
}
