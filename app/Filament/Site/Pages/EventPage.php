<?php

namespace App\Filament\Site\Pages;

use App\Filament\Rep\Resources\RegistrationResource;
use App\Http\Requests\StoreEventInterestRequest;
use App\Models\Event;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * One fair's public page (card 5.4).
 *
 * The call to action is state-aware, and getting that right is the point of
 * this page. The current site shows "Registration is currently closed" for
 * most of the year with nowhere to go from there (doc 00), which loses every
 * college that finds the site out of season. Three states, three destinations:
 *
 *  - **open** → register;
 *  - **not yet open** → the date it opens, so they can diarise it;
 *  - **closed** → the interest form, so we can tell them next time.
 */
class EventPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $interestData = [];

    /**
     * Locked because it decides where an interest sign-up is written.
     *
     * Livewire re-hydrates a model property from its key on every request, so
     * without this a visitor could edit the payload and add themselves to a
     * different fair's list — small harm, but it is free to close.
     */
    #[Locked]
    public Event $event;

    public static function getRoutePath(Panel $panel): string
    {
        return '/events/{event:slug}';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'events';
    }

    public function mount(Event $event): void
    {
        // Unpublished fairs do not exist as far as the public is concerned —
        // 404 rather than 403, which would confirm the draft exists.
        abort_unless($event->is_published, 404);

        $this->event = $event;

        $this->form->fill();
    }

    public function getTitle(): string
    {
        return $this->event->name;
    }

    public function getSubheading(): string
    {
        return $this->event->starts_at->format('l, F j, Y').' · '.$this->event->venue_name;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components(array_filter([
            Section::make(__('The fair'))
                ->description($this->event->venue_address)
                ->schema([
                    Text::make($this->scheduleLine()),
                    Text::make(__('Registration for colleges and universities: :price', [
                        'price' => Money::format($this->event->price_cents),
                    ])),
                    Text::make(__('Free for students and families. No registration needed.')),
                ]),

            $this->callToAction(),
        ]));
    }

    protected function scheduleLine(): string
    {
        $parts = [__('Fair: :from to :to', [
            'from' => $this->event->starts_at->format('g:i A'),
            'to' => $this->event->ends_at->format('g:i A'),
        ])];

        if ($this->event->reception_starts_at) {
            $parts[] = __('Counselor reception from :time', [
                'time' => $this->event->reception_starts_at->format('g:i A'),
            ]);
        }

        return implode(' · ', $parts);
    }

    /**
     * The three states.
     */
    protected function callToAction(): Section
    {
        if ($this->event->isRegistrationOpen()) {
            return Section::make(__('Registration is open'))
                ->schema([
                    Text::make($this->closingLine()),
                    Actions::make([
                        Action::make('register')
                            ->label(__('Register your institution'))
                            ->url(RegistrationResource::getUrl('create', panel: 'rep')),
                    ]),
                ]);
        }

        if ($this->event->registrationNotYetOpen()) {
            return Section::make(__('Registration is not open yet'))
                ->schema([
                    Text::make(__('Registration opens on :date. Check back then, or leave us your email below.', [
                        'date' => $this->event->registration_opens_at?->format('l, F j, Y'),
                    ])),
                    $this->interestForm(),
                ]);
        }

        return Section::make(__('Registration has closed'))
            ->description(__('Leave us your email and we will tell you the moment it opens again.'))
            ->schema([$this->interestForm()]);
    }

    protected function closingLine(): string
    {
        return $this->event->registration_closes_at
            ? __('Registration closes on :date.', [
                'date' => $this->event->registration_closes_at->format('l, F j, Y'),
            ])
            : __('There is no closing date yet.');
    }

    protected function interestForm(): Form
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('interest')
            ->livewireSubmitHandler('registerInterest')
            ->footer([
                Actions::make([
                    Action::make('notifyMe')
                        ->label(__('Tell me when registration opens'))
                        ->submit('registerInterest'),
                ]),
            ]);
    }

    /**
     * Store the interest row.
     *
     * Goes through the model rather than the HTTP controller because this is a
     * Livewire submit, not a form post — but it applies the same rules: the
     * honeypot must be empty, and the address is lowercased so the same person
     * signing up twice is not mailed twice.
     */
    public function registerInterest(): void
    {
        $data = $this->form->getState();

        if (filled($data[StoreEventInterestRequest::HONEYPOT] ?? null)) {
            // Deliberately vague, so a bot cannot learn which field caught it.
            Notification::make()->title(__('Something went wrong. Please try again.'))->danger()->send();

            return;
        }

        $this->event->interests()->updateOrCreate(
            ['email' => Str::lower(trim((string) $data['email']))],
            ['organization_name' => $data['organization_name'] ?? null],
        );

        $this->form->fill();

        Notification::make()
            ->title(__('Thanks — we will email you as soon as registration opens.'))
            ->success()
            ->send();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('interestData')
            ->components([
                TextInput::make('email')
                    ->label(__('Your email'))
                    ->email()
                    ->required()
                    ->maxLength(255),

                TextInput::make('organization_name')
                    ->label(__('Your institution'))
                    ->maxLength(255)
                    ->helperText(__('Optional.')),

                // Invisible to a person, plausible to a naive bot. The same
                // field the HTTP endpoint rejects in StoreEventInterestRequest.
                Hidden::make(StoreEventInterestRequest::HONEYPOT),
            ]);
    }
}
