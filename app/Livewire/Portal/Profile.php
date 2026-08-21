<?php

namespace App\Livewire\Portal;

use App\Livewire\Portal\Concerns\ActsForAnOrganization;
use App\Services\OrganizationService;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * A representative's own details — the Livewire replacement for the rep panel's
 * EditProfile auth page (docs/12).
 *
 * Three things live here, and the third is the reason this page is not just a
 * form: name, email and phone; the SMS opt-in; and retiring as a representative.
 *
 * **Having a number is not consent (N3).** `sms_opt_in` is off by default and
 * is a separate, explicit act from supplying a phone number. Storing one for
 * fair-day logistics and texting somebody are different permissions, and the
 * page says so rather than implying it.
 *
 * Password change is optional here: leaving both fields empty saves everything
 * else. A profile page that demands the current password to change a phone
 * number teaches people to type their password into forms that did not need it.
 */
#[Layout('components.layouts.portal', ['title' => 'Your details', 'heading' => 'Your details'])]
class Profile extends Component
{
    use ActsForAnOrganization;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public bool $sms_opt_in = false;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = $this->currentUser();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->sms_opt_in = (bool) $user->sms_opt_in;
    }

    public function save(): void
    {
        $user = $this->currentUser();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! Phone::isValid(is_string($value) ? $value : null)) {
                        $fail(__('Enter a phone number we can actually dial, e.g. (423) 757-2845.'));
                    }
                },
            ],
            'sms_opt_in' => ['boolean'],
            // Optional. Empty means "leave it alone", which is why `nullable`
            // sits in front of `confirmed` rather than the field being required.
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => Phone::normalize($this->phone !== '' ? $this->phone : null),
            'sms_opt_in' => $this->sms_opt_in,
        ]);

        /*
         * Changing the address un-verifies it. Otherwise somebody could sign up
         * with an address they control, verify, and then move the account to
         * one they do not - which is the whole point of verifying.
         */
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($validated['password'] ?? null) {
            $user->password = Hash::make($validated['password']);
        }

        $sendVerification = $user->isDirty('email');

        $user->save();

        $this->reset('password', 'password_confirmation');

        if ($sendVerification) {
            $user->sendEmailVerificationNotification();
            $this->toast(__('Saved. Check your new address for a confirmation link.'));

            return;
        }

        $this->toast(__('Saved.'));
    }

    /**
     * Step down as a representative of this school.
     *
     * Keeps the account and the history; loses every right to act for the
     * school. `OrganizationService::retire()` owns what that means — this only
     * asks.
     */
    public function retire(OrganizationService $service): void
    {
        if (! $this->actsForOrganization()) {
            $this->notifyMembershipRefusal();

            return;
        }

        try {
            $service->retire($this->currentUser(), $this->currentUser());
        } catch (Throwable $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->dispatch('ui-modal-close', id: 'confirm-retire');
        $this->toast(__('You have stepped down as a representative.'));
    }

    public function render(): View
    {
        return view('livewire.portal.profile');
    }
}
