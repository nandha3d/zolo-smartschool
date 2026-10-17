@extends('layouts.master')

@section('title')
    {{ __('Kitchen Board') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Kitchen Board') }}</h3>
            <small class="text-muted">{{ __('Refreshes every 10 seconds') }}</small>
        </div>
        @include('canteen::partials.nav')
        <div class="row">
            @foreach (['Pending' => ['warning', __('New')], 'Preparing' => ['info', __('Preparing')], 'Ready' => ['success', __('Ready for pickup')]] as $status => [$tone, $label])
                <div class="col-md-4 grid-margin">
                    <div class="card h-100"><div class="card-body">
                        <h4 class="card-title text-{{ $tone }}">{{ $label }} <span class="badge badge-{{ $tone }}" id="kitchen-count-{{ $status }}">0</span></h4>
                        <div id="kitchen-{{ $status }}"></div>
                    </div></div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.CanteenKitchen = {
            ordersUrl: "{{ route('canteen.kitchen.orders') }}",
            statusUrl: "{{ url('canteen/orders') }}/:id/status",
        };
    </script>
    <script src="{{ asset('assets/js/custom/canteen.js') }}"></script>
@endsection
