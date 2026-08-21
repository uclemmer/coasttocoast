<?php

namespace App\Livewire\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Signing up as a representative (D9) — the Livewire replacement for the
 * Filament rep panel's Register page (docs/12).
 *
 * **The two paths, and the asymmetry between them, are the design:**
 *
 *  - **Claim an existing school** → membership `pending`. Anyone can say they
 *    represent Vanderbilt, and on the other side of that claim sit the school's
 *    registration history, its grants and its place on the roster. A
 *    coordinator approves.
 *  - **Add a new school** → membership `active` immediately. There is nobody to
 *    vouch for a school only this person knows about, so making them wait would
 *    mean waiting on nothing. The coordinator is alerted, with the duplicate
 *    warning attached.
 *
 * That rule is not implemented here. `OrganizationService::claim()` and
 * `::createWithFounder()` own it, exactly as they did for the Filament page —
 * this component collects fields and calls them. Which path makes somebody
 * active is one decision living in one place.
 *
 * The duplicate check **warns and never blocks** (R2.7). "Boston University"
 * and "Boston College" normalize differently on purpose, but near-misses are
 * real, and a false positive that stops a school registering is worse than one
 * a coordinator merges later.
 *
 * WHY THE SCHOOL PICKER IS A SEARCH BOX AND A LIST rather than a `<select>`:
 * Filament gave this a server-searching select for free. A plain select would
 * mean rendering every school in the country into the page, and a `datalist`
 * cannot tell us which row was chosen — only what was typed, which is not an
 * id. So the search runs server-side, capped, and the chosen school is held as
 * an id the user can see and clear.
 */
#[Layout('components.layouts.auth', ['title' => 'Create your account', 'width' => 'xl'])]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** Either `claim` or `create`. Drives which half of the form shows. */
    public string $organization_choice = 'claim';

    /** Claim path: what the user typed, and which school they settled on. */
    public string $organization_search = '';

    public ?int $organization_id = null;

    /** Create path. */
    public string $organization_name = '';

    public string $organization_website = '';

    public string $organization_admissions_email = '';

    /**
     * The honeypot. Rendered off-screen rather than `type="hidden"` — bots skip
     * hidden inputs and fill visible ones. Same defence as ContactForm, and for
     * the same reason: a Livewire submit never touches a throttled route.
     */
    public string $website = '';

    /**
     * Schools matching what has been typed, for the claim path.
     *
     * Capped at 25. An uncapped `like` over every school is a slow query and an
     * unusable list; somebody who cannot find their school in 25 needs a better
     * search term, not more rows.
     *
     * @return Collection<int, Organization>
     */
    #[Computed]
    public function matches()
    {
        if (strlen(trim($this->organization_search)) < 2) {
            return collect();
        }

        return Organization::query()
            ->where('name', 'like', '%'.trim($this->organization_search).'%')
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name']);
    }

    /** The school currently chosen on the claim path, if any. */
    #[Computed]
    public function chosen(): ?Organization
    {
        return $this->organization_id === null
            ? null
            : Organization::query()->find($this->organization_id);
    }

    /**
     * Schools whose normalized name already matches what is being typed on the
     * create path. Warns; never blocks.
     *
     * @return Collection<int, string>
     */
    #[Computed]
    public function duplicateWarning()
    {
        return blank($this->organization_name)
            ? collect()
            : Organization::query()->matchingName($this->organization_name)->pluck('name');
    }

    public function choose(int $id): void
    {
        $this->organization_id = $id;
        $this->organization_search = '';
    }

    public function clearChoice(): void
    {
        $this->organization_id = null;
    }

    public function register(OrganizationService $service): mixed
    {
        // Silently accept and go nowhere: a bot that filled the honeypot should
        // not learn that it was caught.
        if (filled($this->website)) {
            return redirect()->intended('/portal');
        }

        $throttle = 'register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttle, maxAttempts: 5)) {
            $this->addError('email', __('Too many attempts. Please try again in a few minutes.'));

            return null;
        }

        RateLimiter::hit($throttle, decaySeconds: 600);

        $validated = $this->validate($this->rules());

        /*
         * One transaction over the account and the membership. A user created
         * without a school is an account that can log in and do nothing, with
         * no path back to either registration branch — worse than a failed
         * signup, because the email address is now taken.
         */
        $user = DB::transaction(function () use ($validated, $service): User {
            /** @var User $user */
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'phone' => $this->phone !== '' ? $this->phone : null,
            ]);

            if ($this->organization_choice === 'create') {
                $service->createWithFounder([
                    'name' => $validated['organization_name'],
                    'website' => $this->organization_website !== '' ? $this->organization_website : null,
                    'admissions_email' => $this->organization_admissions_email !== ''
                        ? $this->organization_admissions_email
                        : null,
                ], $user);
            } else {
                $service->claim(
                    Organization::query()->findOrFail($validated['organization_id']),
                    $user,
                );
            }

            return $user->refresh();
        });

        event(new Registered($user));

        Auth::login($user);

        /*
         * `session()`, not `request()->session()`: a Livewire request does not
         * always carry a session on the request object, and reaching through it
         * throws "Session store not set on request" in exactly the place a
         * successful signup should be finishing.
         */
        session()->regenerate();

        return redirect()->intended('/portal');
    }

    /**
     * Validation depends on which path is chosen, which is why these are not
     * `#[Validate]` attributes: an attribute cannot be conditional, and marking
     * both `organization_id` and `organization_name` required would make each
     * path fail on the other's field.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $claiming = $this->organization_choice === 'claim';

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'organization_choice' => ['required', 'in:claim,create'],

            'organization_id' => [
                $claiming ? 'required' : 'nullable',
                'integer',
                'exists:organizations,id',
            ],

            'organization_name' => [$claiming ? 'nullable' : 'required', 'string', 'max:255'],
            'organization_website' => ['nullable', 'url', 'max:255'],
            'organization_admissions_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'organization_id' => __('school'),
            'organization_name' => __('school name'),
            'organization_website' => __('school website'),
            'organization_admissions_email' => __('admissions office email'),
        ];
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
