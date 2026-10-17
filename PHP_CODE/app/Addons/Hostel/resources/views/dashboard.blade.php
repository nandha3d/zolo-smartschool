@extends('layouts.master')

@section('title')
    {{ __('Hostel') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Hostel') }}</h3>
        </div>
        @include('hostel::partials.nav')

        <div class="row">
            @foreach ([
                [__('Residents'), $stats['residents'], 'fa-bed', 'primary'],
                [__('Out on a pass'), $stats['out_now'], 'fa-sign-out', 'info'],
                [__('Late returning'), $stats['overdue_return'], 'fa-exclamation-triangle', 'danger'],
                [__('Passes awaiting decision'), $stats['pending_passes'], 'fa-id-card-o', 'warning'],
                [__('Absent at tonight\'s roll call'), $stats['absent_tonight'], 'fa-user-times', 'danger'],
                [__('Unpaid hostel dues'), number_format($stats['unpaid'], 2), 'fa-money', 'secondary'],
            ] as [$label, $value, $icon, $tone])
                <div class="col-6 col-md-4 col-xl-2 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body py-3">
                            <i class="fa {{ $icon }} text-{{ $tone }}"></i>
                            <div class="h4 mb-0 mt-1">{{ $value }}</div>
                            <small class="text-muted">{{ $label }}</small>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Occupancy by block') }}</h4>
                        @forelse ($blocks as $row)
                            @php($pct = $row['beds'] ? round($row['occupied'] / $row['beds'] * 100) : 0)
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small">
                                    <strong>{{ $row['block']->name }} <span class="badge badge-light">{{ $row['block']->type }}</span></strong>
                                    <span>{{ $row['occupied'] }} / {{ $row['beds'] }} {{ __('beds') }} · {{ $row['block']->rooms_count }} {{ __('rooms') }}</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar {{ $pct >= 95 ? 'bg-danger' : 'bg-success' }}" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __('No blocks yet.') }} <a href="{{ route('hostel.setup') }}">{{ __('Set up blocks and rooms') }}</a></p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
