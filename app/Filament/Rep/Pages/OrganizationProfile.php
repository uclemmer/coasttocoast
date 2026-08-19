<?php

namespace App\Filament\Rep\Pages;

use App\Filament\Rep\Concerns\ActsForAnOrganization;
use App\Models\Organization;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

/**
 * A school editing its own details (card 3.1).
 *
 * Active reps only. A pending or retired rep reaching this URL gets a 403
 * carrying the explanation, not a blank refusal — "not allowed" with no reason
 * is how somebody concludes the site is broken.
 *
 * The admissions email is worth the helper text it gets: it is what campaigns
 * fall back to when a school has no active rep, which is exactly the situation
 * a school in the middle of a staff change is about to be in.
 */
class OrganizationProfile extends Page
{
    use ActsForAnOrganization;

    protected static ?int $navigationSort = 30;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Your school');
    }

    public function getTitle(): string
    {
        return $this->currentOrganization()?->name ?? __('Your school');
    }

    /**
     * Hidden entirely from anyone with no school. Reps who are pending or
     * retired still see it, because reading their school's details is
     * reasonable and the form itself is what refuses them.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->organization_id !== null;
    }

    public function mount(): void
    {
        $this->abortUnlessActingForOrganization();

        $this->form->fill($this->currentOrganization()?->toArray() ?? []);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('School'))
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('website')->url()->maxLength(255),
                        FileUpload::make('logo_path')
                            ->label(__('Logo'))
                            ->image()
                            ->disk('public')
                            ->directory('organization-logos')
                            ->maxSize(2048)
                            ->helperText(__('Shown beside your name on the public roster.')),
                    ])
                    ->columns(2),

                Section::make(__('Admissions contact'))
                    ->description(__('A general address and number for your office. We use these to reach your school if nobody there has an account with us.'))
                    ->schema([
                        TextInput::make('admissions_office')->maxLength(255),
                        TextInput::make('admissions_email')->email()->maxLength(255),
                        TextInput::make('admissions_phone')
                            ->tel()
                            ->maxLength(20)
                            ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                if (! Phone::isValid(is_string($value) ? $value : null)) {
                                    $fail(__('Enter a phone number we can actually dial, e.g. (423) 757-2845.'));
                                }
                            }),
                    ])
                    ->columns(3),

                Section::make(__('Address'))
                    ->schema([
                        TextInput::make('address_line1')->label(__('Address'))->maxLength(255),
                        TextInput::make('address_line2')->label(__('Address line 2'))->maxLength(255),
                        TextInput::make('city')->maxLength(255),
                        TextInput::make('state')->maxLength(255),
                        TextInput::make('postal_code')->label(__('ZIP'))->maxLength(20),
                    ])
                    ->columns(2),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ...($this->membershipNotice() === null ? [] : [
                Section::make(__('Heads up'))
                    ->schema([Text::make($this->membershipNotice())]),
            ]),

            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([$this->saveAction()])->key('form-actions'),
                ]),
        ]);
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label(__('Save'))
            ->submit('save');
    }

    public function save(): void
    {
        $this->abortUnlessActingForOrganization();

        $organization = $this->currentOrganization();

        if (! $organization instanceof Organization) {
            return;
        }

        $data = $this->form->getState();
        $data['admissions_phone'] = Phone::normalize($data['admissions_phone'] ?? null);

        // `name` is deliberately included: schools rebrand, and the model
        // re-derives `normalized_name` on save so the duplicate check and the
        // roster import keep working.
        $organization->fill($data)->save();

        Notification::make()->title(__('Saved.'))->success()->send();
    }
}
