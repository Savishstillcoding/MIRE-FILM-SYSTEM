{{--
    <x-section-heading>Title<x-slot:action>right side text/link</x-slot:action></x-section-heading>
    The underlined section titles on most pages. variant="light" (default) or "caps". Styles: .mire-heading in app.scss
--}}
@props(['variant' => 'light', 'as' => 'h2'])
<div {{ $attributes->class(['mire-heading', $variant]) }}>
    <{{ $as }}>{{ $slot }}</{{ $as }}>
    @isset($action)
        <div class="action">{{ $action }}</div>
    @endisset
</div>
