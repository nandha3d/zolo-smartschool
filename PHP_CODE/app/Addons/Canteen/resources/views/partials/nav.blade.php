@php
    $canteenTabs = [
        ['canteen.dashboard', 'fa fa-tachometer', __('Overview'), 'canteen-list'],
        ['canteen.pos', 'fa fa-shopping-cart', __('POS'), 'canteen-pos'],
        ['canteen.kitchen', 'fa fa-fire', __('Kitchen'), 'canteen-pos'],
        ['canteen.orders', 'fa fa-list', __('Orders'), 'canteen-list'],
        ['canteen.wallets', 'fa fa-credit-card', __('Wallets'), 'canteen-wallet'],
        ['canteen.menu', 'fa fa-cutlery', __('Menu'), 'canteen-list'],
        ['canteen.inventory', 'fa fa-cubes', __('Inventory'), 'canteen-list'],
        ['canteen.plans', 'fa fa-calendar', __('Meal Plans'), 'canteen-list'],
    ];
@endphp
<ul class="nav nav-pills mb-3 flex-wrap" style="gap: 6px;">
    @foreach ($canteenTabs as [$route, $icon, $label, $permission])
        @can($permission)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs($route) ? 'active' : 'border' }}" href="{{ route($route) }}">
                    <i class="{{ $icon }} mr-1"></i> {{ $label }}
                </a>
            </li>
        @endcan
    @endforeach
</ul>
