<?php

namespace App\Livewire\Staff\Events;

use App\Livewire\Staff\Concerns\ActsForStaff;
use App\Models\Event;
use App\Support\Money;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Add or edit a fair (R3.2) — replaces the admin panel's CreateEvent and
 * EditEvent pages (docs/13).
 *
 * TWO THINGS HERE ARE LOAD-BEARING, both carried over from `EventResource`'s
 * docblock because neither is cosmetic.
 *
 * **Money is entered in dollars and stored in cents.** The field is dollars
 * because that is what a coordinator types; the column is an integer because
 * floating-point money is how you lose a cent per registration. `Money` owns
 * both directions.
 *
 * The Filament version put that conversion on the *field*
 * (`formatStateUsing`/`dehydrateStateUsing`) and its comment records why: a
 * field marked `dehydrated(false)` never reaches `mutateFormDataBeforeCreate()`
 * at all, so doing it in the page classes **silently saved every fair at zero**.
 * The Livewire equivalent of that mistake is converting in one of mount/save
 * and not the other, which is why both directions are in this one class, next
 * to each other, and why a test asserts the stored integer rather than the
 * rendered string.
 *
 * **The publish toggle is what lets a fair take money.** An unpublished fair is
 * never registration-open, whatever its window says.
 */
#[Layout('components.layouts.staff', ['title' => 'Fair'])]
class Edit extends Component
{
    use ActsForStaff;

    public ?Event $event = null;

    public string $name = '';

    public string $slug = '';

    public string $venue_name = '';

    public string $venue_address = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public string $reception_starts_at = '';

    /** Dollars, as typed. Cents on the way in and out — see the class note. */
    public string $priceDollars = '';

    public string $capacity = '';

    public string $registration_opens_at = '';

    public string $registration_closes_at = '';

    public bool $is_published = false;

    public function mount(?Event $event = null): void
    {
        $this->abortUnlessStaff();

        if (! $event?->exists) {
            $this->authorize('create', Event::class);

            return;
        }

        $this->authorize('update', $event);

        $this->event = $event;
        $this->name = $event->name;
        $this->slug = $event->slug;
        $this->venue_name = $event->venue_name;
        $this->venue_address = $event->venue_address;
        $this->starts_at = $this->forInput($event->starts_at);
        $this->ends_at = $this->forInput($event->ends_at);
        $this->reception_starts_at = $this->forInput($event->reception_starts_at);
        $this->priceDollars = number_format(Money::toDollars($event->price_cents), 2, '.', '');
        $this->capacity = $event->capacity === null ? '' : (string) $event->capacity;
        $this->registration_opens_at = $this->forInput($event->registration_opens_at);
        $this->registration_closes_at = $this->forInput($event->registration_closes_at);
        $this->is_published = $event->is_published;
    }

    public function isEditing(): bool
    {
        return $this->event?->exists === true;
    }

    /**
     * Suggest a slug from the name, **while creating only**.
     *
     * Changing a slug after the fact breaks every link anyone has shared, so
     * the suggestion stops the moment the fair exists. Filament expressed this
     * as `live(onBlur: true)` plus an `$operation === 'create'` guard.
     */
    public function updatedName(): void
    {
        if (! $this->isEditing() && filled($this->name)) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255',
                Rule::unique('events', 'slug')->ignore($this->event?->getKey()),
            ],
            'venue_name' => ['required', 'string', 'max:255'],
            'venue_address' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reception_starts_at' => ['nullable', 'date'],
            'priceDollars' => ['required', 'numeric', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after:registration_opens_at'],
            /*
             * Refuse to publish a fair still carrying its placeholder name.
             *
             * `EventSeeder` writes the next fair as "College Fair 2027 (date and
             * price not yet confirmed — TODO-OWNER)" and leaves it unpublished,
             * because an unpublished fair can never take money. Nothing guarded
             * the other half: publishing without renaming puts that string on
             * the PUBLIC roster page, which renders the active fair's name as
             * its heading. Found in a browser pass — the development database
             * publishes 2027 for its fixtures, and there it was in the design's
             * display type (doc 10, D-9-e).
             *
             * A refusal rather than a warning, and attached to the toggle rather
             * than to the name, because publishing is the thing being refused.
             * The placeholder says in its own words that the date and price are
             * unconfirmed, so a fair wearing it is never one that should be
             * taking registrations.
             */
            'is_published' => ['boolean', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value && str_contains($this->name, 'TODO-OWNER')) {
                    $fail(__(
                        'This fair still has its placeholder name, which would show on the public '
                        .'site. Give it its real name before publishing it.',
                    ));
                }
            }],
        ]);

        $event = $this->event;

        if ($event?->exists) {
            $this->authorize('update', $event);
        } else {
            $this->authorize('create', Event::class);
            $event = new Event;
        }

        $event->fill([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'venue_name' => $validated['venue_name'],
            'venue_address' => $validated['venue_address'],
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'reception_starts_at' => $this->blankToNull($this->reception_starts_at),
            // The one conversion. See the class note.
            'price_cents' => Money::toCents($this->priceDollars),
            'capacity' => $this->capacity === '' ? null : (int) $this->capacity,
            'registration_opens_at' => $this->blankToNull($this->registration_opens_at),
            'registration_closes_at' => $this->blankToNull($this->registration_closes_at),
            'is_published' => $this->is_published,
        ]);

        $event->save();

        $this->event = $event;

        session()->flash('status', __('Fair saved.'));

        $this->redirect(route('staff.events.show', $event), navigate: false);
    }

    /** `datetime-local` wants `Y-m-d\TH:i` and nothing else. */
    protected function forInput(mixed $value): string
    {
        return $value === null ? '' : $value->format('Y-m-d\TH:i');
    }

    protected function blankToNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * Name every field as the form labels it, or as close as a sentence allows.
     *
     * The datetimes are where the label cannot be reused verbatim: they are
     * labelled "Fair opens" and "Fair closes", which are clauses rather than
     * nouns, and "the fair opens field is required" is not English. They take
     * the noun the label implies instead. `priceDollars` and the rest do reuse
     * their labels, because those already read as nouns.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'starts_at' => __('opening time'),
            'ends_at' => __('closing time'),
            'reception_starts_at' => __('reception start time'),
            'registration_opens_at' => __('registration opening time'),
            'registration_closes_at' => __('registration closing time'),
            'priceDollars' => __('registration fee'),
        ];
    }

    public function render(): View
    {
        return view('livewire.staff.events.edit', [
            'pageHeading' => $this->isEditing() ? $this->event->name : __('Add a fair'),
        ]);
    }
}
