@extends('layouts.master')

@section('title')
    {{ __('timetable') }}
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
                            <div class="row">
                                <div class="form-group col-sm-12 col-md-3">
                                    <label for="starting_time">{{ __('Starting Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="timetable_start_time" id="starting_time" class="form-control" value="{{ $timetableData['timetable_start_time'] ?? "09:00:00"}}" required/>
                                </div>

                                <div class="form-group col-sm-12 col-md-3">
                                    <label for="ending_time">{{ __('Ending Time') }} <span class="text-danger">*</span></label>
                                    <input type="time" name="timetable_end_time" id="ending_time" class="form-control" value="{{ $timetableData['timetable_end_time'] ?? "18:00:00"}}" required/>
                                </div>
                                <div class="form-group col-sm-12 col-md-3">
                                    <label for="duration">{{ __('Timeslot Duration') }} <small>({{__('in Minutes')}})</small><span class="text-danger">*</span></label>
                                    <input type="number" name="timetable_duration" id="duration" class="form-control" min="1" max="180" value="{{ $timetableData['timetable_duration'] ?? 45 }}" required/>
                                </div>
                            </div>
                            <div class="form-group text-right">
                                <button type="submit" id="save_settings" class="btn btn-theme">
                                    <i class="fa fa-save mr-1"></i> {{__('Save Timetable Settings')}}
                                </button>
                            </div>
                        </form>
                        <hr class="my-4">
                        <div class="row">
                            <div class="row w-100 align-items-center" id="toolbar">
                                <div class="form-group col-sm-12 col-md-4 mb-0">
                                    <label class="filter-menu" for="">{{ __('medium') }}</label>
                                    {!! Form::select('medium_id', $mediums, null, ['class' => 'form-control','id' => 'filter_medium_id', 'placeholder' => __('select_medium')]) !!}
                                </div>
                                <div class="col-sm-12 col-md-8 text-right mt-3 mt-md-0 d-flex justify-content-end align-items-center flex-wrap" style="gap: 8px;">
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
                            <div class="col-12">
                                <table aria-describedby="mydesc" class='table' id='table_list'
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
                                        <th scope="col" data-field="no">{{ __('no.') }}</th>
                                        <th scope="col" data-field="full_name">{{ __('Class Section') }}</th>
                                        <th scope="col" data-field="Monday" data-formatter="timetableDayFormatter">{{ __('Monday') }}</th>
                                        <th scope="col" data-field="Tuesday" data-formatter="timetableDayFormatter">{{ __('Tuesday') }}</th>
                                        <th scope="col" data-field="Wednesday" data-formatter="timetableDayFormatter">{{ __('Wednesday') }}</th>
                                        <th scope="col" data-field="Thursday" data-formatter="timetableDayFormatter">{{ __('Thursday') }}</th>
                                        <th scope="col" data-field="Friday" data-formatter="timetableDayFormatter">{{ __('Friday') }}</th>
                                        <th scope="col" data-field="Saturday" data-formatter="timetableDayFormatter">{{ __('Saturday') }}</th>
                                        <th scope="col" data-field="Sunday" data-formatter="timetableDayFormatter">{{ __('Sunday') }}</th>
                                        <th scope="col" data-field="operate" data-escape="false">{{ __('action') }}</th>
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
                        <div id="batch-result-summary" class="alert mb-3" role="alert"></div>
                        <ul id="batch-result-conflicts" class="small mb-0 pl-3"></ul>
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
                var score = res.quality && res.quality.quality_score !== undefined ? res.quality.quality_score : null;
                $('#batch-result-summary').removeClass('alert-danger').addClass('alert-success')
                    .text((res.message || '') + (score !== null ? ' {{ __('Quality score') }}: ' + score + '/100.' : '')
                        + ' {{ __('Review, then apply it to replace the live timetable of the selected sections (locked slots are kept).') }}');
                batchRenderConflicts(res.conflicts);
                batchShowStep('result');
            },
            error: function (xhr) {
                var res = xhr.responseJSON || {};
                batchRunId = null;
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
