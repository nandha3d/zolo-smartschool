@extends('layouts.master')

@section('title')
    {{ __('Relief drivers') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Relief drivers') }}</h3>
        </div>

        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Roster a relief driver') }}</h4>
                        <p class="text-muted small mb-3">
                            {{ __('While a relief entry is in force the relief driver sees the route in the driver app and the primary driver cannot start that run.') }}
                        </p>
                        @can('RouteVehicle-edit')
                            <form action="{{ route('relief-drivers.store') }}" method="POST" class="create-form" id="create-form" novalidate>
                                @csrf
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label for="route_vehicle_id">{{ __('Route vehicle') }} <span class="text-danger">*</span></label>
                                        <select name="route_vehicle_id" id="route_vehicle_id" class="form-control select2-dropdown" required>
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach ($routeVehicles as $rv)
                                                <option value="{{ $rv->id }}" data-primary="{{ $rv->driver_id }}">
                                                    {{ $rv->route?->name }} · {{ $rv->vehicle?->vehicle_number }}
                                                    @if ($rv->route?->shift) ({{ $rv->route->shift->name }}) @endif
                                                    — {{ $rv->driver?->full_name ?? __('no primary driver') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="alternate_driver_id">{{ __('Relief driver') }} <span class="text-danger">*</span></label>
                                        <select name="alternate_driver_id" id="alternate_driver_id" class="form-control select2-dropdown" required>
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach ($drivers as $d)
                                                <option value="{{ $d->id }}">{{ $d->full_name }}{{ $d->mobile ? ' (' . $d->mobile . ')' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label for="shift_type">{{ __('Runs') }}</label>
                                        <select name="shift_type" id="shift_type" class="form-control">
                                            <option value="ALL">{{ __('Pickup and drop') }}</option>
                                            <option value="PICKUP_ONLY">{{ __('Morning pickup only') }}</option>
                                            <option value="DROP_ONLY">{{ __('Afternoon drop only') }}</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="start_date">{{ __('From') }} <span class="text-danger">*</span></label>
                                        <input type="date" name="start_date" id="start_date" class="form-control" value="{{ date('Y-m-d') }}" min="{{ date('Y-m-d') }}" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label for="end_date">{{ __('To') }}</label>
                                        <input type="date" name="end_date" id="end_date" class="form-control" min="{{ date('Y-m-d') }}">
                                        <small class="text-muted">{{ __('Leave empty for one day') }}</small>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="reason">{{ __('Reason') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="reason" id="reason" class="form-control" maxlength="255" placeholder="{{ __('e.g. primary driver on sick leave') }}" required>
                                    </div>
                                    <div class="form-group col-md-12">
                                        <input type="hidden" name="notify_parents" value="0">
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="checkbox" class="form-check-input" name="notify_parents" value="1" checked>
                                                {{ __('Notify the families who ride this bus') }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <input class="btn btn-theme float-right ml-3" type="submit" value="{{ __('submit') }}">
                            </form>
                        @else
                            <p class="text-muted">{{ __('You can view the roster but not change it.') }}</p>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Roster') }}</h4>
                        <div id="toolbar">
                            <select id="relief-state" class="form-control form-control-sm">
                                <option value="current">{{ __('Active and upcoming') }}</option>
                                <option value="past">{{ __('Completed and cancelled') }}</option>
                                <option value="all">{{ __('All') }}</option>
                            </select>
                        </div>
                        <table aria-describedby="mydesc" class="table" id="table_list" data-toggle="table"
                               data-url="{{ route('relief-drivers.list') }}" data-side-pagination="server"
                               data-pagination="true" data-page-list="[10, 25, 50, 100]" data-page-size="25"
                               data-toolbar="#toolbar" data-show-refresh="true" data-mobile-responsive="true"
                               data-query-params="reliefQueryParams" data-escape="true">
                            <thead>
                            <tr>
                                <th data-field="no">{{ __('no.') }}</th>
                                <th data-field="route">{{ __('Route') }}</th>
                                <th data-field="vehicle">{{ __('Vehicle') }}</th>
                                <th data-field="primary_driver">{{ __('Primary driver') }}</th>
                                <th data-field="alternate_driver">{{ __('Relief driver') }}</th>
                                <th data-field="dates">{{ __('Dates') }}</th>
                                <th data-field="shift_type">{{ __('Runs') }}</th>
                                <th data-field="reason">{{ __('Reason') }}</th>
                                <th data-field="state" data-formatter="reliefStateFormatter" data-escape="false">{{ __('Status') }}</th>
                                <th data-field="parents_notified">{{ __('Families notified') }}</th>
                                @can('RouteVehicle-edit')
                                    <th data-field="id" data-formatter="reliefActionFormatter" data-escape="false">{{ __('action') }}</th>
                                @endcan
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        function reliefQueryParams(p) {
            return {limit: p.limit, offset: p.offset, state: $('#relief-state').val()};
        }
        function reliefStateFormatter(value) {
            var cls = {ACTIVE: 'success', UPCOMING: 'info', COMPLETED: 'secondary', CANCELLED: 'danger'}[value] || 'secondary';
            return '<span class="badge badge-' + cls + '">' + $('<span>').text(value).html() + '</span>';
        }
        function reliefActionFormatter(value, row) {
            if (!row.can_cancel) { return ''; }
            return '<button type="button" class="btn btn-xs btn-outline-danger relief-cancel" data-id="' + parseInt(row.id, 10) + '">{{ __('Cancel') }}</button>';
        }

        $('#relief-state').on('change', function () { $('#table_list').bootstrapTable('refresh'); });

        $(document).on('click', '.relief-cancel', function () {
            var id = $(this).data('id');
            Swal.fire({
                title: @json(__('Cancel this relief entry?')),
                text: @json(__('The primary driver gets the vehicle back straight away.')),
                icon: 'warning', showCancelButton: true
            }).then(function (r) {
                if (!r.isConfirmed) { return; }
                $.post(@json(route('relief-drivers.cancel', ['id' => '__ID__'])).replace('__ID__', id), {_token: $('meta[name="csrf-token"]').attr('content')})
                    .done(function (res) {
                        res.error ? showErrorToast(res.message) : showSuccessToast(res.message);
                        $('#table_list').bootstrapTable('refresh');
                    });
            });
        });

        // The relief driver cannot be the vehicle's own primary driver.
        $('#route_vehicle_id').on('change', function () {
            var primary = String($(this).find(':selected').data('primary') || '');
            $('#alternate_driver_id option').prop('disabled', false);
            if (primary) { $('#alternate_driver_id option[value="' + primary + '"]').prop('disabled', true); }
        });
    </script>
@endsection
