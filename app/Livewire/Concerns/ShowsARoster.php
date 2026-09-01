<?php

namespace App\Livewire\Concerns;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\WithPagination;

/**
 * The public roster of attending colleges (R1.3, R1.4).
 *
 * Shared by the Representatives page and the Last Year page, which differ only
 * in **which fair** they read. That is the whole reason this is one piece of
 * code: the live site's Last Year page was showing the *current* roster when it
 * was reviewed (doc 00), which is exactly what happens when they are two.
 *
 * The query itself is `Registration::onRoster()` — confirmed, and not hidden by
 * the coordinator. An awaiting-payment organization has no business here: the roster
 * is a promise that the organization will be there.
 */
trait ShowsARoster
{
    use WithPagination;

    public string $search = '';

    /**
     * The fair this page is about. Null renders the empty state rather than
     * every registration ever taken.
     */
    abstract public function fair(): ?Event;

    /**
     * Reset to page one when the search changes, or searching from page three
     * shows an empty page.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Registration>
     */
    public function rosterProperty(): LengthAwarePaginator
    {
        $fair = $this->fair();

        if (! $fair instanceof Event) {
            return Registration::query()->whereRaw('1 = 0')->paginate(30);
        }

        return Registration::query()
            ->onRoster()
            ->where('registrations.event_id', $fair->getKey())
            ->with('organization')
            ->join('organizations', 'organizations.id', '=', 'registrations.organization_id')
            ->when(
                filled($this->search),
                fn ($query) => $query->where('organizations.name', 'like', '%'.trim($this->search).'%'),
            )
            ->orderBy('organizations.name')
            ->select('registrations.*')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * The initial for an organization with no logo (R1.3).
     *
     * Generated inline rather than fetched from an avatar service: a
     * third-party image would leak every visitor's request off-site and break
     * the page when that service is down, for a letter in a circle
     * (doc 10, D-5.3-c).
     */
    public function initialFor(?string $name): string
    {
        return Str::upper(Str::substr((string) $name, 0, 1)) ?: '?';
    }
}
