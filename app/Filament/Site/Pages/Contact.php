<?php

namespace App\Filament\Site\Pages;

use App\Filament\Site\Concerns\RendersContentBlocks;
use App\Support\Phone;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * The contact page (card 5.4).
 *
 * The form itself is laravel-core's — `<x-core::contact-form />`, embedded
 * here rather than rebuilt. Core owns the honeypot, the time-trap, the
 * throttle, storage in `core_contact_submissions`, the organizer alert and the
 * sender's receipt (`core.contact.*` in `config/core.php`, with
 * `routes.page => false` so this page is the only one).
 *
 * **Consent (doc 10, D-5.4-a).** Doc 02 asks for a consent checkbox on our
 * side. Core's controller validates only its own fields, so an extra checkbox
 * would be unvalidated — theatre rather than consent — and adding one properly
 * means a change in the `laravel-core` repo, which is out of scope for this
 * app (workspace rule: never edit a sibling project). The privacy notice is
 * therefore stated plainly above the form, where it is read before sending.
 * Flagged for the owner.
 */
class Contact extends Page
{
    use RendersContentBlocks;

    protected static ?int $navigationSort = 7;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    public static function getNavigationLabel(): string
    {
        return __('Contact');
    }

    public function getTitle(): string
    {
        return __('Contact us');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ...$this->blocks(['contact.intro']),

            Section::make(__('The fair coordinator'))
                ->schema([Text::make($this->coordinatorBlock())]),

            Section::make(__('Send us a message'))
                ->description(__(
                    'We store your message so we can reply to it, and we keep it for as long as we need '
                    .'to. We do not share it with anyone, and we will not add you to a mailing list.',
                ))
                ->schema([
                    View::make('core::components.contact-form'),
                ]),
        ]);
    }

    /**
     * The contact block from `config/fair.php` — the same values the public
     * footer, the email footer and the check form use, so a change of address
     * lands everywhere at once.
     */
    protected function coordinatorBlock(): string
    {
        $contact = (array) config('fair.contact');

        return implode("\n", array_filter([
            $contact['name'] ?? null,
            $contact['address_line1'] ?? null,
            $contact['address_line2'] ?: null,
            trim(implode(' ', array_filter([
                ($contact['city'] ?? null) ? $contact['city'].',' : null,
                $contact['state'] ?? null,
                $contact['postal_code'] ?? null,
            ]))) ?: null,
            $contact['phone'] ?? null,
            $contact['email'] ?? null,
        ]));
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
