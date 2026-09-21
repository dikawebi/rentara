@props(['variant' => 'full', 'theme' => 'default'])

@php
    $variant = in_array($variant, ['full', 'mark'], true) ? $variant : 'full';
    $theme = in_array($theme, ['default', 'dark'], true) ? $theme : 'default';
    $brandName = config('app.brand.name');
@endphp

<img
    src="{{ Vite::asset(config("app.brand.logo.{$variant}")) }}"
    alt="{{ $variant === 'full' ? $brandName : '' }}"
    {{ $attributes->class(['block', 'brightness-0 invert' => $theme === 'dark']) }}
>
