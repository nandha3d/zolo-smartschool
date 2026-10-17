@extends('layouts.master')

@section('title')
    {{ __('Library') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Library') }}</h3>
        </div>
        @include('library::partials.nav')

        <div class="row">
            @foreach ([
                [__('Titles'), $stats['titles'], 'fa-book', 'primary'],
                [__('Copies on shelf'), $stats['available'] . ' / ' . $stats['copies'], 'fa-th', 'success'],
                [__('On loan'), $stats['on_loan'], 'fa-exchange', 'info'],
                [__('Overdue'), $stats['overdue'], 'fa-exclamation-triangle', 'danger'],
                [__('Due today'), $stats['due_today'], 'fa-calendar', 'warning'],
                [__('Members'), $stats['members'], 'fa-users', 'secondary'],
                [__('Unpaid fines'), number_format($stats['unpaid_fines'], 2), 'fa-money', 'danger'],
                [__('Active reservations'), $stats['reservations'], 'fa-bookmark', 'primary'],
            ] as [$label, $value, $icon, $tone])
                <div class="col-6 col-md-3 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center">
                                <i class="fa {{ $icon }} fa-2x text-{{ $tone }} mr-3"></i>
                                <div>
                                    <div class="h4 mb-0">{{ $value }}</div>
                                    <small class="text-muted">{{ $label }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-md-6 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Today') }}</h4>
                        <p class="mb-1">{{ __('Issued') }}: <strong>{{ $stats['issued_today'] }}</strong></p>
                        <p class="mb-3">{{ __('Returned') }}: <strong>{{ $stats['returned_today'] }}</strong></p>
                        @can('library-circulation')
                            <a href="{{ route('library.circulation') }}" class="btn btn-theme btn-sm"><i class="fa fa-barcode mr-1"></i> {{ __('Open the issue / return desk') }}</a>
                        @endcan
                        <a href="{{ route('library.loans') }}?status=overdue" class="btn btn-outline-danger btn-sm ml-1">{{ __('See overdue loans') }}</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Most borrowed') }}</h4>
                        <ol class="mb-0 pl-3">
                            @forelse ($popular as $book)
                                <li>{{ $book->title }} <span class="text-muted small">{{ $book->author }} · {{ $book->loans_count }} {{ __('loans') }}</span></li>
                            @empty
                                <li class="list-unstyled text-muted">{{ __('No loans yet.') }}</li>
                            @endforelse
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
