/*
 * Flowbite's interactive behaviour — dropdowns, modals, the mobile nav toggle,
 * accordions. It binds to `data-*` attributes on page load, so importing it is
 * the whole integration; there is nothing to initialise per component.
 *
 * Livewire injects its own script, and Alpine comes with it, so neither is
 * imported here. Flowbite's initialisers run against the DOM Livewire has
 * already swapped in for a full-page component; a component that replaces part
 * of the DOM after load needs `initFlowbite()` calling again in a
 * `livewire:navigated` listener — worth remembering when the roster pages
 * become Livewire.
 */
import 'flowbite';
