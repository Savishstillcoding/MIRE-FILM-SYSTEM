{{-- <x-avatar size="40" />: placeholder user picture (header menu, profile, review cards). --}}
@props(['size' => 48])
<svg {{ $attributes->merge(['viewBox' => '0 0 48 48', 'width' => $size, 'height' => $size, 'role' => 'img', 'aria-label' => 'User avatar', 'class' => 'mire-avatar']) }}>
    <circle cx="24" cy="24" r="24" fill="#9a9a9e"/>
    <circle cx="24" cy="18" r="8" fill="#f5f5f5"/>
    <path d="M8 40c2-8 9-12 16-12s14 4 16 12a24 24 0 0 1-32 0z" fill="#f5f5f5"/>
</svg>
