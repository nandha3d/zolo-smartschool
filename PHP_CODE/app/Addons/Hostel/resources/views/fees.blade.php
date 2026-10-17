@extends('layouts.master')

@section('title')
    {{ __('Hostel Fees') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Hostel Fees') }}</h3>
        </div>
        @include('hostel::partials.nav')

        <div class="card mb-3">
            <div class="card-body">
                <h4 class="card-title">{{ __('Raise dues') }}</h4>
                <form data-hostel-form data-after="table" action="{{ route('hostel.fees.generate') }}" method="POST" class="form-inline" style="gap: 8px;">
                    <label class="small">{{ __('For the cycle containing') }}</label>
                    <input type="month" name="month" class="form-control form-control-sm" value="{{ now()->format('Y-m') }}" required>
                    <label class="small">{{ __('due after (days)') }}</label>
                    <input type="number" name="due_in_days" class="form-control form-control-sm" value="10" min="0" max="60" style="width: 80px;">
                    <button class="btn btn-sm btn-theme" type="submit">{{ __('Raise dues for all residents') }}</button>
                </form>
                <p class="small text-muted mt-2 mb-0">{{ __('One due per resident per cycle, using the room type fee (or the resident\'s fee override). Running it again for the same cycle does not duplicate dues.') }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div id="toolbar" class="row">
                    <div class="col-md-4">
                        <select id="filter_status" class="form-control">
                            <option value="Unpaid">{{ __('Unpaid') }}</option>
                            <option value="Paid">{{ __('Paid') }}</option>
                            <option value="Waived">{{ __('Waived') }}</option>
                            <option value="">{{ __('All') }}</option>
                        </select>
                    </div>
                </div>
                <table aria-describedby="hostel-fees" class="table" id="table_list" data-toggle="table"
                       data-url="{{ route('hostel.fees.list') }}" data-side-pagination="server" data-pagination="true"
                       data-search="true" data-toolbar="#toolbar" data-show-refresh="true" data-show-export="true" data-export-data-type="all"
                       data-sort-name="id" data-sort-order="desc" data-query-params="hostelQueryParams">
                    <thead>
                    <tr>
                        <th data-field="student">{{ __('Student') }}</th>
                        <th data-field="admission_no">{{ __('Admission no.') }}</th>
                        <th data-field="period_label">{{ __('Period') }}</th>
                        <th data-field="amount">{{ __('Amount') }}</th>
                        <th data-field="due_date" data-sortable="true" data-formatter="hostelDueFormatter" data-escape="false">{{ __('Due') }}</th>
                        <th data-field="status">{{ __('Status') }}</th>
                        <th data-field="receipt_no">{{ __('Receipt') }}</th>
                        <th data-field="paid_at">{{ __('Paid at') }}</th>
                        <th data-field="id" data-formatter="hostelFeeActions" data-escape="false">{{ __('action') }}</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.HostelFees = { settleUrl: "{{ url('hostel/fees') }}/:id/settle" };
    </script>
    <script src="{{ asset('assets/js/custom/hostel.js') }}"></script>
@endsection
