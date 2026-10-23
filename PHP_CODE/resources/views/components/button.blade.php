@props([
    'type' => 'primary',        // primary, secondary, success, danger, warning, help, text, link, outline-primary, outline-secondary, icon-square, icon-circle
    'size' => 'md',             // sm, md, lg
    'icon' => null,             // e.g. 'fa fa-check', 'fa fa-search'
    'iconPosition' => 'left',   // left, right
    'label' => null,            // button text
    'badge' => null,            // badge counter
    'href' => null,             // if provided, renders as <a>
    'disabled' => false,
    'buttonType' => 'button',   // button, submit, reset
    'class' => '',
])

@php
    $typeClasses = [
        'primary'           => 'btn-primary',
        'secondary'         => 'btn-secondary',
        'success'           => 'btn-success',
        'danger'            => 'btn-danger',
        'warning'           => 'btn-warning',
        'help'              => 'btn-help',
        'text'              => 'btn-text',
        'link'              => 'btn-link',
        'outline-primary'   => 'btn-outline-primary',
        'outline-secondary' => 'btn-outline-secondary',
        'icon-square'       => 'btn-icon-square',
        'icon-circle'       => 'btn-icon-circle',
    ];
    $sizeClasses = [
        'sm' => 'btn-sm',
        'md' => 'btn-md',
        'lg' => 'btn-lg',
    ];

    $btnClass = 'btn ' . ($typeClasses[$type] ?? 'btn-primary') . ' ' . ($sizeClasses[$size] ?? '') . ' ' . $class;
    if ($disabled) {
        $btnClass .= ' disabled';
    }
@endphp

@if ($href)
    <a href="{{ $disabled ? '#' : $href }}" {{ $attributes->merge(['class' => $btnClass]) }} @if($disabled) aria-disabled="true" tabindex="-1" @endif>
        @if ($icon && $iconPosition === 'left')
            <i class="{{ $icon }} {{ $label ? 'mr-1' : '' }}"></i>
        @endif
        @if ($label)
            <span>{{ $label }}</span>
        @else
            {{ $slot }}
        @endif
        @if ($badge !== null)
            <span class="btn-badge">{{ $badge }}</span>
        @endif
        @if ($icon && $iconPosition === 'right')
            <i class="{{ $icon }} {{ $label ? 'ml-1' : '' }}"></i>
        @endif
    </a>
@else
    <button type="{{ $buttonType }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $btnClass]) }}>
        @if ($icon && $iconPosition === 'left')
            <i class="{{ $icon }} {{ $label ? 'mr-1' : '' }}"></i>
        @endif
        @if ($label)
            <span>{{ $label }}</span>
        @else
            {{ $slot }}
        @endif
        @if ($badge !== null)
            <span class="btn-badge">{{ $badge }}</span>
        @endif
        @if ($icon && $iconPosition === 'right')
            <i class="{{ $icon }} {{ $label ? 'ml-1' : '' }}"></i>
        @endif
    </button>
@endif
