@extends('layouts.master')

@section('title')
    {{ __('Hostel Roll Call') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Hostel Roll Call') }}</h3>
        </div>
        @include('hostel::partials.nav')

        <div class="card">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="form-group col-md-3">
                        <label class="small">{{ __('Block') }}</label>
                        <select id="roll-block" class="form-control">
                            @foreach ($blocks as $block)<option value="{{ $block->id }}">{{ $block->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <label class="small">{{ __('Date') }}</label>
                        <input type="date" id="roll-date" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label class="small">{{ __('Roll call') }}</label>
                        <select id="roll-type" class="form-control">
                            <option value="Night" @selected(now()->hour >= 15)>{{ __('Night curfew') }}</option>
                            <option value="Morning" @selected(now()->hour < 15)>{{ __('Morning') }}</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3 text-right">
                        <button type="button" class="btn btn-outline-success btn-sm" id="roll-all-present">{{ __('Mark all present') }}</button>
                    </div>
                </div>
                <div id="roll-info" class="small text-muted mb-2"></div>
                <table class="table table-sm">
                    <thead><tr><th>{{ __('Room') }}</th><th>{{ __('Student') }}</th><th>{{ __('Status') }}</th><th>{{ __('Remarks') }}</th></tr></thead>
                    <tbody id="roll-body"></tbody>
                </table>
                <div class="text-right">
                    <button type="button" class="btn btn-theme" id="roll-save"><i class="fa fa-save mr-1"></i> {{ __('Save roll call') }}</button>
                </div>
                <p class="small text-muted mt-2 mb-0">{{ __('Guardians of students newly marked Absent get a push notification. Students out on an approved gate pass are pre-marked On Leave.') }}</p>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        window.HostelRoll = {
            rollUrl: "{{ route('hostel.attendance.roll') }}",
            saveUrl: "{{ route('hostel.attendance.store') }}",
        };
    </script>
    <script src="{{ asset('assets/js/custom/hostel.js') }}"></script>
@endsection
