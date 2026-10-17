@extends('layouts.master')

@section('title')
    {{ __('Hostel Visitors') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Hostel Visitors') }}</h3>
        </div>
        @include('hostel::partials.nav')

        <div class="card mb-3">
            <div class="card-body">
                <h4 class="card-title">{{ __('Check a visitor in') }}</h4>
                <form data-hostel-form data-after="table" action="{{ route('hostel.visitors.store') }}" method="POST">
                    <div class="row">
                        <div class="form-group col-md-4 position-relative">
                            <label class="small">{{ __('Visiting student') }} *</label>
                            <input type="hidden" name="student_id" class="hostel-student-id">
                            <input type="search" class="form-control form-control-sm hostel-student-search" placeholder="{{ __('Name or admission no.') }}" autocomplete="off">
                            <ul class="list-group position-absolute w-100 small hostel-student-results" style="z-index: 5;"></ul>
                        </div>
                        <div class="form-group col-md-3"><label class="small">{{ __('Visitor name') }} *</label><input name="visitor_name" class="form-control form-control-sm" required maxlength="191"></div>
                        <div class="form-group col-md-2"><label class="small">{{ __('Relation') }}</label><input name="relation" class="form-control form-control-sm" maxlength="64"></div>
                        <div class="form-group col-md-3"><label class="small">{{ __('Phone') }}</label><input name="contact" class="form-control form-control-sm" maxlength="32"></div>
                        <div class="form-group col-md-4"><label class="small">{{ __('ID proof (type & number)') }}</label><input name="id_proof" class="form-control form-control-sm" maxlength="64"></div>
                        <div class="form-group col-md-6"><label class="small">{{ __('Purpose') }}</label><input name="purpose" class="form-control form-control-sm" maxlength="191"></div>
                        <div class="form-group col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-theme btn-block" type="submit">{{ __('Check in') }}</button></div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div id="toolbar" class="row">
                    <div class="col-md-4">
                        <select id="filter_status" class="form-control">
                            <option value="inside">{{ __('Inside now') }}</option>
                            <option value="">{{ __('All visits') }}</option>
                        </select>
                    </div>
                </div>
                <table aria-describedby="hostel-visitors" class="table" id="table_list" data-toggle="table"
                       data-url="{{ route('hostel.visitors.list') }}" data-side-pagination="server" data-pagination="true"
                       data-search="true" data-toolbar="#toolbar" data-show-refresh="true" data-show-export="true" data-export-data-type="all"
                       data-sort-name="id" data-sort-order="desc" data-query-params="hostelQueryParams">
                    <thead>
                    <tr>
                        <th data-field="visitor_name">{{ __('Visitor') }}</th>
                        <th data-field="relation">{{ __('Relation') }}</th>
                        <th data-field="contact">{{ __('Phone') }}</th>
                        <th data-field="id_proof">{{ __('ID proof') }}</th>
                        <th data-field="student">{{ __('Student') }}</th>
                        <th data-field="purpose">{{ __('Purpose') }}</th>
                        <th data-field="in_time" data-sortable="true">{{ __('In') }}</th>
                        <th data-field="out_time" data-formatter="hostelVisitorOut" data-escape="false">{{ __('Out') }}</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.HostelVisitors = {
            checkoutUrl: "{{ url('hostel/visitors') }}/:id/checkout",
            studentsUrl: "{{ route('hostel.students') }}",
        };
    </script>
    <script src="{{ asset('assets/js/custom/hostel.js') }}"></script>
@endsection
