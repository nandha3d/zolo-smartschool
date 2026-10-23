@props([
    'type' => 'radio',           // 'radio' or 'checkbox'
    'name' => '',
    'id' => null,
    'value' => '',
    'label' => '',
    'checked' => false,
    'icon' => null,
    'subtext' => null,
    'required' => false,
    'class' => '',
    'size' => 'md',              // 'sm', 'md', 'lg'
])

@php
    $elementId = $id ?? ($name . '_' . Str::slug($value ?: rand(100, 999)));
    $sizeClasses = [
        'sm' => 'py-1 px-2 font-size-12',
        'md' => 'py-2 px-3 font-size-13',
        'lg' => 'py-3 px-4 font-size-14',
    ];
@endphp

<label for="{{ $elementId }}" class="zolo-selection-pill {{ $checked ? 'active' : '' }} {{ $class }}" style="cursor: pointer; user-select: none;">
    <input type="{{ $type }}" 
           name="{{ $name }}" 
           id="{{ $elementId }}" 
           value="{{ $value }}" 
           {{ $checked ? 'checked' : '' }} 
           {{ $required ? 'required' : '' }} 
           class="zolo-pill-input"
           style="position: absolute; opacity: 0; pointer-events: none;"
           onchange="updateSelectionPill(this)"
           {{ $attributes }} />
    <div class="pill-content d-inline-flex align-items-center {{ $sizeClasses[$size] ?? $sizeClasses['md'] }}">
        <span class="pill-indicator mr-2">
            @if ($type === 'radio')
                <span class="radio-dot"></span>
            @else
                <i class="fa fa-check check-icon"></i>
            @endif
        </span>
        @if ($icon)
            <i class="{{ $icon }} mr-1 pill-icon text-muted"></i>
        @endif
        <span class="pill-label font-weight-bold">{{ $label }}</span>
        @if ($subtext)
            <span class="pill-subtext ml-2 badge badge-light border text-muted">{{ $subtext }}</span>
        @endif
    </div>
</label>

@once
<script>
    if (typeof window.updateSelectionPill === 'undefined') {
        window.updateSelectionPill = function(input) {
            if (!input) return;
            if (input.type === 'radio') {
                var group = document.querySelectorAll('input[type="radio"][name="' + input.name + '"]');
                group.forEach(function(r) {
                    var pill = r.closest('.zolo-selection-pill');
                    if (pill) {
                        if (r.checked) {
                            pill.classList.add('active');
                        } else {
                            pill.classList.remove('active');
                        }
                    }
                });
            } else if (input.type === 'checkbox') {
                var pill = input.closest('.zolo-selection-pill');
                if (pill) {
                    if (input.checked) {
                        pill.classList.add('active');
                    } else {
                        pill.classList.remove('active');
                    }
                }
            }
        };
    }
</script>
@endonce
