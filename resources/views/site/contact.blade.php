{{--
    The contact page (card 8.4).

    The form is a Livewire component embedded here and on the landing page, so
    there is one implementation of the validation, the consent checkbox, the
    honeypot and the throttle.
--}}
<x-layouts.app :title="__('Contact us')"
               :description="__('Questions about registering for the Coast to Coast College Fair, fees, or the evening itself.')">

    <x-site.page-header
        :title="__('Write to the fair')"
        :eyebrow="__('Contact')"
        :crumbs="[__('Home') => route('site.home'), __('Contact') => null]" />

    <x-site.container class="py-14">
        <div class="grid gap-12 md:grid-cols-2 md:gap-x-[clamp(32px,6vw,100px)]">
            <div>
                @if ($intro)
                    <x-ui.prose :html="$intro" class="max-w-[48ch]" />
                @endif

                {{-- From config/fair.php — the same source the email footer and
                     the printed check form read, so a move lands everywhere at
                     once. --}}
                <div class="mt-6 text-[16px] leading-[1.7] text-ink-600">
                    <p class="mb-1 font-semibold text-ink-900">{{ __('Checks by post') }}</p>
                    <p class="m-0">
                        {{ config('app.name') }}<br>
                        @if (config('fair.contact.name'))
                            {{ __('ATTN: :name', ['name' => config('fair.contact.name')]) }}<br>
                        @endif
                        {{ config('fair.contact.address_line1') }}<br>
                        @if (config('fair.contact.address_line2'))
                            {{ config('fair.contact.address_line2') }}<br>
                        @endif
                        {{ config('fair.contact.city') }}, {{ config('fair.contact.state') }} {{ config('fair.contact.postal_code') }}
                    </p>

                    <p class="mt-4">
                        @if (config('fair.contact.phone'))
                            <a href="tel:{{ preg_replace('/\D/', '', config('fair.contact.phone')) }}"
                               class="text-brand-600 underline underline-offset-[3px] hover:text-brand-700">{{ config('fair.contact.phone') }}</a>
                            &middot;
                        @endif
                        <a href="mailto:{{ config('fair.contact.email') }}"
                           class="text-brand-600 underline underline-offset-[3px] hover:text-brand-700">{{ config('fair.contact.email') }}</a>
                    </p>
                </div>
            </div>

            <livewire:contact-form />
        </div>
    </x-site.container>
</x-layouts.app>
