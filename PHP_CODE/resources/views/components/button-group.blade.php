@props([
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'btn-group btn-group-unified ' . $class]) }} role="group">
    {{ $slot }}
</div>
