<?php

namespace App\Filament\Rep\Pages\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Signing up as a representative (D9).
 *
 * Two paths, and the asymmetry between them is the design:
 *
 *  - **Claim an existing school** → membership `pending`. Anyone can say they
 *    represent Vanderbilt, and on the other side of that claim sit the
 *    school's registration history, its grants and its place on the roster.
 *    A coordinator approves.
 *  - **Add a new school** → membership `active` immediately. There is nobody
 *    to vouch for a school only this person knows about, so making them wait
 *    would mean waiting on nothing. The coordinator is alerted, with the
 *    duplicate warning attached.
 *
 * The duplicate check warns and does not block (R2.7). "Boston University" and
 * "Boston College" normalize differently on purpose, but near-misses are real
 * and a false positive that stops a school registering is worse than one the
 * coordinator merges later.
 */
class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('About you'))
                ->schema([
                    $this->getNameFormComponent(),
                    $this->getEmailFormComponent(),
                    TextInput::make('phone')
                        ->label(__('Phone'))
                        ->tel()
                        ->maxLength(20)
                        ->helperText(__('Optional. Used for fair-day logistics only, and only if you opt in later.')),
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                ]),

            Section::make(__('Your school'))
                ->schema([
                    Radio::make('organization_choice')
                        ->label(__('Is your school already registered with us?'))
                        ->options([
                            'claim' => __('Yes — find it in the list'),
                            'create' => __('No — add it'),
                        ])
                        ->default('claim')
                        ->required()
                        ->live(),

                    Select::make('organization_id')
                        ->label(__('Your school'))
                        ->searchable()
                        ->required()
                        ->visible(fn (Get $get): bool => $get('organization_choice') === 'claim')
                        ->getSearchResultsUsing(fn (string $search): array => Organization::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => Organization::query()->find($value)?->name)
                        ->helperText(__('Your account will be active once the fair coordinator confirms you work there.'))
                        ->dehydrated(false),

                    TextInput::make('organization_name')
                        ->label(__('School name'))
                        ->required()
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('organization_choice') === 'create')
                        ->live(onBlur: true)
                        // Warns; never blocks. A false positive that stops a
                        // school registering is worse than one merged later.
                        ->helperText(fn (?string $state): ?string => static::duplicateWarning($state))
                        ->dehydrated(false),

                    TextInput::make('organization_website')
                        ->label(__('School website'))
                        ->url()
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('organization_choice') === 'create')
                        ->dehydrated(false),

                    TextInput::make('organization_admissions_email')
                        ->label(__('Admissions office email'))
                        ->email()
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('organization_choice') === 'create')
                        ->helperText(__('A general address we can use if nobody from your school has an account with us.'))
                        ->dehydrated(false),
                ]),
        ]);
    }

    /**
     * Create the account, then attach it to a school through
     * `OrganizationService` — which owns which path makes somebody active and
     * which makes them wait.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        $choice = $this->data['organization_choice'] ?? 'claim';

        /** @var User $user */
        $user = $this->getUserModel()::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
        ]);

        $service = app(OrganizationService::class);

        if ($choice === 'create') {
            $service->createWithFounder([
                'name' => $this->data['organization_name'],
                'website' => $this->data['organization_website'] ?? null,
                'admissions_email' => $this->data['organization_admissions_email'] ?? null,
            ], $user);

            return $user->refresh();
        }

        $service->claim(
            Organization::query()->findOrFail($this->data['organization_id']),
            $user,
        );

        return $user->refresh();
    }

    protected static function duplicateWarning(?string $name): ?string
    {
        if (blank($name)) {
            return null;
        }

        $matches = Organization::query()->matchingName($name)->pluck('name');

        return $matches->isEmpty()
            ? null
            : __('We already have :names. If that is your school, go back and choose "yes" instead.', [
                'names' => $matches->join(', '),
            ]);
    }
}
