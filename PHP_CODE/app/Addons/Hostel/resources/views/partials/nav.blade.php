@php
    $hostelTabs = [
        ['hostel.dashboard', 'fa fa-tachometer', __('Overview'), 'hostel-list'],
        ['hostel.allocations', 'fa fa-bed', __('Beds & Residents'), 'hostel-list'],
        ['hostel.attendance', 'fa fa-check-square-o', __('Roll Call'), 'hostel-attendance'],
        ['hostel.passes', 'fa fa-id-card-o', __('Gate Passes'), 'hostel-gate-pass'],
        ['hostel.visitors', 'fa fa-users', __('Visitors'), 'hostel-gate-pass'],
        ['hostel.fees', 'fa fa-money', __('Hostel Fees'), 'hostel-fees'],
        ['hostel.setup', 'fa fa-building', __('Blocks & Rooms'), 'hostel-list'],
    ];
@endphp
<ul class="nav nav-pills mb-3 flex-wrap" style="gap: 6px;">
    @foreach ($hostelTabs as [$route, $icon, $label, $permission])
        @can($permission)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs($route) ? 'active' : 'border' }}" href="{{ route($route) }}">
                    <i class="{{ $icon }} mr-1"></i> {{ $label }}
                </a>
            </li>
        @endcan
    @endforeach
</ul>
