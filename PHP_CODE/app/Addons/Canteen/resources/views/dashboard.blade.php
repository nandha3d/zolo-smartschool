@extends('layouts.master')

@section('title')
    {{ __('Canteen') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Canteen') }}</h3>
        </div>
        @include('canteen::partials.nav')

        <div class="row">
            @foreach ([
                [__('Orders today'), $stats['orders'], 'fa-receipt', 'primary'],
                [__('Sales today'), number_format($stats['sales'], 2), 'fa-money', 'success'],
                [__('Paid by wallet'), number_format($stats['wallet_sales'], 2), 'fa-credit-card', 'info'],
                [__('Open in kitchen'), $stats['open_orders'], 'fa-fire', 'warning'],
                [__('Low-stock ingredients'), $stats['low_stock'], 'fa-cubes', 'danger'],
            ] as [$label, $value, $icon, $tone])
                <div class="col-6 col-md grid-margin stretch-card">
                    <div class="card"><div class="card-body py-3">
                        <i class="fa {{ $icon }} text-{{ $tone }}"></i>
                        <div class="h4 mb-0 mt-1">{{ $value }}</div>
                        <small class="text-muted">{{ $label }}</small>
                    </div></div>
                </div>
            @endforeach
        </div>
        <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
                <div class="card"><div class="card-body">
                    <h4 class="card-title">{{ __('Best sellers today') }}</h4>
                    <table class="table table-sm small mb-0">
                        @forelse ($top as $row)
                            <tr><td>{{ $row->name }}</td><td class="text-right">{{ $row->qty }}</td><td class="text-right">{{ number_format($row->revenue, 2) }}</td></tr>
                        @empty
                            <tr><td class="text-muted">{{ __('No sales yet today.') }}</td></tr>
                        @endforelse
                    </table>
                </div></div>
            </div>
            <div class="col-md-6 grid-margin stretch-card">
                <div class="card"><div class="card-body">
                    <h4 class="card-title">{{ __('Reorder soon') }}</h4>
                    <table class="table table-sm small mb-0">
                        @forelse ($lowStock as $item)
                            <tr><td>{{ $item->name }}</td><td class="text-right text-danger">{{ rtrim(rtrim(number_format($item->current_stock, 3), '0'), '.') }} {{ $item->unit }}</td><td class="text-right text-muted">{{ __('reorder at') }} {{ rtrim(rtrim(number_format($item->reorder_level, 3), '0'), '.') }}</td></tr>
                        @empty
                            <tr><td class="text-muted">{{ __('Stock levels are fine.') }}</td></tr>
                        @endforelse
                    </table>
                </div></div>
            </div>
        </div>
    </div>
@endsection
