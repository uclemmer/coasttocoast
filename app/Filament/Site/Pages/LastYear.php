<?php

namespace App\Filament\Site\Pages;

use App\Filament\Site\Concerns\RendersContentBlocks;
use App\Filament\Site\Widgets\PreviousRoster;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;

/**
 * Last year's roster (R1.4, card 5.3).
 */
class LastYear extends Page
{
    use RendersContentBlocks;

    protected static ?int $navigationSort = 4;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'last-year';
    }

    public static function getNavigationLabel(): string
    {
        return __('Last year');
    }

    public function getTitle(): string
    {
        return __('Last year at the fair');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ...$this->blocks(['last_year.intro']),
            ...$this->getWidgetsSchemaComponents([PreviousRoster::class]),
        ]);
    }
}
