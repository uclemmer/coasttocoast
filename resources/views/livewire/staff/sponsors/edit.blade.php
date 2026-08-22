{{--
    Add or edit a sponsor, and the staff listed under it (docs/13).

    The heading comes from the component rather than the layout: `#[Layout]`'s
    data is attribute arguments and cannot carry the sponsor's name.
--}}
<div>
    <x-ui::action-bar :heading="$pageHeading"
        :description="__('Sponsors and their counseling staff appear on the public Sponsors page.')">
        <x-ui::button href="{{ route('staff.sponsors') }}" variant="secondary">
            {{ __('Back to sponsors') }}
        </x-ui::button>
    </x-ui::action-bar>

    <form wire:submit="save" class="mt-6 max-w-2xl">
        <x-ui::section :heading="__('Details')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui::forms.input name="name" wire:model="name" :label="__('Name')" required />
                <x-ui::forms.input name="website" wire:model="website" type="url" :label="__('Website')"
                    :hint="__('Optional.')" />
            </div>

            <div class="mt-4">
                @if ($this->isEditing() && $sponsor->logo_path && ! $removeLogo)
                    <div class="mb-3 flex items-center gap-3">
                        <img src="{{ Storage::disk('public')->url($sponsor->logo_path) }}" alt=""
                            class="h-12 w-12 rounded-base object-cover">
                        <x-ui::forms.checkbox name="removeLogo" wire:model.live="removeLogo"
                            :label="__('Remove this logo when I save')" />
                    </div>
                @endif

                {{-- `wire:model` rather than `.live`: the upload starts on
                     change either way, and `.live` would additionally round-trip
                     every other field on the form with it. --}}
                <x-ui::forms.file name="logo" wire:model="logo" accept="image/*" :label="__('Logo')"
                    :hint="__('An image, up to 2 MB.')" />

                <div wire:loading wire:target="logo" class="mt-2 text-sm text-body">
                    {{ __('Uploading…') }}
                </div>
            </div>
        </x-ui::section>

        <div class="mt-6 flex items-center gap-3">
            <x-ui::button type="submit" variant="brand">{{ __('Save sponsor') }}</x-ui::button>
            <span wire:loading wire:target="save" class="text-sm text-body">{{ __('Saving…') }}</span>
        </div>
    </form>

    @if ($this->isEditing())
        <div class="mt-10 max-w-2xl">
            <x-ui::action-bar :heading="__('Counseling staff')" level="h2"
                :description="__('Listed under this sponsor on the public page, in this order.')">
                <x-ui::button size="sm" variant="secondary" wire:click="addStaff">
                    {{ __('Add a person') }}
                </x-ui::button>
            </x-ui::action-bar>

            <div class="mt-4">
                <x-ui::table>
                    <x-ui::table.head>
                        <x-ui::table.heading>{{ __('Name') }}</x-ui::table.heading>
                        <x-ui::table.heading>{{ __('Title') }}</x-ui::table.heading>
                        <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
                    </x-ui::table.head>

                    @forelse ($this->staff as $member)
                        <x-ui::table.row wire:key="staff-{{ $member->id }}">
                            <x-ui::table.cell header>{{ $member->name }}</x-ui::table.cell>
                            <x-ui::table.cell>
                                {{ $member->title ?: '—' }}
                            </x-ui::table.cell>
                            <x-ui::table.cell>
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui::button size="xs" variant="ghost"
                                        wire:click="moveStaffUp({{ $member->id }})" :disabled="$loop->first">
                                        <span class="sr-only">{{ __('Move :name up', ['name' => $member->name]) }}</span>
                                        <span aria-hidden="true">&uarr;</span>
                                    </x-ui::button>
                                    <x-ui::button size="xs" variant="ghost"
                                        wire:click="moveStaffDown({{ $member->id }})" :disabled="$loop->last">
                                        <span class="sr-only">{{ __('Move :name down', ['name' => $member->name]) }}</span>
                                        <span aria-hidden="true">&darr;</span>
                                    </x-ui::button>
                                    <x-ui::button size="xs" variant="secondary"
                                        wire:click="editStaff({{ $member->id }})">{{ __('Edit') }}</x-ui::button>
                                    <x-ui::button size="xs" variant="ghost"
                                        wire:click="confirmDeleteStaff({{ $member->id }})">{{ __('Remove') }}</x-ui::button>
                                </div>
                            </x-ui::table.cell>
                        </x-ui::table.row>
                    @empty
                        <x-ui::table.row>
                            <x-ui::table.empty-state :colspan="3" :heading="__('Nobody listed yet')">
                                {{ __('Add the counselors this sponsor wants named on the public page.') }}
                            </x-ui::table.empty-state>
                        </x-ui::table.row>
                    @endforelse
                </x-ui::table>
            </div>
        </div>

        <x-ui::modal id="sponsor-staff"
            :title="$editingStaffId ? __('Edit this person') : __('Add a person')" size="md">
            <form wire:submit="saveStaff" class="space-y-4">
                <x-ui::forms.input name="staffName" wire:model="staffName" :label="__('Name')" required />
                <x-ui::forms.input name="staffTitle" wire:model="staffTitle" :label="__('Title')"
                    :hint="__('Optional — for example, Director of College Counseling.')" />

                <div class="flex items-center gap-3 pt-2">
                    <x-ui::button type="submit" variant="brand">{{ __('Save') }}</x-ui::button>
                    <x-ui::button type="button" variant="ghost"
                        x-on:click="$dispatch('ui-modal-close', { id: 'sponsor-staff' })">
                        {{ __('Cancel') }}
                    </x-ui::button>
                </div>
            </form>
        </x-ui::modal>

        <x-ui::confirm-modal id="delete-sponsor-staff" :title="__('Remove this person?')"
            :confirm="__('Remove')" variant="danger" wire:click="deleteStaff">
            {{ __('They stop appearing under this sponsor on the public page.') }}
        </x-ui::confirm-modal>
    @endif
</div>
