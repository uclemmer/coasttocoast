<?php

namespace App\Filament\Site\Pages;

use App\Filament\Site\Concerns\RendersContentBlocks;
use App\Models\Event;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

/**
 * The front page (card 5.2).
 *
 * Leads with the next fair's date and the call to action a representative came
 * for. The current site buries both (doc 00: "Pricing, deadlines, and what the
 * fee includes are scattered or missing entirely"), and a college rep deciding
 * whether to come should not have to hunt.
 */
class Home extends Page
{
    use RendersContentBlocks;

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public static function getSlug(?Panel $panel = null): string
    {
        return '/';
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public static function getNavigationLabel(): string
    {
        return __('Home');
    }

    public function getTitle(): string
    {
        return (string) config('core.admin.brand', config('app.name'));
    }

    public function getSubheading(): ?string
    {
        $fair = Event::active();

        return $fair instanceof Event
            ? $fair->starts_at->format('l, F j, Y').' · '.$fair->venue_name
            : null;
    }

    public function content(Schema $schema): Schema
    {
        $fair = Event::active();

        return $schema->components([
            ...$this->blocks(['home.hero']),

            ...($fair instanceof Event ? [
                Section::make($fair->name)
                    ->description($fair->venue_address)
                    ->schema([
                        Text::make($this->fairSummary($fair)),
                        Actions::make(array_filter([
                            $this->eventAction($fair),
                        ])),
                    ]),
            ] : []),

            ...$this->blocks(['home.for_representatives']),
        ]);
    }

    /**
     * Everything a representative needs before deciding: when, how much, and
     * whether they can still get in.
     */
    protected function fairSummary(Event $fair): string
    {
        $lines = [
            __('Fair: :from to :to', [
                'from' => $fair->starts_at->format('g:i A'),
                'to' => $fair->ends_at->format('g:i A'),
            ]),
        ];

        if ($fair->reception_starts_at) {
            $lines[] = __('Counselor reception from :time', [
                'time' => $fair->reception_starts_at->format('g:i A'),
            ]);
        }

        $lines[] = __('Registration for colleges: :price', [
            'price' => Money::format($fair->price_cents),
        ]);

        $lines[] = __('Free for students and families. No registration needed.');

        return implode(' · ', $lines);
    }

    protected function eventAction(Event $fair): ?Action
    {
        return Action::make('event')
            ->label(__('About this fair'))
            ->url(EventPage::getUrl(['event' => $fair->slug], panel: 'site'));
    }
}
