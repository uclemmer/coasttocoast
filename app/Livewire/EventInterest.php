<?php

namespace App\Livewire;

use App\Livewire\Concerns\ThrottlesPublicSubmissions;
use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * "Tell me when registration opens" (R2.7, card 8.4).
 *
 * The fix for the biggest hole in the current site: registration is shut for
 * most of the year and the page is a dead end (doc 00).
 *
 * Asks for as little as possible on purpose. This is the person who found the
 * site the week after registration closed; demanding an account, or the
 * official spelling of a school they may not work at yet, is how the lead is
 * lost.
 *
 * The same capture also exists as a plain POST route, which is the
 * non-JavaScript path. Both lowercase the address before writing, so somebody
 * who signs up as `Dana@` and then `dana@` is not both told they are on the
 * list and mailed twice (doc 10, D-5.4-b).
 */
class EventInterest extends Component
{
    use ThrottlesPublicSubmissions;

    /**
     * Locked because it decides which fair's list a sign-up lands on. Livewire
     * re-hydrates a model property from its key on every request.
     */
    #[Locked]
    public Event $event;

    #[Validate('required|email:rfc|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:255')]
    public string $organizationName = '';

    public bool $sent = false;

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('We need an email address to tell you.'),
            'email.email' => __('That does not look like an email address.'),
        ];
    }

    public function submit(): void
    {
        $this->validate();

        if ($this->rejectedAsAbuse(
            bucket: 'event-interest',
            errorField: 'email',
            throttleMessage: __('Please try again a little later.'),
        )) {
            return;
        }

        // `updateOrCreate` on the lowercased address is both the dedupe and the
        // "we heard you the first time" — and a second submission still
        // improves what we know, by filling in a school name the first left
        // blank.
        $this->event->interests()->updateOrCreate(
            ['email' => Str::lower(trim($this->email))],
            ['organization_name' => $this->organizationName ?: null],
        );

        $this->reset(['email', 'organizationName', 'website']);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.event-interest');
    }
}
