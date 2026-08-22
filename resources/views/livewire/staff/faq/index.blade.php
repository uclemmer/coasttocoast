{{--
    The FAQ list (docs/13).

    Table chrome is `uclemmer/laravel-ui`; the query, the selection and the
    ordering are the component's.
--}}
<div>
    <x-ui::action-bar :heading="__('Frequently asked questions')"
        :description="__('Shown on the public FAQ page, in this order.')" level="h2">
        <x-ui::button href="{{ route('staff.faq.create') }}" variant="brand">
            {{ __('Add a question') }}
        </x-ui::button>
    </x-ui::action-bar>

    @if ($this->needsCopyCount > 0)
        {{-- An alert, not a toast: this is a standing state of the content
             rather than the result of an action, and it must survive a click
             elsewhere. --}}
        <div class="mt-4">
            <x-ui::alert variant="warning">
                {{ trans_choice(
                    ':count question still has placeholder copy in it.|:count questions still have placeholder copy in them.',
                    $this->needsCopyCount,
                    ['count' => $this->needsCopyCount],
                ) }}
                {{ __('They are marked below, and they are published as they stand.') }}
            </x-ui::alert>
        </div>
    @endif

    <div class="mt-6">
        <x-ui::table>
            <x-slot:before>
                <x-ui::table.toolbar>
                    <x-slot:search>
                        <div class="flex flex-wrap items-end gap-3">
                            <x-ui::forms.input name="search" wire:model.live.debounce.300ms="search" type="search"
                                :label="__('Search questions')" :placeholder="__('Search the question text')" />

                            <x-ui::forms.select name="published" wire:model.live="published" :label="__('Published')">
                                <option value="">{{ __('All') }}</option>
                                <option value="yes">{{ __('Published only') }}</option>
                                <option value="no">{{ __('Unpublished only') }}</option>
                            </x-ui::forms.select>
                        </div>
                    </x-slot:search>
                </x-ui::table.toolbar>

                @if (count($selected) > 0)
                    <x-ui::table.bulk-bar :count="count($selected)" :noun="__('question')">
                        <x-ui::button size="sm" variant="danger" wire:click="deleteSelected">
                            {{ __('Remove') }}
                        </x-ui::button>
                    </x-ui::table.bulk-bar>
                @endif
            </x-slot:before>

            <x-ui::table.head>
                <x-ui::table.heading><span class="sr-only">{{ __('Select') }}</span></x-ui::table.heading>
                <x-ui::table.heading>{{ __('Question') }}</x-ui::table.heading>
                <x-ui::table.heading>{{ __('Published') }}</x-ui::table.heading>
                <x-ui::table.heading><span class="sr-only">{{ __('Actions') }}</span></x-ui::table.heading>
            </x-ui::table.head>

            @forelse ($this->items as $item)
                <x-ui::table.row wire:key="faq-{{ $item->id }}">
                    <x-ui::table.cell>
                        <x-ui::forms.checkbox name="selected" wire:model.live="selected"
                            :value="(string) $item->id"
                            :aria-label="__('Select :question', ['question' => $item->question])" />
                    </x-ui::table.cell>

                    <x-ui::table.cell header>
                        <span class="block max-w-prose">{{ $item->question }}</span>

                        @if ($this->needsCopy($item))
                            <x-ui::badge variant="warning" size="base" class="mt-1">
                                {{ __('Needs copy') }}
                            </x-ui::badge>
                        @endif
                    </x-ui::table.cell>

                    {{-- A button, not just an icon: publishing is the one thing
                         done to a FAQ row in a hurry, and making it a round
                         trip through the editor is why Filament's toggle lived
                         in the wrong place. --}}
                    <x-ui::table.cell>
                        <button type="button" wire:click="togglePublished({{ $item->id }})"
                            class="rounded-base focus:outline-none focus:ring-2 focus:ring-brand-soft">
                            <x-ui::badge :variant="$item->is_published ? 'success' : 'gray'">
                                {{ $item->is_published ? __('Published') : __('Hidden') }}
                            </x-ui::badge>
                            <span class="sr-only">
                                {{ $item->is_published
                                    ? __('Hide :question from the public page', ['question' => $item->question])
                                    : __('Publish :question', ['question' => $item->question]) }}
                            </span>
                        </button>
                    </x-ui::table.cell>

                    <x-ui::table.cell>
                        <div class="flex items-center justify-end gap-1">
                            @if ($this->canReorder)
                                <x-ui::button size="xs" variant="ghost" wire:click="moveUp({{ $item->id }})"
                                    :disabled="$loop->first">
                                    <span class="sr-only">{{ __('Move up') }}</span>
                                    <span aria-hidden="true">&uarr;</span>
                                </x-ui::button>
                                <x-ui::button size="xs" variant="ghost" wire:click="moveDown({{ $item->id }})"
                                    :disabled="$loop->last">
                                    <span class="sr-only">{{ __('Move down') }}</span>
                                    <span aria-hidden="true">&darr;</span>
                                </x-ui::button>
                            @endif

                            <x-ui::button size="xs" variant="secondary"
                                href="{{ route('staff.faq.edit', $item) }}">{{ __('Edit') }}</x-ui::button>

                            <x-ui::button size="xs" variant="ghost"
                                wire:click="confirmDelete({{ $item->id }})">{{ __('Remove') }}</x-ui::button>
                        </div>
                    </x-ui::table.cell>
                </x-ui::table.row>
            @empty
                <x-ui::table.row>
                    <x-ui::table.empty-state :colspan="4"
                        :heading="$search === '' && $published === '' ? __('No questions yet') : __('Nothing matches those filters')">
                        @if ($search === '' && $published === '')
                            {{ __('Questions appear on the public FAQ page in the order you set here.') }}
                            <x-slot:action>
                                <x-ui::button href="{{ route('staff.faq.create') }}" variant="brand" size="sm">
                                    {{ __('Add a question') }}
                                </x-ui::button>
                            </x-slot:action>
                        @endif
                    </x-ui::table.empty-state>
                </x-ui::table.row>
            @endforelse
        </x-ui::table>
    </div>

    <x-ui::confirm-modal id="delete-faq-item" :title="__('Remove this question?')" :confirm="__('Remove')"
        variant="danger" wire:click="delete">
        {{ __('It disappears from the public FAQ page. This cannot be undone.') }}
    </x-ui::confirm-modal>
</div>
