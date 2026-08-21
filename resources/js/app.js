/*
 * Deliberately empty of behaviour.
 *
 * This file was one line, `import 'flowbite'`, until 2026-08-21. The two things
 * Flowbite drove here — the public site's mobile nav and the FAQ accordion —
 * are inline Alpine and `x-ui::accordion` now.
 *
 * Alpine is NOT imported here, and must not be: Livewire injects its own
 * script and Alpine comes bundled with it, so importing `alpinejs` as well
 * would start two copies and double every handler.
 *
 * One consequence worth keeping from the note this replaces. Flowbite's
 * initialisers bound once on page load, so any component that replaced part of
 * the DOM afterwards needed `initFlowbite()` calling again in a
 * `livewire:navigated` listener. Alpine re-initialises itself across a Livewire
 * morph, so that whole class of bug left with Flowbite — there is nothing to
 * remember when the roster pages become Livewire.
 *
 * The one thing that does still need care: a page that renders NO Livewire
 * component gets no Livewire assets and therefore no Alpine. Most public pages
 * here are exactly that — static Blade served by SiteController — so
 * `x-layouts.app` emits `@livewireScripts` explicitly. It did not until
 * 2026-08-21; Flowbite had been arriving through @vite and covering for it, and
 * removing Flowbite without adding that line would have left the FAQ accordion
 * and the hamburger rendering perfectly and doing nothing at all.
 */

