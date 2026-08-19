<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page layout
    |--------------------------------------------------------------------------
    |
    | The layout that wraps every full-page Livewire component.
    |
    | Livewire 4 ships `layouts::app`, and that namespace does not resolve here:
    | `component_namespaces` registers namespaces for Livewire's own component
    | resolution, not Blade view hints, so a component with no `#[Layout]`
    | attribute dies with `No hint path defined for [layouts]`. It is a
    | documented trap across this workspace — `ckbs` and `budget` fix it the
    | same way, by pointing this at a view the application owns.
    |
    | `components.layouts.app` is deliberately a component path as well as a
    | view path, so plain Blade pages can use `<x-layouts.app>` and Livewire
    | pages get the same chrome without a second layout to keep in step.
    |
    | This file is intentionally minimal. Laravel merges it over Livewire's
    | packaged config, so every other setting keeps its default.
    |
    */

    'component_layout' => 'components.layouts.app',

];
