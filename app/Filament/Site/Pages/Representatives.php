<?php

namespace App\Filament\Site\Pages;

use App\Filament\Site\Concerns\RendersContentBlocks;
use App\Filament\Site\Widgets\CurrentRoster;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Who is coming this year (R1.3, card 5.3).
 *
 * Doubles as social proof and as a duplicate check — a rep who finds their
 * school already listed knows not to register it twice.
 */
class Representatives extends Page
{
    use RendersContentBlocks;

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    public static function getNavigationLabel(): string
    {
        return __('Representatives');
    }

    public function getTitle(): string
    {
        return __('Participating institutions');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ...$this->blocks(['representatives.intro']),
            ...$this->getWidgetsSchemaComponents([CurrentRoster::class]),
        ]);
    }
}
