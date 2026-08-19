<?php

namespace App\Filament\Admin\Resources\GrantResource\Pages;

use App\Filament\Admin\Resources\GrantResource;
use Filament\Resources\Pages\ListRecords;

/**
 * No CreateAction: a grant is applied for by a school, never opened by the
 * coordinator on their behalf. See the resource docblock.
 */
class ListGrants extends ListRecords
{
    protected static string $resource = GrantResource::class;
}
