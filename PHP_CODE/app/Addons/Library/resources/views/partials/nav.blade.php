@php
    $libraryTabs = [
        ['library.dashboard', 'fa fa-tachometer', __('Overview'), 'library-list'],
        ['library.circulation', 'fa fa-exchange', __('Issue / Return'), 'library-circulation'],
        ['library.catalog', 'fa fa-book', __('Catalogue'), 'library-list'],
        ['library.loans', 'fa fa-list', __('Loans'), 'library-list'],
        ['library.reservations', 'fa fa-bookmark', __('Reservations'), 'library-list'],
        ['library.fines', 'fa fa-money', __('Fines'), 'library-list'],
        ['library.settings', 'fa fa-cog', __('Settings'), 'library-edit'],
    ];
@endphp
<ul class="nav nav-pills mb-3 flex-wrap" style="gap: 6px;">
    @foreach ($libraryTabs as [$route, $icon, $label, $permission])
        @can($permission)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs($route) ? 'active' : 'border' }}" href="{{ route($route) }}">
                    <i class="{{ $icon }} mr-1"></i> {{ $label }}
                </a>
            </li>
        @endcan
    @endforeach
</ul>
