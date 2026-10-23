@props([
    'id' => 'tabGroup',
    'tabs' => [],            // [['id' => 'tab1', 'label' => 'Tab 1', 'icon' => 'fa fa-user', 'active' => true, 'badge' => null]]
    'class' => '',
    'tabClass' => '',
])

<ul class="nav nav-pills zolo-tabs {{ $class }}" id="{{ $id }}" role="tablist">
    @foreach ($tabs as $tab)
        <li class="nav-item mr-1" role="presentation">
            <button class="nav-link zolo-tab-btn {{ !empty($tab['active']) ? 'active' : '' }} {{ $tabClass }}"
                    id="{{ $tab['id'] }}-tab" 
                    data-toggle="pill" 
                    data-target="#{{ $tab['id'] }}" 
                    type="button" 
                    role="tab" 
                    aria-controls="{{ $tab['id'] }}" 
                    aria-selected="{{ !empty($tab['active']) ? 'true' : 'false' }}">
                @if (!empty($tab['icon']))
                    <i class="{{ $tab['icon'] }} mr-1"></i>
                @endif
                <span>{{ $tab['label'] }}</span>
                @if (!empty($tab['badge']))
                    <span class="badge badge-pill badge-primary ml-1 font-weight-bold">{{ $tab['badge'] }}</span>
                @endif
            </button>
        </li>
    @endforeach
</ul>
