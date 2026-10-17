@extends('layouts.master')

@section('title')
    {{ __('Beds & Residents') }}
@endsection

@section('css')
    <style>
        .hb-room { border: 1px solid #e5e7eb; border-radius: 10px; padding: 8px; height: 100%; }
        .hb-room.hb-maint { background: #fff7ed; }
        .hb-bed { display: block; width: 100%; text-align: left; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 4px 6px; margin-top: 4px; font-size: 12px; background: #f8fafc; }
        .hb-bed.hb-taken { border-style: solid; border-color: #93c5fd; background: #eff6ff; }
        .hb-bed.hb-selected { outline: 2px solid #16a34a; }
    </style>
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('Beds & Residents') }}</h3>
        </div>
        @include('hostel::partials.nav')

        <div class="row">
            <div class="col-lg-8 grid-margin">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center flex-wrap mb-2" style="gap: 10px;">
                            <h4 class="card-title mb-0">{{ __('Bed board') }}</h4>
                            <select id="board-block" class="form-control form-control-sm" style="max-width: 260px;">
                                @foreach ($blocks as $block)<option value="{{ $block->id }}">{{ $block->name }} ({{ $block->type }})</option>@endforeach
                            </select>
                            <span class="small text-muted" id="board-summary"></span>
                        </div>
                        <p class="small text-muted mb-2">{{ __('Click a free bed to allocate it, or an occupied bed to vacate or move its resident.') }}</p>
                        <div id="board" class="row"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 grid-margin">
                <div class="card" id="board-panel">
                    <div class="card-body">
                        <h4 class="card-title" id="board-panel-title">{{ __('Select a bed') }}</h4>
                        <div id="board-panel-free" class="d-none">
                            <label class="small">{{ __('Find student') }}</label>
                            <input type="search" id="board-student-search" class="form-control form-control-sm" placeholder="{{ __('Name or admission no.') }}">
                            <ul class="list-group list-group-flush small mt-2" id="board-student-results"></ul>
                            <div class="row mt-2">
                                <div class="col-6"><label class="small">{{ __('From') }}</label><input type="date" id="board-date" class="form-control form-control-sm" value="{{ now()->toDateString() }}"></div>
                                <div class="col-6"><label class="small">{{ __('Fee override') }}</label><input type="number" id="board-fee" class="form-control form-control-sm" min="0" step="0.01" placeholder="{{ __('room type fee') }}"></div>
                            </div>
                        </div>
                        <div id="board-panel-taken" class="d-none">
                            <p class="mb-1"><strong id="board-resident"></strong></p>
                            <p class="small text-muted" id="board-resident-meta"></p>
                            @can('hostel-edit')
                                <button type="button" class="btn btn-sm btn-outline-danger" id="board-vacate"><i class="fa fa-sign-out mr-1"></i> {{ __('Vacate bed') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="board-move"><i class="fa fa-arrows mr-1"></i> {{ __('Move to another bed') }}</button>
                                <p class="small text-muted mt-2 d-none" id="board-move-hint">{{ __('Now click a free bed on the board.') }}</p>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">{{ __('Residents') }}</h4>
                        <div id="toolbar" class="row">
                            <div class="col-md-3">
                                <select id="filter_status" class="form-control">
                                    <option value="Active">{{ __('Current residents') }}</option>
                                    <option value="Vacated">{{ __('Vacated') }}</option>
                                    <option value="Transferred">{{ __('Transferred') }}</option>
                                    <option value="">{{ __('All') }}</option>
                                </select>
                            </div>
                        </div>
                        <table aria-describedby="hostel-residents" class="table" id="table_list" data-toggle="table"
                               data-url="{{ route('hostel.allocations.list') }}" data-side-pagination="server" data-pagination="true"
                               data-search="true" data-toolbar="#toolbar" data-show-refresh="true" data-show-export="true"
                               data-export-data-type="all" data-sort-name="id" data-sort-order="desc" data-query-params="hostelQueryParams">
                            <thead>
                            <tr>
                                <th data-field="no">{{ __('no.') }}</th>
                                <th data-field="student">{{ __('Student') }}</th>
                                <th data-field="admission_no">{{ __('Admission no.') }}</th>
                                <th data-field="class">{{ __('Class') }}</th>
                                <th data-field="block">{{ __('Block') }}</th>
                                <th data-field="room">{{ __('Room') }}</th>
                                <th data-field="bed">{{ __('Bed') }}</th>
                                <th data-field="allocation_date" data-sortable="true">{{ __('From') }}</th>
                                <th data-field="vacate_date">{{ __('To') }}</th>
                                <th data-field="status">{{ __('Status') }}</th>
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
        window.HostelBoard = {
            roomsUrl: "{{ route('hostel.rooms') }}",
            studentsUrl: "{{ route('hostel.students') }}",
            allocateUrl: "{{ route('hostel.allocations.store') }}",
            vacateUrl: "{{ url('hostel/allocations') }}/:id/vacate",
            transferUrl: "{{ url('hostel/allocations') }}/:id/transfer",
            canCreate: @json(Auth::user()->can('hostel-create')),
        };
    </script>
    <script src="{{ asset('assets/js/custom/hostel.js') }}"></script>
@endsection
