@extends('layouts.master')

@section('title')
    {{ __('Gate Passes') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Gate Passes') }}</h3>
        </div>
        @include('hostel::partials.nav')

        <div class="row">
            <div class="col-lg-5 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title"><i class="fa fa-qrcode mr-1"></i> {{ __('Security desk') }}</h4>
                        <form id="gate-scan-form">
                            <div class="input-group">
                                <input type="text" id="gate-code" class="form-control form-control-lg text-monospace text-uppercase" maxlength="12" autocomplete="off" placeholder="{{ __('Pass code') }}">
                                <div class="input-group-append"><button class="btn btn-theme" type="submit">{{ __('Check out / in') }}</button></div>
                            </div>
                        </form>
                        <div id="gate-scan-result" class="alert alert-light mt-3 mb-0">{{ __('Scan or type the code shown on the student\'s pass.') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Raise a pass for a student') }}</h4>
                        <form data-hostel-form data-after="table" action="{{ route('hostel.passes.store') }}" method="POST">
                            <div class="row">
                                <div class="form-group col-md-6 position-relative">
                                    <label class="small">{{ __('Student') }} *</label>
                                    <input type="hidden" name="student_id" class="hostel-student-id">
                                    <input type="search" class="form-control form-control-sm hostel-student-search" placeholder="{{ __('Name or admission no.') }}" autocomplete="off">
                                    <ul class="list-group position-absolute w-100 small hostel-student-results" style="z-index: 5;"></ul>
                                </div>
                                <div class="form-group col-md-6"><label class="small">{{ __('Type') }}</label>
                                    <select name="pass_type" class="form-control form-control-sm"><option value="Day_Out">{{ __('Day out') }}</option><option value="Night_Out">{{ __('Night out') }}</option><option value="Home_Visit">{{ __('Home visit') }}</option></select></div>
                                <div class="form-group col-md-6"><label class="small">{{ __('Leaves at') }} *</label><input type="datetime-local" name="out_time" class="form-control form-control-sm" required></div>
                                <div class="form-group col-md-6"><label class="small">{{ __('Back by') }} *</label><input type="datetime-local" name="expected_in_time" class="form-control form-control-sm" required></div>
                                <div class="form-group col-md-12"><label class="small">{{ __('Reason') }}</label><input name="reason" class="form-control form-control-sm" maxlength="191"></div>
                                <div class="form-group col-md-12">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="guardian_confirmed" name="guardian_confirmed" value="1">
                                        <label class="custom-control-label small" for="guardian_confirmed">{{ __('Guardian has already approved (e.g. by phone)') }}</label>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-theme" type="submit">{{ __('Create pass') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div id="toolbar" class="row">
                            <div class="col-md-4">
                                <select id="filter_status" class="form-control">
                                    <option value="Requested">{{ __('Awaiting decision') }}</option>
                                    <option value="Approved">{{ __('Approved, not yet out') }}</option>
                                    <option value="Out">{{ __('Out now') }}</option>
                                    <option value="late">{{ __('Late returning') }}</option>
                                    <option value="Returned">{{ __('Returned') }}</option>
                                    <option value="">{{ __('All') }}</option>
                                </select>
                            </div>
                        </div>
                        <table aria-describedby="hostel-passes" class="table" id="table_list" data-toggle="table"
                               data-url="{{ route('hostel.passes.list') }}" data-side-pagination="server" data-pagination="true"
                               data-search="true" data-toolbar="#toolbar" data-show-refresh="true" data-sort-name="id" data-sort-order="desc"
                               data-query-params="hostelQueryParams">
                            <thead>
                            <tr>
                                <th data-field="code" data-formatter="hostelCodeFormatter" data-escape="false">{{ __('Code') }}</th>
                                <th data-field="student">{{ __('Student') }}</th>
                                <th data-field="pass_type">{{ __('Type') }}</th>
                                <th data-field="reason">{{ __('Reason') }}</th>
                                <th data-field="out_time" data-sortable="true">{{ __('Out') }}</th>
                                <th data-field="expected_in_time">{{ __('Back by') }}</th>
                                <th data-field="guardian_status">{{ __('Guardian') }}</th>
                                <th data-field="warden_status">{{ __('Warden') }}</th>
                                <th data-field="status" data-formatter="hostelPassStatusFormatter" data-escape="false">{{ __('Status') }}</th>
                                <th data-field="id" data-formatter="hostelPassActions" data-escape="false">{{ __('action') }}</th>
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
        window.HostelPasses = {
            scanUrl: "{{ route('hostel.passes.scan') }}",
            decideUrl: "{{ url('hostel/gate-passes') }}/:id/decide",
            cancelUrl: "{{ url('hostel/gate-passes') }}/:id/cancel",
            studentsUrl: "{{ route('hostel.students') }}",
        };
    </script>
    <script src="{{ asset('assets/js/custom/hostel.js') }}"></script>
@endsection
