@extends('layouts.master')

@section('title')
    {{ __('Canteen Orders') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Canteen Orders') }}</h3>
        </div>
        @include('canteen::partials.nav')
        <div class="card"><div class="card-body">
            <div class="row" id="toolbar">
                <div class="col-md-3"><input type="date" id="filter_date" class="form-control" value="{{ now()->toDateString() }}"></div>
                <div class="col-md-3">
                    <select id="filter_status" class="form-control">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Addons\Canteen\Models\CanteenOrder::STATUSES as $status)<option>{{ $status }}</option>@endforeach
                    </select>
                </div>
            </div>
            <table aria-describedby="canteen-orders" class="table" id="table_list" data-toggle="table"
                   data-url="{{ route('canteen.orders.list') }}" data-side-pagination="server" data-pagination="true"
                   data-search="true" data-toolbar="#toolbar" data-show-refresh="true" data-show-export="true" data-export-data-type="all"
                   data-sort-name="id" data-sort-order="desc" data-query-params="canteenQueryParams">
                <thead>
                <tr>
                    <th data-field="token_number">{{ __('Token') }}</th>
                    <th data-field="order_number">{{ __('Order no.') }}</th>
                    <th data-field="service_date" data-sortable="true">{{ __('Date') }}</th>
                    <th data-field="time">{{ __('Time') }}</th>
                    <th data-field="customer">{{ __('Customer') }}</th>
                    <th data-field="items_text">{{ __('Items') }}</th>
                    <th data-field="total_amount" data-sortable="true">{{ __('Total') }}</th>
                    <th data-field="payment_mode">{{ __('Paid by') }}</th>
                    <th data-field="order_type">{{ __('Type') }}</th>
                    <th data-field="status">{{ __('Status') }}</th>
                    <th data-field="id" data-formatter="canteenOrderActions" data-escape="false">{{ __('action') }}</th>
                </tr>
                </thead>
            </table>
        </div></div>
    </div>
@endsection

@section('script')
    <script>
        window.CanteenOrders = { statusUrl: "{{ url('canteen/orders') }}/:id/status" };
    </script>
    <script src="{{ asset('assets/js/custom/canteen.js') }}"></script>
@endsection
