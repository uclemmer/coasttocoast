<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Validate;
use Livewire\Component;
use UClemmer\LaravelCore\Contact\ContactService;

/**
 * The contact page (card 8.4).
 *
 * **The form is ours; the work is laravel-core's.** Submitting calls
 * `ContactService::submit()`, which owns storage in `core_contact_submissions`,
 * user attribution, the `ContactSubmitted` event, the sender's receipt and the
 * organizer alert. None of that is reimplemented here.
 *
 * The abuse defences are ours because core's route carries them and a Livewire
 * submit never touches that route: the honeypot below, and an IP throttle. Both
 * carried over from the Filament build (doc 10, D-8-d), as did the consent
 * checkbox — which is validated here, before the service is ever called, so it
 * means something rather than being decoration.
 *
 * The design adds an Institution field the package does not store. It is folded
 * into the message body rather than dropped, and rather than migrating a column
 * onto a package table this app does not own.
 */
class ContactForm extends Component
{
    /**
     * The honeypot. Rendered off-screen rather than `type="hidden"` — bots
     * skip hidden inputs and fill visible ones, so this has to be visually
     * hidden but present.
     */
    public string $website = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email:rfc|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:255')]
    public string $institution = '';

    #[Validate('required|string|max:5000')]
    public string $message = '';

    #[Validate('accepted')]
    public bool $consent = false;

    public bool $sent = false;

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => __('Please confirm this so we can reply to you.'),
            'email.email' => __('That does not look like an email address.'),
        ];
    }

    public function submit(): void
    {
        $this->validate();

        if (filled($this->website)) {
            // Deliberately vague: a bot should not learn which field caught it.
            $this->addError('message', __('Something went wrong. Please try again.'));

            return;
        }

        $key = 'contact:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $this->addError('message', __('You have sent us several messages already. Please try again later.'));

            return;
        }

        RateLimiter::hit($key, decaySeconds: 3600);

        app(ContactService::class)->submit(
            attributes: [
                'name' => $this->name,
                'email' => $this->email,
                'subject' => __('Website enquiry'),
                'message' => $this->messageWithInstitution(),
            ],
            context: [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        );

        $this->reset(['name', 'email', 'institution', 'message', 'consent', 'website']);
        $this->sent = true;
    }

    /**
     * The institution is prepended to the message rather than lost.
     *
     * `core_contact_submissions` has no column for it and it is not this app's
     * table to migrate. A coordinator reading the message needs to know which
     * college is asking, and one line at the top does that.
     */
    protected function messageWithInstitution(): string
    {
        return blank($this->institution)
            ? $this->message
            : __('Institution: :institution', ['institution' => $this->institution])."\n\n".$this->message;
    }

    public function render(): View
    {
        return view('livewire.contact-form');
    }
}
