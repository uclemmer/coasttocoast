<?php

namespace App\Filament\Site\Pages;

use App\Filament\Site\Concerns\RendersContentBlocks;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * About the fair (card 5.2). Entirely content blocks, so the coordinator owns
 * every word without a deploy.
 */
class About extends Page
{
    use RendersContentBlocks;

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-information-circle';

    public static function getNavigationLabel(): string
    {
        return __('About');
    }

    public function getTitle(): string
    {
        return __('About the fair');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components($this->blocks(['about.body']));
    }
}
