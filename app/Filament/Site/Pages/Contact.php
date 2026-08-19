<?php

namespace App\Filament\Site\Pages;

use App\Filament\Site\Concerns\RendersContentBlocks;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\RateLimiter;
use UClemmer\LaravelCore\Contact\ContactService;

/**
 * The contact page (card 5.4).
 *
 * The **form is ours; the work is laravel-core's.** Submitting calls
 * `ContactService::submit()`, which owns storage in `core_contact_submissions`,
 * user attribution, the `ContactSubmitted` event, the sender's receipt and the
 * organizer alert. Nothing about that logic is reimplemented here.
 *
 * What is not core's is the presentation. The package ships its form
 * "deliberately unstyled beyond structure" — its own words — which is right for
 * a package and wrong on a public page: embedded raw, it rendered as unbordered
 * inputs and a submit button that looked like plain text. Rebuilding it as a
 * Filament schema puts it in the same visual language as the rest of the site.
 *
 * **This also makes the consent checkbox real** (doc 10, D-5.4-a, now
 * resolved). Embedding core's form meant any checkbox we added would go
 * unvalidated, because core's controller validates only its own fields —
 * theatre rather than consent. Our form validates it before the service is ever
 * called.
 *
 * The abuse defences core's route provided are reimplemented here for the same
 * reason: a Livewire submit never touches that route, so its honeypot and
 * throttle would not have applied.
 */
class Contact extends Page
{
    use RendersContentBlocks;

    /**
     * The honeypot field. Rendered hidden; a human never sees it, and a naive
     * bot fills anything that looks like an input.
     */
    public const HONEYPOT = 'website';

    protected static ?int $navigationSort = 7;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Contact');
    }

    public function getTitle(): string
    {
        return __('Contact us');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ...$this->blocks(['contact.intro']),

            Section::make(__('The fair coordinator'))
                ->schema([static::prose($this->coordinatorBlock())]),

            Section::make(__('Send us a message'))
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('contact')
                        ->livewireSubmitHandler('submitContactForm')
                        ->footer([
                            Actions::make([
                                Action::make('send')
                                    ->label(__('Send message'))
                                    ->submit('submitContactForm'),
                            ]),
                        ]),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('name')
                    ->label(__('Your name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label(__('Your email'))
                    ->email()
                    ->required()
                    ->maxLength(255),

                TextInput::make('subject')
                    ->label(__('Subject'))
                    ->maxLength(255),

                Textarea::make('message')
                    ->label(__('Message'))
                    ->required()
                    ->rows(6)
                    ->maxLength(5000)
                    ->columnSpanFull(),

                Checkbox::make('consent')
                    ->label(__('I understand that my message will be stored so the fair can reply to it.'))
                    // Validated, so it means something. This is the whole
                    // reason the form is ours rather than the package's.
                    ->accepted()
                    ->required()
                    ->validationMessages([
                        'accepted' => __('Please confirm this so we can reply to you.'),
                    ])
                    ->columnSpanFull(),

                Hidden::make(self::HONEYPOT),
            ])
            ->columns(2);
    }

    public function submitContactForm(): void
    {
        $data = $this->form->getState();

        if (filled($data[self::HONEYPOT] ?? null)) {
            // Deliberately vague, so a bot cannot learn which field caught it.
            $this->refuse();

            return;
        }

        // Core's own route carries a throttle; a Livewire submit never reaches
        // it, so the same limit is applied here. Five an hour is far more than
        // any person needs and far fewer than a script wants.
        $key = 'contact:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            Notification::make()
                ->title(__('You have sent us several messages already. Please try again later.'))
                ->warning()
                ->send();

            return;
        }

        RateLimiter::hit($key, decaySeconds: 3600);

        app(ContactService::class)->submit(
            attributes: [
                'name' => $data['name'],
                'email' => $data['email'],
                'subject' => $data['subject'] ?? null,
                'message' => $data['message'],
            ],
            context: [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        );

        $this->form->fill();

        Notification::make()
            ->title(__('Thanks — your message is with us.'))
            ->body(__('You should have a confirmation by email, and the coordinator will reply.'))
            ->success()
            ->send();
    }

    protected function refuse(): void
    {
        Notification::make()
            ->title(__('Something went wrong. Please try again.'))
            ->danger()
            ->send();
    }

    /**
     * The contact block from `config/fair.php` — the same values the public
     * footer, the email footer and the check form use, so a change of address
     * lands everywhere at once.
     *
     * Returns HTML rather than newline-joined text: newlines inside a rendered
     * span collapse to spaces, which ran the whole address onto one line.
     */
    protected function coordinatorBlock(): string
    {
        $contact = (array) config('fair.contact');

        $lines = array_filter([
            e($contact['name'] ?? ''),
            e($contact['address_line1'] ?? ''),
            e($contact['address_line2'] ?: ''),
            e(trim(implode(' ', array_filter([
                ($contact['city'] ?? null) ? $contact['city'].',' : null,
                $contact['state'] ?? null,
                $contact['postal_code'] ?? null,
            ])))),
        ]);

        $links = array_filter([
            filled($contact['phone'] ?? null)
                ? '<a href="tel:'.e(preg_replace('/\D/', '', (string) $contact['phone']) ?? '').'">'.e($contact['phone']).'</a>'
                : null,
            filled($contact['email'] ?? null)
                ? '<a href="mailto:'.e($contact['email']).'">'.e($contact['email']).'</a>'
                : null,
        ]);

        return '<p>'.implode('<br>', $lines).'</p>'
            .($links === [] ? '' : '<p>'.implode(' · ', $links).'</p>');
    }

    /**
     * Exposed for the footer and for tests: the coordinator's number, dialable.
     */
    public function coordinatorPhone(): ?string
    {
        return Phone::forHumans((string) config('fair.contact.phone'))
            ?: (string) config('fair.contact.phone');
    }
}
