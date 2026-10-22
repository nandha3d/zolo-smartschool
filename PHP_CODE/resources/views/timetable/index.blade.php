@extends('layouts.master')

@section('title')
    {{ __('timetable') }}
@endsection

@section('css')
<style>
    /* Strict No-Horizontal-Scroll Timetable Layout */
    .fixed-table-container,
    .fixed-table-body {
        overflow-x: hidden !important;
    }
    #table_list {
        table-layout: fixed !important;
        width: 100% !important;
    }
    #table_list th,
    #table_list td {
        overflow: hidden !important;
        word-break: break-word;
        padding: 5px 4px !important;
        vertical-align: top !important;
    }
    #table_list thead th {
        text-align: center;
        background: #f8fafc;
        font-weight: 700;
        font-size: 11px;
        letter-spacing: 0.5px;
        color: #334155;
    }
    .timetable-slot-card {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .timetable-slot-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
    }
</style>
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('create') . ' ' . __('timetable') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="card-title mb-1">
                                    <i class="fa fa-clock-o text-primary mr-1"></i> {{ __('Timetable Operating Hours & Default Duration') }}
                                </h4>
                                <p class="text-muted small mb-0">{{ __('Configure school operating hours and default timeslot duration. To generate or view timetables for each class, use the Auto-Generate buttons in the table below.') }}</p>
                            </div>
                        </div>
                        <form class="edit-form edit-form-without-reset timetable-settings-form" action="{{ route('timetable.settings') }}" method="POST">
                            <div class="row align-items-end">
                                <div class="form-group col-sm-12 col-md-3 mb-3">
                                    <label for="starting_time" class="font-weight-bold">{{ __('Starting Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="timetable_start_time" id="starting_time" class="form-control" value="{{ $timetableData['timetable_start_time'] ?? "09:00:00"}}" required/>
                                </div>

                                <div class="form-group col-sm-12 col-md-3 mb-3">
                                    <label for="ending_time" class="font-weight-bold">{{ __('Ending Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="timetable_end_time" id="ending_time" class="form-control" value="{{ $timetableData['timetable_end_time'] ?? "18:00:00"}}" required/>
                                </div>
                                <div class="form-group col-sm-12 col-md-3 mb-3">
                                    <label for="duration" class="font-weight-bold">{{ __('Timeslot Duration') }} <small class="text-muted">({{__('in Minutes')}})</small><span class="text-danger">*</span></label>
                                    <input type="number" name="timetable_duration" id="duration" class="form-control" min="1" max="180" value="{{ $timetableData['timetable_duration'] ?? 45 }}" required/>
                                </div>
                                <div class="form-group col-sm-12 col-md-3 mb-3">
                                    <button type="submit" id="save_settings" class="btn btn-theme btn-block" style="height: 44px;">
                                        <i class="fa fa-save mr-1"></i> {{__('Save Timetable Settings')}}
                                    </button>
                                </div>
                            </div>
                        </form>
                        <hr class="my-4">
                        
                        <!-- Actions & Quick Controls Bar -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 p-3 bg-light rounded border">
                            <div class="d-flex align-items-center mb-2 mb-md-0">
                                <h5 class="mb-0 font-weight-bold text-dark">
                                    <i class="fa fa-calendar-check-o text-primary mr-2"></i>{{ __('Class Timetable Schedules') }}
                                </h5>
                            </div>
                            <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                                <button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#templatesModal">
                                    <i class="fa fa-calendar-check-o mr-1"></i> {{ __('Bell Schedules') }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#resourcesModal">
                                    <i class="fa fa-building mr-1"></i> {{ __('Rooms & Labs') }}
                                </button>
                                <a href="{{ route('timetable.teacher-matrix') }}" class="btn btn-outline-primary">
                                    <i class="fa fa-th mr-1"></i> {{ __('Teacher Matrix') }}
                                </a>
                                <button type="button" class="btn btn-gradient-primary" data-toggle="modal" data-target="#chooseClassForAutoGenerateModal">
                                    <i class="fa fa-magic mr-1"></i> {{ __('Auto-Generate') }}
                                </button>
                                @can('timetable-create')
                                <button type="button" class="btn btn-gradient-success" data-toggle="modal" data-target="#batchGenerateModal">
                                    <i class="fa fa-sitemap mr-1"></i> {{ __('School-Wide Generate') }}
                                </button>
                                @endcan
                            </div>
                        </div>

                        <div class="row">
                            <div id="toolbar" class="d-flex flex-wrap align-items-center mb-2" style="gap: 14px;">
                                <div class="d-flex align-items-center">
                                    <label class="filter-menu mr-2 mb-0 font-weight-bold text-muted small" style="white-space: nowrap;">
                                        <i class="fa fa-filter mr-1"></i>{{ __('Medium') }}:
                                    </label>
                                    <div style="min-width: 140px;">
                                        {!! Form::select('medium_id', $mediums, null, ['class' => 'form-control form-control-sm','id' => 'filter_medium_id', 'placeholder' => __('all')]) !!}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <label class="filter-menu mr-2 mb-0 font-weight-bold text-muted small" style="white-space: nowrap;">
                                        <i class="fa fa-graduation-cap mr-1"></i>{{ __('Class') }}:
                                    </label>
                                    <div style="min-width: 190px;">
                                        <select name="class_section_id" id="filter_class_section_id" class="form-control form-control-sm">
                                            <option value="">-- {{ __('All Classes') }} --</option>
                                            @if(!empty($classSections))
                                                @foreach($classSections as $cs)
                                                    <option value="{{ $cs->id }}">{{ $cs->full_name }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <table aria-describedby="mydesc" class='table table-bordered' id='table_list'
                                       data-toggle="table" data-url="{{ route('timetable.show',[1]) }}"
                                       data-click-to-select="true" data-side-pagination="server"
                                       data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                                       data-search="true" data-toolbar="#toolbar"
                                       data-show-columns="true" data-show-refresh="true"
                                       data-fixed-columns="false" data-fixed-number="2"
                                       data-fixed-right-number="1" data-trim-on-search="false"
                                       data-mobile-responsive="true" data-sort-name="id"
                                       data-query-params="timetableQueryParams" data-sort-order="desc"
                                       data-maintain-selected="true" data-export-data-type='all' data-show-export="true"
                                       data-export-options='{ "fileName": "timetable-list-<?= date(' d-m-y') ?>" }'>
                                    <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                                        <th scope="col" data-field="no" data-width="40">{{ __('no.') }}</th>
                                        <th scope="col" data-field="full_name" data-width="130">{{ __('Class Section') }}</th>
                                        <th scope="col" data-field="Monday" data-formatter="timetableDayFormatter">{{ __('Monday') }}</th>
                                        <th scope="col" data-field="Tuesday" data-formatter="timetableDayFormatter">{{ __('Tuesday') }}</th>
                                        <th scope="col" data-field="Wednesday" data-formatter="timetableDayFormatter">{{ __('Wednesday') }}</th>
                                        <th scope="col" data-field="Thursday" data-formatter="timetableDayFormatter">{{ __('Thursday') }}</th>
                                        <th scope="col" data-field="Friday" data-formatter="timetableDayFormatter">{{ __('Friday') }}</th>
                                        <th scope="col" data-field="Saturday" data-visible="false" data-formatter="timetableDayFormatter">{{ __('Saturday') }}</th>
                                        <th scope="col" data-field="Sunday" data-visible="false" data-formatter="timetableDayFormatter">{{ __('Sunday') }}</th>
                                        <th scope="col" data-field="operate" data-width="95" data-escape="false">{{ __('action') }}</th>
                                    </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal to Select Class for Auto-Generation -->
    <div class="modal fade" id="chooseClassForAutoGenerateModal" tabindex="-1" role="dialog" aria-labelledby="chooseClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 14px;">
                <div class="modal-header bg-light">
                    <h5 class="modal-title font-weight-bold text-dark" id="chooseClassModalLabel">
                        <i class="fa fa-magic text-primary mr-1"></i> {{ __('Auto-Generate Timetable') }}
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        {{ __('Select a class section below to open its Intelligent Auto-Generator wizard. The system will build a conflict-free schedule, balancing teacher workloads and preventing clashes.') }}
                    </p>
                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Select Class Section') }} <span class="text-danger">*</span></label>
                        <select id="select_class_section_for_gen" class="form-control" style="height: 45px;">
                            <option value="">-- {{ __('Choose Class & Section') }} --</option>
                            @if(!empty($classSections))
                                @foreach($classSections as $cs)
                                    <option value="{{ $cs->id }}">{{ $cs->full_name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-gradient-primary" onclick="launchAutoGeneratorForSelected()">
                        <i class="fa fa-arrow-right mr-1"></i> {{ __('Launch Auto-Generator') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: School-wide batch generation across many classes at once -->
    <div class="modal fade" id="batchGenerateModal" tabindex="-1" role="dialog" aria-labelledby="batchGenerateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content" style="border-radius: 14px;">
                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark" id="batchGenerateModalLabel">
                            <i class="fa fa-sitemap text-primary mr-1"></i> {{ __('School-Wide Timetable Generator') }}
                        </h5>
                        <small class="text-muted">{{ __('Generate several classes in one run so a teacher shared across grades and sections is never double-booked.') }}</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div id="batch-step-select">
                        <div class="row">
                            <div class="col-md-5 form-group">
                                <label class="font-weight-bold small">{{ __('Bell Schedule') }}</label>
                                <select id="batch_template_id" class="form-control form-control-sm">
                                    <option value="">{{ __('Default schedule') }}</option>
                                    @foreach($templates as $tmpl)
                                        <option value="{{ $tmpl->id }}" @selected($tmpl->is_default)>{{ $tmpl->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-7 form-group d-flex align-items-end">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="batch_preserve_existing" checked>
                                    <label class="custom-control-label small" for="batch_preserve_existing">
                                        {{ __('Respect the timetables of classes not selected (their teachers stay booked)') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 6px;">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="batchSelect('ready')">{{ __('Select ready') }}</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="batchSelect('all')">{{ __('Select all') }}</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="batchSelect('none')">{{ __('Clear') }}</button>
                            <span class="small text-muted ml-auto"><span id="batch-selected-count">0</span> {{ __('sections selected') }}</span>
                        </div>

                        <div class="border rounded p-2" style="max-height: 45vh; overflow-y: auto;">
                            @php($sectionsByClass = $classSections->groupBy('class_id'))
                            @forelse($sectionsByClass as $classId => $sections)
                                <div class="mb-2">
                                    <div class="d-flex align-items-center bg-light rounded px-2 py-1">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input batch-class-toggle" id="batch_class_{{ $classId }}" data-class="{{ $classId }}">
                                            <label class="custom-control-label font-weight-bold small" for="batch_class_{{ $classId }}">
                                                {{ $sections->first()->class->full_name ?? $sections->first()->class->name ?? __('Class') }}
                                            </label>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap pl-4 pt-1" style="gap: 4px 18px;">
                                        @foreach($sections as $cs)
                                            @php($r = $readiness[$cs->id] ?? null)
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input batch-section" id="batch_cs_{{ $cs->id }}"
                                                       value="{{ $cs->id }}" data-class="{{ $classId }}" data-ready="{{ $r && $r['subjects'] > 0 ? 1 : 0 }}">
                                                <label class="custom-control-label small" for="batch_cs_{{ $cs->id }}">
                                                    {{ $cs->full_name }}
                                                    @if(!$r)
                                                        <span class="badge badge-warning" title="{{ __('No subject requirements set for this section yet. Open its Auto-Generator once to set weekly periods.') }}">{{ __('no requirements') }}</span>
                                                    @else
                                                        <span class="badge badge-light">{{ $r['subjects'] }} {{ __('subjects') }} · {{ $r['periods'] }} {{ __('periods/wk') }}</span>
                                                        @if($r['no_teacher'] > 0)
                                                            <span class="badge badge-danger" title="{{ __('Subjects without an assigned teacher') }}">{{ $r['no_teacher'] }} {{ __('without teacher') }}</span>
                                                        @endif
                                                    @endif
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted small py-3">{{ __('No class sections found.') }}</div>
                            @endforelse
                        </div>
                    </div>

                    <div id="batch-step-result" class="d-none">
                        <!-- Top KPI & Status Bar -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center p-3 mb-3 bg-light rounded border">
                            <div class="d-flex align-items-center mb-2 mb-md-0 flex-wrap" style="gap: 8px;">
                                <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 13px;" id="batch-preview-quality-badge">
                                    <i class="fa fa-star mr-1"></i> {{ __('Quality Score') }}: <span id="batch-preview-quality-score">100</span>/100
                                </span>
                                <span class="badge badge-info px-2 py-2" id="batch-preview-total-slots">0 {{ __('slots scheduled') }}</span>
                                <span class="badge badge-secondary px-2 py-2" id="batch-preview-total-classes">0 {{ __('classes') }}</span>
                            </div>
                            <div class="text-muted small">
                                <i class="fa fa-check-circle text-success mr-1"></i> {{ __('Conflict-free timetable generated.') }}
                            </div>
                        </div>

                        <div id="batch-result-summary" class="alert alert-success py-2 px-3 small mb-2" role="alert"></div>
                        <ul id="batch-result-conflicts" class="small text-danger mb-2 pl-3"></ul>

                        <!-- Class Filter & View Switcher Toolbar -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center p-2 mb-3 bg-white rounded border shadow-sm">
                            <div class="d-flex align-items-center mb-2 mb-md-0 flex-wrap" style="gap: 8px;">
                                <label for="batch-preview-class-filter" class="mb-0 font-weight-bold text-dark small" style="white-space: nowrap;">
                                    <i class="fa fa-graduation-cap text-primary mr-1"></i> {{ __('Class Schedule') }}:
                                </label>
                                <select id="batch-preview-class-filter" class="form-control form-control-sm" style="min-width: 260px; font-weight: 600;">
                                    <!-- Populated dynamically -->
                                </select>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-preview-prev" title="{{ __('Previous Class') }}"><i class="fa fa-chevron-left"></i></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-preview-next" title="{{ __('Next Class') }}"><i class="fa fa-chevron-right"></i></button>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge badge-primary px-2 py-1 small" id="batch-preview-current-class-badge">{{ __('Viewing Schedule') }}</span>
                            </div>
                        </div>

                        <!-- Neat and Compact Weekly Timetable Grid -->
                        <div id="batch-preview-timetable-container" class="border rounded bg-white p-2" style="max-height: 52vh; overflow-y: auto;">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="batch-back-btn" onclick="batchBack()">
                        <i class="fa fa-arrow-left mr-1"></i> {{ __('Back') }}
                    </button>
                    <button type="button" class="btn btn-outline-danger d-none" id="batch-discard-btn" onclick="batchDiscard()">
                        {{ __('Discard Preview') }}
                    </button>
                    <button type="button" class="btn btn-gradient-success d-none" id="batch-apply-btn" onclick="batchApply()">
                        <i class="fa fa-check mr-1"></i> {{ __('Apply to Live Timetable') }}
                    </button>
                    <button type="button" class="btn btn-gradient-primary" id="batch-generate-btn" onclick="batchGenerate()">
                        <i class="fa fa-magic mr-1"></i> {{ __('Generate Preview') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Timetable Templates & Bell Schedules -->
    <div class="modal fade" id="templatesModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 14px;">
                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark">
                            <i class="fa fa-calendar-check-o text-primary mr-1"></i> {{ __('Timetable Templates & Bell Schedules') }}
                        </h5>
                        <small class="text-muted">{{ __('Templates define instructional periods, breaks, and daily timing grids.') }}</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>{{ __('Template Name') }}</th>
                                    <th>{{ __('Working Days') }}</th>
                                    <th>{{ __('Periods & Recesses') }}</th>
                                    <th>{{ __('Default') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($templates as $tmpl)
                                    <tr>
                                        <td>
                                            <strong>{{ $tmpl->name }}</strong>
                                            @if($tmpl->description)
                                                <div class="text-muted small">{{ $tmpl->description }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="small">{{ implode(', ', $tmpl->working_days ?? []) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">{{ count($tmpl->periods) }} {{ __('periods') }}</span>
                                            <span class="badge badge-secondary">{{ $tmpl->instructional_periods->count() }} {{ __('instructional') }}</span>
                                        </td>
                                        <td>
                                            @if($tmpl->is_default)
                                                <span class="badge badge-success"><i class="fa fa-check"></i> {{ __('Default') }}</span>
                                            @else
                                                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="makeTemplateDefault({{ $tmpl->id }})">
                                                    {{ __('Set Default') }}
                                                </button>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="deleteTemplateItem({{ $tmpl->id }})">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            {{ __('No custom templates found. System is using default auto-generated schedule.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card bg-light border p-3">
                        <h6 class="font-weight-bold text-dark mb-2">{{ __('Create New Timetable Template') }}</h6>
                        <form id="createTemplateForm" onsubmit="createTemplateSubmit(event)">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label class="small font-weight-bold">{{ __('Template Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" id="new_template_name" class="form-control form-control-sm" placeholder="e.g. Primary School Standard Schedule" required />
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="small font-weight-bold">{{ __('Description') }}</label>
                                    <input type="text" id="new_template_desc" class="form-control form-control-sm" placeholder="Optional notes" />
                                </div>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fa fa-plus mr-1"></i> {{ __('Create Template') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Rooms & Labs (Resources) -->
    <div class="modal fade" id="resourcesModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 14px;">
                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark">
                            <i class="fa fa-building text-primary mr-1"></i> {{ __('Rooms, Labs & Resource Allocation') }}
                        </h5>
                        <small class="text-muted">{{ __('Define specialized rooms (e.g. Science Lab, Computer Lab, Gymnasium) to avoid room clashes.') }}</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-sm">
                            <thead class="bg-light">
                                <tr>
                                    <th>{{ __('Resource Name') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Capacity') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($resources as $res)
                                    <tr>
                                        <td><strong>{{ $res->name }}</strong></td>
                                        <td><span class="badge badge-info">{{ $res->type }}</span></td>
                                        <td>{{ $res->capacity ? $res->capacity . ' ' . __('students') : __('Standard') }}</td>
                                        <td>
                                            <span class="badge badge-{{ $res->is_active ? 'success' : 'secondary' }}">
                                                {{ $res->is_active ? __('Active') : __('Inactive') }}
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="deleteResourceItem({{ $res->id }})">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            {{ __('No dedicated rooms or labs registered yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card bg-light border p-3">
                        <h6 class="font-weight-bold text-dark mb-2">{{ __('Add Room or Lab') }}</h6>
                        <form id="createResourceForm" onsubmit="createResourceSubmit(event)">
                            <div class="row">
                                <div class="col-md-5 form-group">
                                    <label class="small font-weight-bold">{{ __('Resource / Room Name') }} <span class="text-danger">*</span></label>
                                    <input type="text" id="new_resource_name" class="form-control form-control-sm" placeholder="e.g. Physics Lab, Computer Room 1" required />
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="small font-weight-bold">{{ __('Type') }} <span class="text-danger">*</span></label>
                                    <select id="new_resource_type" class="form-control form-control-sm" required>
                                        <option value="Lab">{{ __('Laboratory') }}</option>
                                        <option value="Room">{{ __('Special Classroom') }}</option>
                                        <option value="Auditorium">{{ __('Auditorium / Hall') }}</option>
                                        <option value="Ground">{{ __('Sports Ground / Gym') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="small font-weight-bold">{{ __('Capacity') }}</label>
                                    <input type="number" id="new_resource_capacity" class="form-control form-control-sm" placeholder="e.g. 40" />
                                </div>
                            </div>
                            <div class="text-right">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fa fa-plus mr-1"></i> {{ __('Add Resource') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script>
    // ---- School-wide batch generation -------------------------------------
    var batchRunId = null;

    function batchUpdateCount() {
        $('#batch-selected-count').text($('.batch-section:checked').length);
        $('.batch-class-toggle').each(function () {
            var cls = $(this).data('class');
            var all = $('.batch-section[data-class="' + cls + '"]');
            $(this).prop('checked', all.length > 0 && all.filter(':checked').length === all.length);
        });
    }

    function batchSelect(mode) {
        $('.batch-section').each(function () {
            $(this).prop('checked', mode === 'all' || (mode === 'ready' && $(this).data('ready') == 1));
        });
        batchUpdateCount();
    }

    $(document).on('change', '.batch-section', batchUpdateCount);
    $(document).on('change', '.batch-class-toggle', function () {
        $('.batch-section[data-class="' + $(this).data('class') + '"]').prop('checked', $(this).is(':checked'));
        batchUpdateCount();
    });

    function batchShowStep(step) {
        var result = step === 'result';
        $('#batch-step-select').toggleClass('d-none', result);
        $('#batch-step-result').toggleClass('d-none', !result);
        $('#batch-generate-btn').toggleClass('d-none', result);
        $('#batch-back-btn').toggleClass('d-none', !result);
        $('#batch-apply-btn, #batch-discard-btn').toggleClass('d-none', !(result && batchRunId));
    }

    function batchRenderConflicts(conflicts) {
        var list = $('#batch-result-conflicts').empty();
        (conflicts || []).forEach(function (c) {
            list.append($('<li>').text(c.message || c.code || JSON.stringify(c)));
        });
    }

    function batchGenerate() {
        var ids = $('.batch-section:checked').map(function () { return $(this).val(); }).get();
        if (!ids.length) {
            showErrorToast("{{ __('Select at least one class section.') }}");
            return;
        }
        var btn = $('#batch-generate-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> {{ __('Generating…') }}');
        batchRunId = null;
        $.ajax({
            url: "{{ route('timetable.generate') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                class_section_ids: ids,
                template_id: $('#batch_template_id').val(),
                preserve_existing: $('#batch_preserve_existing').is(':checked') ? 1 : 0,
                mode: 'classes'
            },
            success: function (res) {
                batchRunId = res.generation_run_id || res.run_id;
                currentBatchData = res;
                currentBatchClasses = res.classes || [];
                var score = res.quality && res.quality.quality_score !== undefined ? res.quality.quality_score : 100;

                $('#batch-preview-quality-score').text(score);
                $('#batch-preview-total-slots').text((res.entries_count || (res.entries ? res.entries.length : 0)) + ' {{ __("slots scheduled") }}');
                $('#batch-preview-total-classes').text(currentBatchClasses.length + ' {{ __("classes") }}');

                $('#batch-result-summary').removeClass('alert-danger').addClass('alert-success')
                    .html('<i class="fa fa-check-circle mr-1"></i> ' + (res.message || '{{ __("Timetable generated successfully.") }}'));

                // Populate Class Filter Dropdown
                var filterSelect = $('#batch-preview-class-filter').empty();
                if (currentBatchClasses.length > 1) {
                    filterSelect.append($('<option>').val('all').text('📊 {{ __("All Classes Overview") }} (' + currentBatchClasses.length + ' {{ __("classes") }})'));
                }
                currentBatchClasses.forEach(function (cls) {
                    filterSelect.append($('<option>').val(cls.id).text(cls.name + ' (' + cls.slots_count + ' slots)'));
                });

                var defaultClassId = currentBatchClasses.length > 0 ? currentBatchClasses[0].id : 'all';
                currentSelectedClassIndex = 0;
                filterSelect.val(defaultClassId);

                renderBatchPreview(defaultClassId);
                batchRenderConflicts(res.conflicts);
                batchShowStep('result');
            },
            error: function (xhr) {
                var res = xhr.responseJSON || {};
                batchRunId = null;
                currentBatchData = null;
                $('#batch-result-summary').removeClass('alert-success').addClass('alert-danger')
                    .text(res.message || "{{ __('Generation failed.') }}");
                batchRenderConflicts(res.conflicts);
                batchShowStep('result');
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="fa fa-magic mr-1"></i> {{ __('Generate Preview') }}');
            }
        });
    }

    var currentBatchData = null;
    var currentBatchClasses = [];
    var currentSelectedClassIndex = 0;

    // Filter main table when class section changes
    $('#filter_class_section_id').on('change', function () {
        $('#table_list').bootstrapTable('refresh');
    });

    function renderBatchPreview(classSectionId) {
        if (!currentBatchData) return;
        var entries = currentBatchData.entries || [];
        var container = $('#batch-preview-timetable-container');

        if (classSectionId === 'all') {
            var html = '<div class="table-responsive"><table class="table table-sm table-striped table-bordered text-center mb-0" style="font-size: 11px;">';
            html += '<thead class="thead-light"><tr>';
            html += '<th style="width: 50px;">#</th>';
            html += '<th class="text-left">{{ __("Class Section") }}</th>';
            html += '<th style="width: 120px;">{{ __("Total Slots") }}</th>';
            html += '<th style="width: 120px;">{{ __("Lectures") }}</th>';
            html += '<th style="width: 100px;">{{ __("Breaks") }}</th>';
            html += '<th style="width: 110px;">{{ __("Action") }}</th>';
            html += '</tr></thead><tbody>';

            currentBatchClasses.forEach(function (cls, idx) {
                var cEntries = entries.filter(function (e) { return e.class_section_id == cls.id; });
                var lectures = cEntries.filter(function (e) { return (e.type || 'Lecture') === 'Lecture'; }).length;
                var breaks = cEntries.filter(function (e) { return (e.type || '') === 'Break'; }).length;
                html += '<tr>';
                html += '<td>' + (idx + 1) + '</td>';
                html += '<td class="text-left font-weight-bold">' + cls.name + '</td>';
                html += '<td><span class="badge badge-info">' + cEntries.length + ' {{ __("slots") }}</span></td>';
                html += '<td><span class="badge badge-success">' + lectures + ' {{ __("lectures") }}</span></td>';
                html += '<td><span class="badge badge-warning">' + breaks + ' {{ __("breaks") }}</span></td>';
                html += '<td><button type="button" class="btn btn-xs btn-outline-primary" onclick="selectBatchPreviewClass(' + cls.id + ')"><i class="fa fa-eye mr-1"></i>{{ __("View") }}</button></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            container.html(html);
            $('#batch-preview-current-class-badge').text('{{ __("All Classes Overview") }} (' + currentBatchClasses.length + ' {{ __("classes") }})');
            return;
        }

        var targetClass = currentBatchClasses.find(function (c) { return c.id == classSectionId; });
        var className = targetClass ? targetClass.name : 'Class #' + classSectionId;
        var classEntries = entries.filter(function (e) { return e.class_section_id == classSectionId; });

        $('#batch-preview-current-class-badge').text(className + ' (' + classEntries.length + ' {{ __("slots") }})');

        var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        if (classEntries.some(function (e) { return e.day === 'Saturday'; })) days.push('Saturday');
        if (classEntries.some(function (e) { return e.day === 'Sunday'; })) days.push('Sunday');

        var colWidth = (100 / days.length).toFixed(1) + '%';
        var html = '<div class="d-flex border rounded overflow-hidden" style="width: 100%; min-width: 720px;">';

        days.forEach(function (day, dIdx) {
            var daySlots = classEntries.filter(function (e) { return e.day === day; });
            daySlots.sort(function (a, b) { return (a.start_time || '').localeCompare(b.start_time || ''); });
            var borderRight = (dIdx < days.length - 1) ? 'border-right: 1px solid #e2e8f0;' : '';

            html += '<div style="flex: 1; width: ' + colWidth + '; ' + borderRight + ' background: #fbfcfe;">';
            html += '<div class="p-2 text-center font-weight-bold text-dark border-bottom" style="background: #f1f5f9; font-size: 11px;">';
            html += '<span class="badge badge-light border mr-1 font-weight-bold" style="color: #3b82f6;">' + day.substring(0, 3).toUpperCase() + '</span> ' + day;
            html += ' <span class="badge badge-secondary py-0 px-1 ml-1" style="font-size: 9px;">' + daySlots.length + '</span>';
            html += '</div>';

            html += '<div class="p-2 d-flex flex-column" style="gap: 5px;">';
            if (!daySlots.length) {
                html += '<div class="text-center text-muted p-3 my-2 bg-light rounded small" style="font-size: 10px; border: 1px dashed #cbd5e1;">- {{ __("No slots") }} -</div>';
            } else {
                daySlots.forEach(function (slot) {
                    var isBreak = (slot.type === 'Break' || (slot.subject_name && slot.subject_name.toLowerCase().indexOf('break') !== -1));
                    var sTime = (slot.start_time || '').substring(0, 5);
                    var eTime = (slot.end_time || '').substring(0, 5);
                    var timeStr = (sTime && eTime) ? (sTime + ' - ' + eTime) : '';

                    if (isBreak) {
                        html += '<div class="py-1 px-2 rounded" style="background: #fffbeb; border: 1px dashed #f59e0b; font-size: 10px; line-height: 1.2;">';
                        html += '<div class="font-weight-bold text-truncate" style="color: #92400e;"><i class="fa fa-coffee text-warning mr-1"></i>' + (slot.subject_name || 'Break') + '</div>';
                        html += '<div class="text-muted" style="font-size: 9px;"><i class="fa fa-clock-o mr-1"></i>' + timeStr + '</div>';
                        html += '</div>';
                    } else {
                        html += '<div class="p-2 rounded bg-white shadow-sm" style="border: 1px solid #e2e8f0; border-left: 3px solid #3b82f6; font-size: 10.5px; line-height: 1.25;">';
                        html += '<div class="font-weight-bold text-dark text-truncate" title="' + slot.subject_name + '">' + slot.subject_name;
                        if (slot.is_locked) {
                            html += ' <i class="fa fa-lock text-warning" title="{{ __("Locked Slot") }}"></i>';
                        }
                        html += '</div>';
                        html += '<div class="text-muted mt-1" style="font-size: 9px;"><i class="fa fa-clock-o mr-1 text-primary"></i>' + timeStr + '</div>';
                        if (slot.teacher_name) {
                            html += '<div class="text-dark font-weight-semibold text-truncate mt-1" style="font-size: 9.5px;" title="' + slot.teacher_name + '">';
                            html += '<i class="fa fa-user-circle mr-1 text-muted"></i>' + slot.teacher_name;
                            html += '</div>';
                        }
                        if (slot.resource_name) {
                            html += '<div class="text-muted text-truncate mt-1" style="font-size: 9px;"><i class="fa fa-building-o mr-1"></i>' + slot.resource_name + '</div>';
                        }
                        html += '</div>';
                    }
                });
            }
            html += '</div>';
            html += '</div>';
        });

        html += '</div>';
        container.html(html);
    }

    function selectBatchPreviewClass(classId) {
        $('#batch-preview-class-filter').val(classId).trigger('change');
    }

    $(document).on('change', '#batch-preview-class-filter', function () {
        var val = $(this).val();
        currentSelectedClassIndex = currentBatchClasses.findIndex(function(c) { return c.id == val; });
        renderBatchPreview(val);
    });

    $(document).on('click', '#btn-preview-prev', function () {
        if (!currentBatchClasses.length) return;
        if (currentSelectedClassIndex > 0) {
            currentSelectedClassIndex--;
        } else {
            currentSelectedClassIndex = currentBatchClasses.length - 1;
        }
        var nextId = currentBatchClasses[currentSelectedClassIndex].id;
        $('#batch-preview-class-filter').val(nextId).trigger('change');
    });

    $(document).on('click', '#btn-preview-next', function () {
        if (!currentBatchClasses.length) return;
        if (currentSelectedClassIndex < currentBatchClasses.length - 1) {
            currentSelectedClassIndex++;
        } else {
            currentSelectedClassIndex = 0;
        }
        var nextId = currentBatchClasses[currentSelectedClassIndex].id;
        $('#batch-preview-class-filter').val(nextId).trigger('change');
    });

    function batchBack() {
        if (batchRunId) {
            batchDiscard(true);
        }
        batchShowStep('select');
    }

    function batchDiscard(silent) {
        if (!batchRunId) return;
        var id = batchRunId;
        batchRunId = null;
        $.post("{{ url('timetable/generation') }}/" + id + "/cancel", { _token: "{{ csrf_token() }}" })
            .always(function () {
                if (silent !== true) {
                    showSuccessToast("{{ __('Preview discarded.') }}");
                    batchShowStep('select');
                }
            });
    }

    function batchApply() {
        if (!batchRunId) return;
        var btn = $('#batch-apply-btn').prop('disabled', true);
        $.post("{{ url('timetable/generation') }}/" + batchRunId + "/apply", { _token: "{{ csrf_token() }}" })
            .done(function (res) {
                if (res.success === false || res.error) {
                    showErrorToast(res.message || "{{ __('Could not apply the timetable.') }}");
                    return;
                }
                batchRunId = null;
                showSuccessToast(res.message || "{{ __('Timetable applied.') }}");
                $('#batchGenerateModal').modal('hide');
                batchShowStep('select');
                $('#table_list').bootstrapTable('refresh');
            })
            .fail(function (xhr) {
                showErrorToast((xhr.responseJSON && xhr.responseJSON.message) || "{{ __('Could not apply the timetable.') }}");
            })
            .always(function () { btn.prop('disabled', false); });
    }

    $('#batchGenerateModal').on('hidden.bs.modal', function () {
        if (batchRunId) {
            batchDiscard(true);
        }
        batchShowStep('select');
    });

    function launchAutoGeneratorForSelected() {
        var id = $('#select_class_section_for_gen').val();
        if (!id) {
            alert("{{ __('Please select a class section.') }}");
            return;
        }
        window.location.href = "{{ url('timetable') }}/" + id + "/edit?autogen=1";
    }

    function makeTemplateDefault(id) {
        $.ajax({
            url: `{{ url('timetable/templates') }}/${id}/set-default`,
            type: 'POST',
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                location.reload();
            },
            error: function() {
                alert("Failed to set default template.");
            }
        });
    }

    function deleteTemplateItem(id) {
        if (!confirm("Are you sure you want to delete this template?")) return;
        $.ajax({
            url: `{{ url('timetable/templates') }}/${id}`,
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                location.reload();
            },
            error: function() {
                alert("Failed to delete template.");
            }
        });
    }

    function createTemplateSubmit(e) {
        e.preventDefault();
        var name = $('#new_template_name').val();
        var desc = $('#new_template_desc').val();
        $.ajax({
            url: "{{ route('timetable.templates.store') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                name: name,
                description: desc,
                working_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']
            },
            success: function() {
                location.reload();
            },
            error: function(err) {
                alert(err.responseJSON ? err.responseJSON.message : "Failed to create template.");
            }
        });
    }

    function createResourceSubmit(e) {
        e.preventDefault();
        var name = $('#new_resource_name').val();
        var type = $('#new_resource_type').val();
        var cap = $('#new_resource_capacity').val();
        $.ajax({
            url: "{{ route('timetable.resources.store') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                name: name,
                type: type,
                capacity: cap
            },
            success: function() {
                location.reload();
            },
            error: function(err) {
                alert(err.responseJSON ? err.responseJSON.message : "Failed to add resource.");
            }
        });
    }

    function deleteResourceItem(id) {
        if (!confirm("Are you sure you want to delete this room/resource?")) return;
        $.ajax({
            url: `{{ url('timetable/resources') }}/${id}`,
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                location.reload();
            },
            error: function() {
                alert("Failed to delete resource.");
            }
        });
    }
</script>
@endsection
