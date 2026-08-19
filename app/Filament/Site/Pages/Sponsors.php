<?php

namespace App\Filament\Site\Pages;

use App\Filament\Site\Concerns\RendersContentBlocks;
use App\Models\Sponsor;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

/**
 * The four schools that organize and underwrite the fair (card 5.2).
 *
 * One section per sponsor, in the coordinator's chosen order rather than
 * alphabetical — sponsors pay for billing position.
 */
class Sponsors extends Page
{
    use RendersContentBlocks;

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    public static function getNavigationLabel(): string
    {
        return __('Sponsors');
    }

    public function getTitle(): string
    {
        return __('Sponsors');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ...$this->blocks(['sponsors.intro']),

            ...Sponsor::query()
                ->ordered()
                ->with('staff')
                ->get()
                ->map(fn (Sponsor $sponsor): Section => Section::make($sponsor->name)
                    ->description($sponsor->website)
                    ->schema(
                        $sponsor->staff->isEmpty()
                            ? []
                            : [Text::make($sponsor->staff
                                ->map(fn ($person): string => trim($person->name.($person->title ? ' — '.$person->title : '')))
                                ->join("\n"))],
                    ))
                ->all(),
        ]);
    }
}
