<?php

namespace App\Filament\Site\Pages;

use App\Models\FaqItem;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The FAQ (card 5.2).
 *
 * Collapsed sections, so somebody looking for parking is not scrolling past
 * ten answers to find it. Answers are markdown, written in the admin panel.
 */
class Faq extends Page
{
    protected static ?int $navigationSort = 6;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    public static function getNavigationLabel(): string
    {
        return __('FAQ');
    }

    public function getTitle(): string
    {
        return __('Frequently asked questions');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components(
            FaqItem::query()
                ->published()
                ->get()
                ->map(fn (FaqItem $item): Section => Section::make($item->question)
                    ->schema([Text::make(new HtmlString(Str::markdown($item->answer)))])
                    ->collapsible()
                    ->collapsed())
                ->all(),
        );
    }
}
