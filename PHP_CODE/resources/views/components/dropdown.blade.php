@props([
    'name' => '',
    'id' => null,
    'label' => null,
    'placeholder' => null,
    'options' => [],
    'selected' => null,
    'required' => false,
    'optional' => false,
    'multiple' => false,
    'icon' => null,
    'class' => '',
    'selectClass' => '',
    'help' => null,
])

@php
    $elementId = $id ?? $name;
@endphp

<div class="form-group zolo-form-group {{ $class }}">
    @if ($label)
        <label for="{{ $elementId }}" class="zolo-form-label font-weight-bold small text-dark mb-1 d-flex align-items-center">
            @if ($icon)
                <i class="{{ $icon }} text-muted mr-1"></i>
            @endif
            <span>{{ $label }}</span>
            @if ($required)
                <span class="text-danger ml-1">*</span>
            @endif
            @if ($optional)
                <span class="badge badge-light border text-muted ml-2 font-weight-normal py-0 px-1" style="font-size: 11px;">{{ __('Optional') }}</span>
            @endif
        </label>
    @endif
    <select name="{{ $name }}{{ $multiple ? '[]' : '' }}" 
            id="{{ $elementId }}" 
            class="form-control zolo-form-select {{ $selectClass }}" 
            {{ $required ? 'required' : '' }} 
            {{ $multiple ? 'multiple' : '' }}
            {{ $attributes }}>
        @if ($placeholder && !$multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $val => $text)
            @php
                $isSelected = is_array($selected) ? in_array($val, $selected) : ($selected == $val && $selected !== null && $selected !== '');
            @endphp
            <option value="{{ $val }}" {{ $isSelected ? 'selected' : '' }}>{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @if ($help)
        <small class="form-text text-muted mt-1">{{ $help }}</small>
    @endif
</div>
