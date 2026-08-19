<x-layouts.app :title="__('About the fair')"
               :description="__('An annual college fair in Chattanooga, organised by the college counseling offices of four preparatory schools.')">

    <x-site.page-header
        :title="__('About the fair')"
        :eyebrow="__('Since 2007')"
        :crumbs="[__('Home') => route('site.home'), __('About') => null]" />

    <x-site.container class="py-14">
        @if ($body)
            <x-ui.prose :html="$body" />
        @endif
    </x-site.container>
</x-layouts.app>
