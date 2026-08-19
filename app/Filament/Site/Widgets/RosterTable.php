<?php

namespace App\Filament\Site\Widgets;

use App\Models\Event;
use App\Models\Registration;
use App\Services\RosterService;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The public roster of attending schools (R1.3), shared by the Representatives
 * and Last Year pages (card 5.3).
 *
 * One widget for both, because they are the same query against different
 * events — and because the current site's Last Year page was showing the
 * *current* roster when it was reviewed (doc 00), which is exactly what
 * happens when they are two pieces of code.
 *
 * Subclasses choose the event; everything else, including which registrations
 * qualify, comes from `RosterService`.
 */
abstract class RosterTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    /**
     * Rendered with the page, not fetched afterwards.
     *
     * Filament lazy-loads widgets by default, which is right inside an admin
     * panel. It is wrong here: the roster is the page's whole content and its
     * main job is to be read — by a search engine, by a rep checking whether
     * their school is already listed, and by anyone whose JavaScript did not
     * run. A list that only exists after a round-trip is invisible to all three.
     */
    protected static bool $isLazy = false;

    abstract protected function rosterEvent(): ?Event;

    public function table(Table $table): Table
    {
        $event = $this->rosterEvent();

        return $table
            ->query(fn (): Builder => $this->rosterQuery($event))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->searchable()
            ->columns([
                ImageColumn::make('organization.logo_path')
                    ->label(__('Logo'))
                    ->disk('public')
                    ->height(40)
                    // The placeholder when a school has not uploaded one
                    // (R1.3): its initial, rather than a broken image or a
                    // ragged empty column.
                    ->defaultImageUrl(fn (Registration $record): string => $this->initialPlaceholder($record))
                    ->extraImgAttributes(fn (Registration $record): array => [
                        'loading' => 'lazy',
                        'alt' => (string) $record->organization?->name,
                    ]),

                TextColumn::make('organization.name')
                    ->label(__('Institution'))
                    ->searchable()
                    ->url(fn (Registration $record): ?string => $record->organization?->website)
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading($this->emptyHeading())
            ->emptyStateDescription($this->emptyDescription());
    }

    /**
     * Confirmed, not hidden, ordered by school name — `RosterService`'s
     * definition, reused rather than restated.
     */
    protected function rosterQuery(?Event $event): Builder
    {
        if (! $event instanceof Event) {
            return Registration::query()->whereRaw('1 = 0');
        }

        return Registration::query()
            ->onRoster()
            ->where('registrations.event_id', $event->getKey())
            ->with('organization')
            ->join('organizations', 'organizations.id', '=', 'registrations.organization_id')
            ->orderBy('organizations.name')
            ->select('registrations.*');
    }

    /**
     * An inline SVG data URI carrying the school's initial.
     *
     * Generated rather than fetched: an external avatar service would leak
     * every visitor's request to a third party and break the page when it is
     * down, for a letter in a circle.
     */
    protected function initialPlaceholder(Registration $registration): string
    {
        $initial = strtoupper(mb_substr((string) $registration->organization?->name, 0, 1)) ?: '?';

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40">
                <rect width="40" height="40" rx="20" fill="#e5e7eb"/>
                <text x="20" y="26" font-family="sans-serif" font-size="18" font-weight="600"
                      fill="#4b5563" text-anchor="middle">{$initial}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    protected function emptyHeading(): string
    {
        return __('No institutions listed yet');
    }

    protected function emptyDescription(): string
    {
        return __('Schools appear here as their registrations are confirmed.');
    }

    protected function rosterService(): RosterService
    {
        return app(RosterService::class);
    }
}
