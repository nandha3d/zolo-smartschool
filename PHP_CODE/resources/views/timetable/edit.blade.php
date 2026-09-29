@extends('layouts.master')

@section('title')
    {{ __('timetable') }} - {{ $classSection->full_name }}
@endsection

@section('css')
<style>
    /* =========================================================
       Zolo Modern Timetable & Compact Interactive Matrix Styles
       ========================================================= */
    .timetable-header-card {
        background: #ffffff;
        border: 1px solid #E2E8F0;
        border-radius: 14px;
        padding: 16px 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        margin-bottom: 20px;
    }
    .timetable-class-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #EEF2FF;
        color: #3730A3;
        font-weight: 700;
        font-size: 16px;
        padding: 6px 14px;
        border-radius: 10px;
        border: 1px solid #C7D2FE;
    }
    .view-switcher-btn-group .btn {
        font-size: 13px;
        font-weight: 600;
        padding: 6px 14px;
        border-radius: 8px;
    }
    .view-switcher-btn-group .btn.active {
        background: #2F6BFF !important;
        color: #ffffff !important;
        border-color: #2F6BFF !important;
    }

    /* Compact Interactive Matrix Table */
    .matrix-container {
        background: #ffffff;
        border: 1px solid #E2E8F0;
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        overflow-x: auto;
    }
    .matrix-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 8px;
        min-width: 900px;
    }
    .matrix-table th {
        background: #F8FAFC;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 12px 10px;
        text-align: center;
        border-radius: 8px;
        border: 1px solid #E2E8F0;
    }
    .matrix-table th.period-header-col {
        width: 140px;
        background: #F1F5F9;
        color: #1E293B;
    }
    .matrix-table td {
        vertical-align: top;
        padding: 0;
        height: 105px;
        min-width: 145px;
    }
    .matrix-table td.period-label-cell {
        vertical-align: middle;
        text-align: center;
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 10px;
        padding: 8px;
    }
    .period-title {
        font-weight: 700;
        font-size: 13px;
        color: #1E293B;
    }
    .period-time {
        font-size: 11px;
        color: #64748B;
        font-weight: 500;
        margin-top: 2px;
    }

    /* Slot Cards */
    .slot-card {
        background: #ffffff;
        border: 1px solid #E2E8F0;
        border-left-width: 5px !important;
        border-radius: 10px;
        padding: 10px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .slot-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px rgba(0,0,0,0.08);
        border-color: #CBD5E1;
    }
    .slot-subject-title {
        font-weight: 700;
        font-size: 13px;
        color: #0F172A;
        line-height: 1.3;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .slot-subject-type {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        background: #F1F5F9;
        color: #475569;
        font-weight: 600;
    }
    .slot-teacher-info {
        margin-top: 6px;
        font-size: 12px;
        line-height: 1.3;
    }
    .slot-primary-teacher {
        color: #2563EB;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .slot-alt-teacher {
        color: #D97706;
        font-size: 11px;
        font-weight: 500;
        margin-top: 3px;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .slot-conflict-badge {
        background: #FEE2E2;
        color: #DC2626;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 6px;
        border-radius: 4px;
        border: 1px solid #FCA5A5;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 3px;
        animation: pulseConflict 2s infinite;
    }
    @keyframes pulseConflict {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    .slot-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        margin-top: 6px;
        opacity: 0.2;
        transition: opacity 0.15s ease;
    }
    .slot-card:hover .slot-actions {
        opacity: 1;
    }
    .slot-action-btn {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        border: 1px solid #E2E8F0;
        background: #F8FAFC;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #64748B;
        cursor: pointer;
        padding: 0;
        transition: all 0.15s ease;
    }
    .slot-action-btn:hover {
        background: #2F6BFF;
        color: #ffffff;
        border-color: #2F6BFF;
    }
    .slot-action-btn.delete-btn:hover {
        background: #EF4444;
        color: #ffffff;
        border-color: #EF4444;
    }

    /* Break Row / Slot */
    .break-card {
        background: repeating-linear-gradient(
            45deg,
            #F8FAFC,
            #F8FAFC 10px,
            #F1F5F9 10px,
            #F1F5F9 20px
        );
        border: 1px dashed #CBD5E1;
        border-radius: 10px;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #64748B;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .empty-slot-card {
        background: #FAFAFA;
        border: 1px dashed #E2E8F0;
        border-radius: 10px;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94A3B8;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .empty-slot-card:hover {
        background: #EEF2FF;
        border-color: #818CF8;
        color: #4F46E5;
    }

    /* Calendar Wrapper for Legacy Mode */
    .calendar-wrapper {
        overflow-x: auto;
        width: 100%;
    }
    #calendar {
        min-width: 790px;
    }
    .fc .fc-col-header-cell,
    .fc .fc-timegrid-col {
        min-width: 150px;
    }

    /* Wizard & Setup Modals */
    .modal-stepper {
        display: flex;
        border-bottom: 1px solid #E2E8F0;
        margin-bottom: 20px;
    }
    .stepper-item {
        flex: 1;
        text-align: center;
        padding: 12px 10px;
        font-weight: 600;
        font-size: 13px;
        color: #64748B;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .stepper-item.active {
        color: #2F6BFF;
        border-bottom-color: #2F6BFF;
        background: #F8FAFC;
    }
    .stepper-number {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #E2E8F0;
        color: #475569;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
    }
    .stepper-item.active .stepper-number {
        background: #2F6BFF;
        color: #ffffff;
    }
    .quota-badge {
        font-size: 13px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 6px;
    }
    .quota-badge.quota-ok {
        background: #D1FAE5;
        color: #065F46;
    }
    .quota-badge.quota-warn {
        background: #FEF3C7;
        color: #92400E;
    }
</style>
@endsection

@section('content')
<div class="content-wrapper">
    <!-- Header Section -->
    <div class="page-header d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="page-title mb-1">{{ __('Timetable') }}</h3>
            <div class="text-muted small">
                <a href="{{ route('timetable.index') }}" class="text-secondary"><i class="fa fa-arrow-left mr-1"></i>{{ __('Back to Timetables') }}</a>
                <span class="mx-2">•</span>
                <span>{{ __('Class Section:') }} <strong>{{ $classSection->full_name }}</strong></span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- View Mode Switcher -->
            <div class="btn-group view-switcher-btn-group mr-2" role="group">
                <button type="button" class="btn btn-outline-primary active" id="btn-view-matrix" onclick="switchTimetableView('matrix')">
                    <i class="fa fa-th mr-1"></i> {{ __('Compact Matrix') }}
                </button>
                <button type="button" class="btn btn-outline-primary" id="btn-view-calendar" onclick="switchTimetableView('calendar')">
                    <i class="fa fa-calendar mr-1"></i> {{ __('Timeline Calendar') }}
                </button>
            </div>

            <!-- Subject Rules Action -->
            <button type="button" class="btn btn-outline-success mr-2" data-toggle="modal" data-target="#subjectRequirementsModal">
                <i class="fa fa-sliders mr-1"></i> {{ __('Subject Rules & Labs') }}
            </button>

            <!-- Repair Schedule Action -->
            <button type="button" class="btn btn-outline-warning mr-2" onclick="runConflictRepair()">
                <i class="fa fa-wrench mr-1"></i> {{ __('Repair Schedule') }}
            </button>

            <!-- Teacher Setup Action -->
            <button type="button" class="btn btn-outline-info mr-2" data-toggle="modal" data-target="#teacherSetupModal">
                <i class="fa fa-users mr-1"></i> {{ __('Teacher Setup') }}
            </button>

            <!-- Auto Generate Action -->
            <button type="button" class="btn btn-gradient-primary mr-2" data-toggle="modal" data-target="#autoGenerateModal">
                <i class="fa fa-magic mr-1"></i> {{ __('Auto Generate') }}
            </button>

            <!-- Print Action -->
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="fa fa-print"></i>
            </button>
        </div>
    </div>

    <!-- Overview Bar & Conflict Banner -->
    <div class="timetable-header-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center flex-wrap gap-3">
                <div class="timetable-class-badge">
                    <i class="fa fa-graduation-cap"></i>
                    <span>{{ $classSection->full_name }}</span>
                </div>
                <div class="badge badge-light border p-2">
                    <i class="fa fa-book text-primary mr-1"></i>
                    <strong>{{ $allClassSubjects->count() }}</strong> {{ __('Subjects') }}
                </div>
                <div class="badge badge-light border p-2">
                    <i class="fa fa-user-tie text-success mr-1"></i>
                    <strong>{{ $subjectTeachers->count() }}</strong> {{ __('Teachers Assigned') }}
                </div>
                <div class="badge badge-light border p-2">
                    <i class="fa fa-clock-o text-warning mr-1"></i>
                    <span>{{ $timetableSettingsData['timetable_start_time'] ?? '09:00' }} - {{ $timetableSettingsData['timetable_end_time'] ?? '16:00' }}</span>
                </div>
            </div>

            <div class="d-flex align-items-center mt-2 mt-md-0">
                <input type="hidden" id="class_section_id" value="{{ $classSection->id }}" />
                {!! Form::hidden('semester_id', $classSection->class->include_semesters ? $currentSemester->id : null, ['id' => 'semester_id']) !!}
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearClassTimetable()">
                    <i class="fa fa-trash mr-1"></i> {{ __('Clear Schedule') }}
                </button>
            </div>
        </div>

        <!-- Real-Time Conflict Alert Banner -->
        <div id="conflictAlertBanner" class="alert alert-danger mt-3 mb-0 d-none" role="alert">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <i class="fa fa-exclamation-triangle mr-2"></i>
                    <strong>{{ __('Teacher Conflict Alert:') }}</strong>
                    <span id="conflictAlertText">{{ __('One or more teachers are scheduled for another class simultaneously.') }}</span>
                </div>
                <button type="button" class="btn btn-xs btn-danger" onclick="$('#autoGenerateModal').modal('show')">
                    <i class="fa fa-refresh mr-1"></i> {{ __('Auto-Resolve') }}
                </button>
            </div>
        </div>
    </div>

    <!-- VIEW 1: Compact Interactive Matrix (Default) -->
    <div id="matrixViewContainer" class="matrix-container mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3 px-2">
            <div>
                <h5 class="mb-0 font-weight-bold text-dark">{{ __('Weekly Period Matrix') }}</h5>
                <small class="text-muted">{{ __('Interactive period-by-period schedule with Primary Teacher & Substitute Alternate.') }}</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadMatrixData()">
                    <i class="fa fa-refresh mr-1"></i> {{ __('Refresh Matrix') }}
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="matrix-table" id="timetableMatrixTable">
                <thead>
                    <tr id="matrixHeaderRow">
                        <th class="period-header-col">{{ __('Period / Time') }}</th>
                        <th>{{ __('Monday') }}</th>
                        <th>{{ __('Tuesday') }}</th>
                        <th>{{ __('Wednesday') }}</th>
                        <th>{{ __('Thursday') }}</th>
                        <th>{{ __('Friday') }}</th>
                        <th>{{ __('Saturday') }}</th>
                    </tr>
                </thead>
                <tbody id="matrixBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fa fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                            <div>{{ __('Loading interactive timetable matrix...') }}</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- VIEW 2: Legacy FullCalendar View (Toggled via button) -->
    <div id="calendarViewContainer" class="card d-none mb-4">
        <div class="card-body">
            <div class="row">
                <div id='external-events' class="col-md-3 col-sm-12 col-12">
                    <p class="font-weight-bold mb-2">{{ __('Draggable Subjects') }}</p>
                    <div style="max-height: 500px; overflow-y: auto; padding-right: 5px;">
                        @foreach ($subjectTeachers as $subjectTeacher)
                            <div class='fc-event fc-h-event fc-div-color fc-daygrid-event fc-daygrid-block-event text-wrap mb-2'
                                style="background-color: {{ $subjectTeacher->subject->bg_color }}; border-radius: 6px; padding: 6px;"
                                data-color="{{ $subjectTeacher->subject->bg_color }}"
                                data-subject_teacher_id="{{ $subjectTeacher->id }}"
                                data-subject_id="{{ $subjectTeacher->subject_id }}"
                                data-subject-type="{{ $subjectTeacher->class_subject ? $subjectTeacher->class_subject->type : '' }}"
                                data-duration='{{ $timetableSettingsData['timetable_duration'] ?? '01:00:00' }}'
                                data-note="">
                                <div class='fc-event-main' style="width: -webkit-fill-available">
                                    <strong>{{ $subjectTeacher->subject->name }}</strong>
                                    <div class="small opacity-75">👤 {{ $subjectTeacher->teacher->full_name }}</div>
                                </div>
                            </div>
                        @endforeach

                        @foreach ($subjectWithoutTeacherAssigned as $subject)
                            @php
                                $filtered = collect($subject->class_subjects)->first();
                            @endphp
                            <div class='fc-event fc-h-event fc-div-color fc-daygrid-event fc-daygrid-block-event mb-2'
                                style="background-color: {{ $subject->bg_color }}; border-radius: 6px; padding: 6px;"
                                data-color="{{ $subject->bg_color }}"
                                data-duration='{{ $timetableSettingsData['timetable_duration'] ?? '01:00:00' }}'
                                data-subject_id="{{ $subject->id }}" data-note=""
                                data-subject-type="{{ $filtered['type'] ?? '' }}">
                                <div class='fc-event-main'>
                                    <strong>{{ $subject->name }}</strong>
                                    <div class="small opacity-75">({{ $subject->type }})</div>
                                </div>
                            </div>
                        @endforeach

                        <div class='fc-event fc-h-event fc-daygrid-event fc-daygrid-block-event'
                            style="background-color: #1E293B; border-radius: 6px; padding: 6px;"
                            data-color="#1E293B" data-duration='00:30:00'
                            data-note="Break">
                            <div class='fc-event-main'>☕ {{ __('Break / Recess') }}</div>
                        </div>
                    </div>
                </div>
                <div class="calendar-wrapper col-md-9">
                    <div id="calendar" class="no-header-toolbar"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 1: Teacher & Subject Allocation Setup (Assigns Teachers & Syncs DB)
     ========================================================================= -->
<div class="modal fade" id="teacherSetupModal" tabindex="-1" role="dialog" aria-labelledby="teacherSetupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark" id="teacherSetupModalLabel">
                        <i class="fa fa-users text-primary mr-1"></i> {{ __('Teacher-Subject Allocation Setup') }}
                    </h5>
                    <small class="text-muted">{{ __('Assign which teacher takes which subject for :class, specify alternate teachers, and sync across school.', ['class' => $classSection->full_name]) }}</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <form id="teacherAssignmentForm">
                    <div class="alert alert-info py-2 px-3 small mb-3">
                        <i class="fa fa-info-circle mr-1"></i>
                        {{ __('Saving here directly updates the official Subject-Teacher records in the system. The Timetable Auto-Generator will use these assignments.') }}
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 25%;">{{ __('Subject') }}</th>
                                    <th style="width: 32%;">{{ __('Primary Teacher') }} <span class="text-danger">*</span></th>
                                    <th style="width: 28%;">{{ __('Alternate / Substitute') }}</th>
                                    <th style="width: 15%;">{{ __('Periods/Wk') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($allClassSubjects as $cs)
                                    @php
                                        $existingSt = $subjectTeachers->firstWhere('subject_id', $cs->subject_id);
                                        $primaryTeacherId = $existingSt ? $existingSt->teacher_id : null;
                                    @endphp
                                    <tr class="subject-setup-row" data-subject-id="{{ $cs->subject_id }}">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="d-inline-block mr-2" style="width: 12px; height: 12px; border-radius: 3px; background: {{ $cs->subject->bg_color ?? '#3B82F6' }};"></span>
                                                <strong>{{ $cs->subject->name }}</strong>
                                            </div>
                                            <small class="text-muted">({{ $cs->subject->type }})</small>
                                        </td>
                                        <td>
                                            <select class="form-control select-primary-teacher select2-basic" name="primary_teacher_{{ $cs->subject_id }}" required>
                                                <option value="">{{ __('-- Select Primary Teacher --') }}</option>
                                                @foreach($allTeachers as $t)
                                                    <option value="{{ $t->id }}" {{ $primaryTeacherId == $t->id ? 'selected' : '' }}>
                                                        {{ $t->first_name }} {{ $t->last_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control select-alt-teacher" name="alt_teacher_{{ $cs->subject_id }}">
                                                <option value="">{{ __('-- Optional Alternate --') }}</option>
                                                @foreach($allTeachers as $t)
                                                    <option value="{{ $t->id }}" {{ ($primaryTeacherId != $t->id) ? '' : 'disabled' }}>
                                                        {{ $t->first_name }} {{ $t->last_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control text-center input-weekly-periods" value="5" min="1" max="15" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-info mr-2" onclick="saveTeacherAssignments()">
                        <i class="fa fa-save mr-1"></i> {{ __('Save & Sync to School Setup') }}
                    </button>
                    <button type="button" class="btn btn-gradient-primary" onclick="proceedToAutoGenerator()">
                        <i class="fa fa-magic mr-1"></i> {{ __('Proceed to Auto-Generate') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: Intelligent Auto-Generator Wizard
     ========================================================================= -->
<div class="modal fade" id="autoGenerateModal" tabindex="-1" role="dialog" aria-labelledby="autoGenerateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark" id="autoGenerateModalLabel">
                        <i class="fa fa-magic text-primary mr-1"></i> {{ __('Intelligent Timetable Auto-Generator') }}
                    </h5>
                    <small class="text-muted">{{ __('Generates a conflict-free schedule, balancing teacher workloads and preventing clashes.') }}</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Stepper Header -->
            <div class="modal-stepper">
                <div class="stepper-item active" id="stepTab1" onclick="goToWizardStep(1)">
                    <span class="stepper-number">1</span>
                    <span>{{ __('Timing & Structure') }}</span>
                </div>
                <div class="stepper-item" id="stepTab2" onclick="goToWizardStep(2)">
                    <span class="stepper-number">2</span>
                    <span>{{ __('Subjects & Teachers') }}</span>
                </div>
                <div class="stepper-item" id="stepTab3" onclick="goToWizardStep(3)">
                    <span class="stepper-number">3</span>
                    <span>{{ __('Generate & Solve') }}</span>
                </div>
            </div>

            <div class="modal-body px-4 py-2">
                <!-- STEP 1: Timing & Structure -->
                <div id="wizardStep1">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="font-weight-bold">{{ __('Working Days') }} <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day)
                                    <div class="custom-control custom-checkbox mr-3">
                                        <input type="checkbox" class="custom-control-input gen-day-checkbox" id="genDay{{ $day }}" value="{{ $day }}" {{ $day != 'Saturday' ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold" for="genDay{{ $day }}">{{ __($day) }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">{{ __('Day Start Time') }} <span class="text-danger">*</span></label>
                            <input type="time" id="genStartTime" class="form-control" value="{{ $timetableSettingsData['timetable_start_time'] ?? '09:00' }}" required>
                        </div>

                        <div class="col-md-4 form-group">
                            @php
                                $rawDuration = $timetableSettingsData['timetable_duration'] ?? '45';
                                $periodDurationMins = 45;
                                if (is_numeric($rawDuration)) {
                                    $periodDurationMins = (int)$rawDuration;
                                } elseif (is_string($rawDuration) && str_contains($rawDuration, ':')) {
                                    $parts = explode(':', $rawDuration);
                                    $periodDurationMins = ((int)$parts[0] * 60) + (int)($parts[1] ?? 0);
                                }
                                if ($periodDurationMins <= 0 || $periodDurationMins > 180) {
                                    $periodDurationMins = 45;
                                }
                            @endphp
                            <label class="font-weight-bold">{{ __('Period Duration (Mins)') }} <span class="text-danger">*</span></label>
                            <input type="number" id="genPeriodDuration" class="form-control" value="{{ $periodDurationMins }}" min="20" max="120" required>
                        </div>

                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold">{{ __('Total Periods Per Day') }} <span class="text-danger">*</span></label>
                            <input type="number" id="genTotalPeriods" class="form-control" value="6" min="1" max="12" required>
                        </div>

                        <!-- Breaks Configuration -->
                        <div class="col-md-12">
                            <label class="font-weight-bold d-flex align-items-center justify-content-between">
                                <span>{{ __('Daily Breaks / Recesses') }}</span>
                                <button type="button" class="btn btn-xs btn-outline-primary" onclick="addBreakRow()">
                                    <i class="fa fa-plus mr-1"></i> {{ __('Add Break') }}
                                </button>
                            </label>
                            <div id="breaksListContainer" class="border rounded p-2 bg-light">
                                <div class="break-row d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                                    <div class="flex-grow-1">
                                        <input type="text" class="form-control form-control-sm break-title" value="Morning Recess" placeholder="Break Title">
                                    </div>
                                    <div style="width: 140px;">
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">After P</span></div>
                                            <input type="number" class="form-control break-after" value="2" min="1" max="10">
                                        </div>
                                    </div>
                                    <div style="width: 130px;">
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control break-duration" value="15" min="5" max="60">
                                            <div class="input-group-append"><span class="input-group-text">min</span></div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="$(this).closest('.break-row').remove()"><i class="fa fa-times"></i></button>
                                </div>
                                <div class="break-row d-flex align-items-center gap-2 p-2 bg-white rounded border">
                                    <div class="flex-grow-1">
                                        <input type="text" class="form-control form-control-sm break-title" value="Lunch Break" placeholder="Break Title">
                                    </div>
                                    <div style="width: 140px;">
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend"><span class="input-group-text">After P</span></div>
                                            <input type="number" class="form-control break-after" value="4" min="1" max="10">
                                        </div>
                                    </div>
                                    <div style="width: 130px;">
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control break-duration" value="45" min="10" max="90">
                                            <div class="input-group-append"><span class="input-group-text">min</span></div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="$(this).closest('.break-row').remove()"><i class="fa fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Subject & Teacher Distribution -->
                <div id="wizardStep2" class="d-none">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <small class="text-muted">{{ __('Configure weekly period quotas and teachers for each subject.') }}</small>
                        <div id="quotaStatusBadge" class="quota-badge quota-ok">
                            <span id="allocatedQuota">0</span> / <span id="totalAvailableQuota">30</span> {{ __('Periods Assigned') }}
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                        <table class="table table-sm table-bordered">
                            <thead class="bg-light sticky-top">
                                <tr>
                                    <th>{{ __('Subject') }}</th>
                                    <th>{{ __('Primary Teacher') }}</th>
                                    <th>{{ __('Alternate Teacher') }}</th>
                                    <th style="width: 110px;">{{ __('Periods/Wk') }}</th>
                                </tr>
                            </thead>
                            <tbody id="wizardSubjectList">
                                <!-- Populated dynamically from setup modal or class subjects -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- STEP 3: Preview & Run Generation -->
                <div id="wizardStep3" class="d-none">
                    <div class="text-center py-3">
                        <div class="mb-3">
                            <i class="fa fa-cogs fa-3x text-primary"></i>
                        </div>
                        <h5 class="font-weight-bold text-dark">{{ __('Ready to Generate Schedule') }}</h5>
                        <p class="text-muted px-4">
                            {{ __('Our solver will evaluate school-wide teacher schedules in real-time, preventing double-booking, and assigning alternate teachers for backup.') }}
                        </p>

                        <div class="custom-control custom-checkbox my-3 d-inline-block">
                            <input type="checkbox" class="custom-control-input" id="genClearExisting" checked>
                            <label class="custom-control-label font-weight-bold" for="genClearExisting">
                                {{ __('Replace existing schedule for this class on selected days') }}
                            </label>
                        </div>

                        <div id="generationProgressContainer" class="d-none mt-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <div class="mt-2 text-primary font-weight-bold">{{ __('Analyzing constraints and building schedule...') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary mr-2 d-none" id="btnWizardPrev" onclick="stepWizard(-1)">
                        <i class="fa fa-arrow-left mr-1"></i> {{ __('Previous') }}
                    </button>
                    <button type="button" class="btn btn-primary" id="btnWizardNext" onclick="stepWizard(1)">
                        {{ __('Next') }} <i class="fa fa-arrow-right ml-1"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info mr-2 d-none" id="btnWizardPreview" onclick="runAutoGenerationPreview()">
                        <i class="fa fa-eye mr-1"></i> {{ __('Preview Results') }}
                    </button>
                    <button type="button" class="btn btn-gradient-success d-none" id="btnWizardSubmit" onclick="runAutoGeneration()">
                        <i class="fa fa-bolt mr-1"></i> {{ __('Generate & Apply') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: Quick Edit Slot Modal (Inline Editor on Matrix Click)
     ========================================================================= -->
<div class="modal fade" id="quickEditSlotModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 440px;">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark">{{ __('Edit Period Slot') }}</h5>
                    <small class="text-muted" id="quickEditSlotLabel">Monday • Period 1</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <input type="hidden" id="quickEditSlotId" />
                <div class="form-group mb-2">
                    <label class="font-weight-bold small">{{ __('Subject') }}</label>
                    <select id="quickEditSubjectId" class="form-control form-control-sm">
                        @foreach($allClassSubjects as $cs)
                            <option value="{{ $cs->subject_id }}">{{ $cs->subject->name }} ({{ $cs->subject->type }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-2">
                    <label class="font-weight-bold small">{{ __('Primary Teacher') }}</label>
                    <select id="quickEditTeacherId" class="form-control form-control-sm">
                        <option value="">{{ __('-- None --') }}</option>
                        @foreach($allTeachers as $t)
                            <option value="{{ $t->id }}">{{ $t->first_name }} {{ $t->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-2">
                    <label class="font-weight-bold small">{{ __('Alternate / Substitute Teacher') }}</label>
                    <select id="quickEditAltTeacherId" class="form-control form-control-sm">
                        <option value="">{{ __('-- None --') }}</option>
                        @foreach($allTeachers as $t)
                            <option value="{{ $t->id }}">{{ $t->first_name }} {{ $t->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-2">
                    <label class="font-weight-bold small">{{ __('Resource / Room / Lab') }}</label>
                    <select id="quickEditResourceId" class="form-control form-control-sm">
                        <option value="">{{ __('-- Default Classroom --') }}</option>
                        @foreach($resources as $res)
                            <option value="{{ $res->id }}">{{ $res->name }} ({{ $res->type }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-2">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="quickEditIsLocked" onchange="$('#quickEditLockReasonGroup').toggleClass('d-none', !this.checked)">
                        <label class="custom-control-label font-weight-bold small text-dark" for="quickEditIsLocked">
                            🔒 {{ __('Lock Slot (Prevent Auto-Regeneration from moving/deleting)') }}
                        </label>
                    </div>
                    <div id="quickEditLockReasonGroup" class="d-none mt-1">
                        <input type="text" id="quickEditLockReason" class="form-control form-control-sm" placeholder="{{ __('Reason for locking (e.g. Fixed Lab period, Visiting Teacher)') }}">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteSlotFromQuickEdit()">
                    <i class="fa fa-trash mr-1"></i> {{ __('Remove') }}
                </button>
                <button type="button" class="btn btn-sm btn-primary" onclick="saveQuickEditSlot()">
                    <i class="fa fa-check mr-1"></i> {{ __('Save Slot') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 4: Timetable V2 Generation Preview Modal
     ========================================================================= -->
<div class="modal fade" id="generationPreviewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark">
                        <i class="fa fa-tachometer text-primary mr-1"></i> {{ __('Timetable Generation Preview & Quality Report') }}
                    </h5>
                    <small class="text-muted" id="previewRunInfo">{{ __('Review solver metrics, clash analysis, and candidate slot distribution before applying.') }}</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <!-- KPI Metrics Cards -->
                <div class="row mb-3" id="previewMetricsRow">
                    <div class="col-md-3">
                        <div class="card bg-light border p-3 text-center">
                            <div class="text-muted small">{{ __('Quality Score') }}</div>
                            <h3 class="font-weight-bold text-success mb-0" id="previewQualityScore">--</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light border p-3 text-center">
                            <div class="text-muted small">{{ __('Total Slots Placed') }}</div>
                            <h3 class="font-weight-bold text-primary mb-0" id="previewSlotsPlaced">--</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light border p-3 text-center">
                            <div class="text-muted small">{{ __('Locked Slots Preserved') }}</div>
                            <h3 class="font-weight-bold text-warning mb-0" id="previewLockedPreserved">--</h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light border p-3 text-center">
                            <div class="text-muted small">{{ __('Hard Conflicts') }}</div>
                            <h3 class="font-weight-bold text-danger mb-0" id="previewConflictCount">0</h3>
                        </div>
                    </div>
                </div>

                <!-- Conflicts Alert -->
                <div id="previewConflictAlert" class="alert alert-danger d-none" role="alert">
                    <h6 class="font-weight-bold mb-1"><i class="fa fa-exclamation-circle mr-1"></i> {{ __('Unresolved Conflicts Detected:') }}</h6>
                    <ul class="mb-0 small" id="previewConflictList"></ul>
                </div>

                <!-- Preview Table of Entries -->
                <h6 class="font-weight-bold text-dark mb-2">{{ __('Generated Lesson Placement Preview') }}</h6>
                <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-striped" id="previewEntriesTable">
                        <thead class="bg-light">
                            <tr>
                                <th>{{ __('Day') }}</th>
                                <th>{{ __('Period / Time') }}</th>
                                <th>{{ __('Subject') }}</th>
                                <th>{{ __('Teacher') }}</th>
                                <th>{{ __('Room / Resource') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody id="previewEntriesBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <input type="hidden" id="previewRunId" />
                <button type="button" class="btn btn-outline-danger" onclick="cancelPreviewRun()">
                    <i class="fa fa-times mr-1"></i> {{ __('Discard Run') }}
                </button>
                <div>
                    <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal">{{ __('Close Preview') }}</button>
                    <button type="button" class="btn btn-gradient-success" id="btnApplyPreviewRun" onclick="applyPreviewRun()">
                        <i class="fa fa-check-circle mr-1"></i> {{ __('Apply & Save to Live Timetable') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 5: Subject Schedule Requirements & Labs Modal
     ========================================================================= -->
<div class="modal fade" id="subjectRequirementsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark">
                        <i class="fa fa-sliders text-primary mr-1"></i> {{ __('Subject Schedule Rules & Lab Requirements') }}
                    </h5>
                    <small class="text-muted">{{ __('Configure weekly period quotas, double-period/lab constraints, and room bookings.') }}</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>{{ __('Subject') }}</th>
                                <th style="width: 100px;">{{ __('Weekly Periods') }}</th>
                                <th style="width: 90px;">{{ __('Max/Day') }}</th>
                                <th style="width: 120px;">{{ __('Consecutive?') }}</th>
                                <th>{{ __('Dedicated Room / Lab') }}</th>
                                <th style="width: 80px;">{{ __('Priority') }}</th>
                            </tr>
                        </thead>
                        <tbody id="requirementsTableBody">
                            @foreach($allClassSubjects as $cs)
                                @php
                                    $req = $requirements->firstWhere('subject_id', $cs->subject_id);
                                @endphp
                                <tr class="req-row" data-subject-id="{{ $cs->subject_id }}" data-class-subject-id="{{ $cs->id }}">
                                    <td>
                                        <strong>{{ $cs->subject->name }}</strong>
                                        <div class="text-muted small">{{ $cs->subject->type }}</div>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm req-weekly" min="1" max="30" value="{{ $req ? $req->weekly_periods : 5 }}">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm req-max-day" min="1" max="4" value="{{ $req ? $req->max_periods_per_day : 1 }}">
                                    </td>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input req-consecutive" id="req_consec_{{ $cs->subject_id }}" {{ $req && $req->allow_consecutive ? 'checked' : '' }}>
                                            <label class="custom-control-label small" for="req_consec_{{ $cs->subject_id }}">{{ __('Allow Block') }}</label>
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm req-resource">
                                            <option value="">{{ __('-- None (Regular Classroom) --') }}</option>
                                            @foreach($resources as $res)
                                                <option value="{{ $res->id }}" {{ $req && $req->resource_id == $res->id ? 'selected' : '' }}>{{ $res->name }} ({{ $res->type }})</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm req-priority" min="1" max="10" value="{{ $req ? $req->priority : 1 }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="saveSubjectRequirements()">
                    <i class="fa fa-save mr-1"></i> {{ __('Save Subject Requirements') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    // Global data references
    const classSectionId = "{{ $classSection->id }}";
    let currentWizardStep = 1;

    // Switch between Compact Matrix View and Legacy FullCalendar View
    function switchTimetableView(viewType) {
        if (viewType === 'matrix') {
            $('#matrixViewContainer').removeClass('d-none');
            $('#calendarViewContainer').addClass('d-none');
            $('#btn-view-matrix').addClass('active');
            $('#btn-view-calendar').removeClass('active');
        } else {
            $('#matrixViewContainer').addClass('d-none');
            $('#calendarViewContainer').removeClass('d-none');
            $('#btn-view-matrix').removeClass('active');
            $('#btn-view-calendar').addClass('active');
            // Re-render FullCalendar if needed
            if (typeof createTimetable !== 'undefined') {
                createTimetable.render();
            }
        }
    }

    // Load Matrix Data via AJAX
    function loadMatrixData() {
        $('#matrixBody').html(`
            <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                    <i class="fa fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                    <div>{{ __('Refreshing timetable matrix...') }}</div>
                </td>
            </tr>
        `);

        $.ajax({
            url: `{{ url('timetable/get-matrix') }}/${classSectionId}`,
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                renderMatrixTable(res);
            },
            error: function(err) {
                $('#matrixBody').html(`
                    <tr>
                        <td colspan="7" class="text-center py-4 text-danger">
                            <i class="fa fa-exclamation-circle fa-2x mb-2"></i>
                            <div>{{ __('Failed to load timetable matrix. Please try again.') }}</div>
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Render Matrix HTML
    function renderMatrixTable(data) {
        const days = data.days || ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        const timeSlots = data.time_slots || [];
        const matrix = data.matrix || {};

        // Update Headers
        let headerHtml = `<th class="period-header-col">{{ __('Period / Time') }}</th>`;
        days.forEach(d => {
            headerHtml += `<th>${d}</th>`;
        });
        $('#matrixHeaderRow').html(headerHtml);

        if (timeSlots.length === 0) {
            $('#matrixBody').html(`
                <tr>
                    <td colspan="${days.length + 1}" class="text-center py-5 text-muted">
                        <i class="fa fa-calendar-o fa-3x text-secondary mb-2"></i>
                        <h5>{{ __('No Schedule Configured Yet') }}</h5>
                        <p class="small text-muted">{{ __('Use Auto-Generate to create an instant conflict-free schedule, or switch to Calendar view.') }}</p>
                        <button type="button" class="btn btn-sm btn-gradient-primary mt-2" data-toggle="modal" data-target="#autoGenerateModal">
                            <i class="fa fa-magic mr-1"></i> {{ __('Auto Generate Now') }}
                        </button>
                    </td>
                </tr>
            `);
            $('#conflictAlertBanner').addClass('d-none');
            return;
        }

        let bodyHtml = '';
        let hasConflicts = false;
        let conflictDetails = [];

        timeSlots.forEach((slot, pIdx) => {
            const periodLabel = slot.type === 'Break' ? (slot.title || 'Break') : `Period ${slot.period_no || (pIdx + 1)}`;
            const timeRange = `${slot.start_time.substring(0, 5)} - ${slot.end_time.substring(0, 5)}`;

            bodyHtml += `<tr>`;
            bodyHtml += `
                <td class="period-label-cell">
                    <div class="period-title">${periodLabel}</div>
                    <div class="period-time">${timeRange}</div>
                </td>
            `;

            days.forEach(day => {
                const daySlots = matrix[day] || {};
                const entry = daySlots[slot.start_time];

                if (entry) {
                    if (entry.type === 'Break') {
                        bodyHtml += `
                            <td>
                                <div class="break-card">
                                    <span>☕ ${entry.subject_name || 'Break'}</span>
                                    <small class="text-muted">${timeRange}</small>
                                </div>
                            </td>
                        `;
                    } else {
                        if (entry.conflict) {
                            hasConflicts = true;
                            conflictDetails.push(entry.conflict);
                        }

                        bodyHtml += `
                            <td>
                                <div class="slot-card ${entry.is_locked ? 'border-warning' : ''}" style="border-left-color: ${entry.bg_color || '#3B82F6'} !important; ${entry.is_locked ? 'background: #fffdf5;' : ''}">
                                    <div>
                                        <div class="slot-subject-title">
                                            <span>
                                                ${entry.is_locked ? `<span class="badge badge-warning text-dark font-weight-bold mr-1" title="${entry.lock_reason || 'Locked Slot'}">🔒</span>` : ''}
                                                ${entry.subject_name}
                                            </span>
                                            <span class="slot-subject-type">${timeRange}</span>
                                        </div>
                                        <div class="slot-teacher-info">
                                            <div class="slot-primary-teacher" title="Primary Teacher">
                                                <i class="fa fa-user-circle"></i>
                                                <span>${entry.primary_teacher_name || 'No Teacher'}</span>
                                            </div>
                                            ${entry.alt_teacher_name ? `
                                                <div class="slot-alt-teacher" title="Substitute / Alternate Teacher">
                                                    <i class="fa fa-refresh"></i>
                                                    <span>Alt: ${entry.alt_teacher_name}</span>
                                                </div>
                                            ` : ''}
                                            ${entry.resource_name ? `
                                                <div class="slot-alt-teacher text-info" title="Dedicated Room/Lab">
                                                    <i class="fa fa-map-marker"></i>
                                                    <span>${entry.resource_name}</span>
                                                </div>
                                            ` : ''}
                                            ${entry.conflict ? `
                                                <div class="slot-conflict-badge" title="${entry.conflict}">
                                                    <i class="fa fa-exclamation-triangle"></i>
                                                    <span>Conflict</span>
                                                </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                    <div class="slot-actions">
                                        ${entry.is_locked ? `
                                            <button type="button" class="slot-action-btn unlock-btn text-warning" title="Unlock Slot (${entry.lock_reason || 'Locked'})" onclick="toggleSlotLock(${entry.id}, false)">
                                                <i class="fa fa-lock"></i>
                                            </button>
                                        ` : `
                                            <button type="button" class="slot-action-btn lock-btn" title="Lock Slot" onclick="toggleSlotLock(${entry.id}, true)">
                                                <i class="fa fa-unlock"></i>
                                            </button>
                                        `}
                                        <button type="button" class="slot-action-btn" title="Edit Slot" onclick="openQuickEditSlot(${entry.id}, '${day}', '${periodLabel}', ${entry.subject_id || 0}, ${entry.primary_teacher_id || 0}, ${entry.alt_teacher_id || 0}, ${entry.resource_id || 0}, ${entry.is_locked ? 1 : 0}, '${(entry.lock_reason || '').replace(/'/g, "\\'")}')">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                        <button type="button" class="slot-action-btn delete-btn" title="Remove Slot" onclick="deleteSlotEntry(${entry.id})">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        `;
                    }
                } else {
                    bodyHtml += `
                        <td>
                            <div class="empty-slot-card" onclick="$('#autoGenerateModal').modal('show')" title="Click to configure">
                                <i class="fa fa-plus mr-1"></i> Empty
                            </div>
                        </td>
                    `;
                }
            });

            bodyHtml += `</tr>`;
        });

        $('#matrixBody').html(bodyHtml);

        // Show/hide Conflict Banner
        if (hasConflicts) {
            $('#conflictAlertBanner').removeClass('d-none');
            $('#conflictAlertText').text(conflictDetails[0] || 'Teacher schedule overlap detected across classes.');
        } else {
            $('#conflictAlertBanner').addClass('d-none');
        }
    }

    // Save & Sync Teacher Setup to Database
    function saveTeacherAssignments() {
        const assignments = [];
        $('.subject-setup-row').each(function() {
            const subId = $(this).data('subject-id');
            const teacherId = $(this).find('.select-primary-teacher').val();
            if (subId && teacherId) {
                assignments.push({
                    subject_id: subId,
                    teacher_id: teacherId
                });
            }
        });

        if (assignments.length === 0) {
            showToast('Please select a teacher for at least one subject.', 'warning');
            return;
        }

        $.ajax({
            url: "{{ route('timetable.sync-teachers') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                class_section_id: classSectionId,
                assignments: assignments
            },
            success: function(res) {
                showToast(res.message || 'Teacher assignments synchronized!', 'success');
                $('#teacherSetupModal').modal('hide');
                loadMatrixData();
            },
            error: function(err) {
                showToast('Failed to sync assignments.', 'error');
            }
        });
    }

    // Proceed from Teacher Setup directly to Auto-Generator
    function proceedToAutoGenerator() {
        saveTeacherAssignments();
        $('#teacherSetupModal').modal('hide');
        $('#autoGenerateModal').modal('show');
    }

    // Auto-Generator Wizard Navigation
    function goToWizardStep(step) {
        if (step === 2) {
            populateWizardSubjectList();
        }
        currentWizardStep = step;
        $('.stepper-item').removeClass('active');
        $(`#stepTab${step}`).addClass('active');

        $('#wizardStep1, #wizardStep2, #wizardStep3').addClass('d-none');
        $(`#wizardStep${step}`).removeClass('d-none');

        $('#btnWizardPrev').toggleClass('d-none', step === 1);
        $('#btnWizardNext').toggleClass('d-none', step === 3);
        $('#btnWizardPreview').toggleClass('d-none', step !== 3);
        $('#btnWizardSubmit').toggleClass('d-none', step !== 3);
    }

    function stepWizard(dir) {
        const nextStep = currentWizardStep + dir;
        if (nextStep >= 1 && nextStep <= 3) {
            goToWizardStep(nextStep);
        }
    }

    // Populate Subjects in Wizard Step 2
    function populateWizardSubjectList() {
        let rowsHtml = '';
        const totalPeriods = parseInt($('#genTotalPeriods').val()) || 6;
        const activeDaysCount = $('.gen-day-checkbox:checked').length || 5;
        const totalSlots = totalPeriods * activeDaysCount;
        $('#totalAvailableQuota').text(totalSlots);

        $('.subject-setup-row').each(function() {
            const subId = $(this).data('subject-id');
            const subName = $(this).find('strong').text();
            const primaryId = $(this).find('.select-primary-teacher').val();
            const altId = $(this).find('.select-alt-teacher').val();
            const periods = $(this).find('.input-weekly-periods').val() || 5;

            rowsHtml += `
                <tr class="wizard-subject-row" data-subject-id="${subId}">
                    <td><strong>${subName}</strong></td>
                    <td>
                        <select class="form-control form-control-sm wizard-primary-teacher">
                            <option value="">-- Select --</option>
                            @foreach($allTeachers as $t)
                                <option value="{{ $t->id }}" ${primaryId == {{ $t->id }} ? 'selected' : ''}>{{ $t->first_name }} {{ $t->last_name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <select class="form-control form-control-sm wizard-alt-teacher">
                            <option value="">-- None --</option>
                            @foreach($allTeachers as $t)
                                <option value="{{ $t->id }}" ${altId == {{ $t->id }} ? 'selected' : ''}>{{ $t->first_name }} {{ $t->last_name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm text-center wizard-periods" value="${periods}" min="1" max="15" onchange="calculateWizardQuota()">
                    </td>
                </tr>
            `;
        });

        $('#wizardSubjectList').html(rowsHtml);
        calculateWizardQuota();
    }

    function calculateWizardQuota() {
        let sum = 0;
        $('.wizard-periods').each(function() {
            sum += parseInt($(this).val()) || 0;
        });
        $('#allocatedQuota').text(sum);
        const total = parseInt($('#totalAvailableQuota').text()) || 30;

        if (sum > total) {
            $('#quotaStatusBadge').removeClass('quota-ok').addClass('quota-warn').text(`${sum} / ${total} Periods (Exceeds Slots!)`);
        } else {
            $('#quotaStatusBadge').removeClass('quota-warn').addClass('quota-ok').text(`${sum} / ${total} Periods Assigned`);
        }
    }

    function addBreakRow() {
        const breakHtml = `
            <div class="break-row d-flex align-items-center gap-2 mb-2 p-2 bg-white rounded border">
                <div class="flex-grow-1">
                    <input type="text" class="form-control form-control-sm break-title" value="Break" placeholder="Break Title">
                </div>
                <div style="width: 140px;">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend"><span class="input-group-text">After P</span></div>
                        <input type="number" class="form-control break-after" value="3" min="1" max="10">
                    </div>
                </div>
                <div style="width: 130px;">
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control break-duration" value="15" min="5" max="60">
                        <div class="input-group-append"><span class="input-group-text">min</span></div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="$(this).closest('.break-row').remove()"><i class="fa fa-times"></i></button>
            </div>
        `;
        $('#breaksListContainer').append(breakHtml);
    }

    // Run Auto-Generation API
    function runAutoGeneration() {
        const days = [];
        $('.gen-day-checkbox:checked').each(function() {
            days.push($(this).val());
        });

        if (days.length === 0) {
            showToast('Please select at least one working day.', 'warning');
            return;
        }

        const breaks = [];
        $('.break-row').each(function() {
            breaks.push({
                title: $(this).find('.break-title').val() || 'Break',
                after_period: parseInt($(this).find('.break-after').val()) || 1,
                duration: parseInt($(this).find('.break-duration').val()) || 15,
            });
        });

        const subjects = [];
        $('.wizard-subject-row').each(function() {
            subjects.push({
                subject_id: $(this).data('subject-id'),
                teacher_id: $(this).find('.wizard-primary-teacher').val() || null,
                alt_teacher_id: $(this).find('.wizard-alt-teacher').val() || null,
                weekly_periods: parseInt($(this).find('.wizard-periods').val()) || 4,
            });
        });

        $('#generationProgressContainer').removeClass('d-none');
        $('#btnWizardSubmit').prop('disabled', true);

        $.ajax({
            url: "{{ route('timetable.auto-generate') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                class_section_id: classSectionId,
                days: days,
                start_time: $('#genStartTime').val(),
                period_duration: $('#genPeriodDuration').val(),
                total_periods: $('#genTotalPeriods').val(),
                breaks: breaks,
                subjects: subjects,
                clear_existing: $('#genClearExisting').is(':checked') ? 1 : 0
            },
            success: function(res) {
                $('#generationProgressContainer').addClass('d-none');
                $('#btnWizardSubmit').prop('disabled', false);
                $('#autoGenerateModal').modal('hide');

                if (res.has_conflicts) {
                    showToast('Generated with conflict notices. Check the conflict banner.', 'warning');
                } else {
                    showToast(res.message || 'Timetable generated successfully without conflicts!', 'success');
                }

                loadMatrixData();
            },
            error: function(xhr) {
                $('#generationProgressContainer').addClass('d-none');
                $('#btnWizardSubmit').prop('disabled', false);
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error generating timetable.';
                showToast(msg, 'error');
            }
        });
    }

    // Quick Slot Edit Handlers
    function openQuickEditSlot(id, day, periodLabel, subjectId, teacherId, altTeacherId, resourceId, isLocked, lockReason) {
        $('#quickEditSlotId').val(id);
        $('#quickEditSlotLabel').text(`${day} • ${periodLabel}`);
        $('#quickEditSubjectId').val(subjectId);
        $('#quickEditTeacherId').val(teacherId || '');
        $('#quickEditAltTeacherId').val(altTeacherId || '');
        $('#quickEditResourceId').val(resourceId || '');
        $('#quickEditIsLocked').prop('checked', !!parseInt(isLocked));
        $('#quickEditLockReason').val(lockReason || '');
        $('#quickEditLockReasonGroup').toggleClass('d-none', !parseInt(isLocked));
        $('#quickEditSlotModal').modal('show');
    }

    function saveQuickEditSlot() {
        const id = $('#quickEditSlotId').val();
        const isLocked = $('#quickEditIsLocked').is(':checked') ? 1 : 0;
        const lockReason = $('#quickEditLockReason').val();

        $.ajax({
            url: "{{ route('timetable.quick-update') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                id: id,
                subject_id: $('#quickEditSubjectId').val(),
                teacher_id: $('#quickEditTeacherId').val(),
                alt_teacher_id: $('#quickEditAltTeacherId').val(),
                resource_id: $('#quickEditResourceId').val(),
                is_locked: isLocked,
                lock_reason: lockReason
            },
            success: function(res) {
                showToast(res.message || 'Slot updated successfully!', 'success');
                $('#quickEditSlotModal').modal('hide');
                loadMatrixData();
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Update failed.';
                showToast(msg, 'error');
            }
        });
    }

    // Direct Lock / Unlock Toggle from Slot Card
    function toggleSlotLock(id, shouldLock) {
        const url = shouldLock ? `{{ url('timetable/lock') }}/${id}` : `{{ url('timetable/unlock') }}/${id}`;
        let reason = null;
        if (shouldLock) {
            reason = prompt("Enter lock reason (e.g. Fixed Lab, Visiting Lecturer):", "Admin Lock");
            if (reason === null) return; // User cancelled
        }

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                reason: reason
            },
            success: function(res) {
                showToast(res.message || (shouldLock ? 'Slot locked!' : 'Slot unlocked!'), 'success');
                loadMatrixData();
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Lock toggle failed.';
                showToast(msg, 'error');
            }
        });
    }

    // Conflict Repair Function
    function runConflictRepair() {
        if (!confirm("Run intelligent minimum-move conflict repair for this class timetable?")) return;

        $.ajax({
            url: "{{ route('timetable.repair') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                class_section_id: classSectionId,
                dry_run: 0
            },
            success: function(res) {
                if (res.resolved_count > 0) {
                    showToast(`Repaired ${res.resolved_count} conflicts successfully!`, 'success');
                } else if (res.total_conflicts === 0) {
                    showToast('No conflicts detected in this schedule. All clear!', 'success');
                } else {
                    showToast(`Found ${res.total_conflicts} conflicts. ${res.unresolved_count} remain unresolved.`, 'warning');
                }
                loadMatrixData();
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Repair operation failed.';
                showToast(msg, 'error');
            }
        });
    }

    // Save Subject Schedule Requirements
    function saveSubjectRequirements() {
        const reqs = [];
        $('.req-row').each(function() {
            const subId = $(this).data('subject-id');
            const classSubId = $(this).data('class-subject-id');
            const weekly = $(this).find('.req-weekly').val() || 5;
            const maxDay = $(this).find('.req-max-day').val() || 1;
            const consecutive = $(this).find('.req-consecutive').is(':checked') ? 1 : 0;
            const resId = $(this).find('.req-resource').val() || null;
            const priority = $(this).find('.req-priority').val() || 1;

            reqs.push({
                subject_id: subId,
                class_subject_id: classSubId,
                weekly_periods: parseInt(weekly),
                max_periods_per_day: parseInt(maxDay),
                allow_consecutive: consecutive,
                required_consecutive_periods: consecutive ? 2 : null,
                resource_id: resId ? parseInt(resId) : null,
                resource_required: !!resId,
                priority: parseInt(priority)
            });
        });

        $.ajax({
            url: `{{ url('timetable/requirements') }}/${classSectionId}`,
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                requirements: reqs
            },
            success: function(res) {
                showToast(res.message || 'Subject requirements saved successfully!', 'success');
                $('#subjectRequirementsModal').modal('hide');
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to save requirements.';
                showToast(msg, 'error');
            }
        });
    }

    // Run Auto-Generation with Preview
    function runAutoGenerationPreview() {
        const days = [];
        $('.gen-day-checkbox:checked').each(function() {
            days.push($(this).val());
        });

        if (days.length === 0) {
            showToast('Please select at least one active school day.', 'warning');
            return;
        }

        $('#generationProgressContainer').removeClass('d-none');
        $('#btnWizardPreview, #btnWizardSubmit').prop('disabled', true);

        $.ajax({
            url: "{{ route('timetable.generate') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                class_section_id: classSectionId,
                preserve_existing: $('#genClearExisting').is(':checked') ? 0 : 1,
                mode: 'classes'
            },
            success: function(res) {
                $('#generationProgressContainer').addClass('d-none');
                $('#btnWizardPreview, #btnWizardSubmit').prop('disabled', false);
                $('#autoGenerateModal').modal('hide');

                // Populate Preview Modal
                $('#previewRunId').val(res.run_id);
                $('#previewQualityScore').text((res.metrics?.quality_score ?? 100) + '/100');
                $('#previewSlotsPlaced').text(res.entries_count ?? 0);
                $('#previewLockedPreserved').text(res.metrics?.preserved_locked ?? 0);
                $('#previewConflictCount').text(res.conflicts?.length ?? 0);

                if (res.conflicts && res.conflicts.length > 0) {
                    $('#previewConflictAlert').removeClass('d-none');
                    let listHtml = '';
                    res.conflicts.forEach(c => {
                        listHtml += `<li>${c.message || JSON.stringify(c)}</li>`;
                    });
                    $('#previewConflictList').html(listHtml);
                } else {
                    $('#previewConflictAlert').addClass('d-none');
                }

                // Populate Preview entries table
                let entriesHtml = '';
                (res.entries_preview || []).forEach(e => {
                    entriesHtml += `
                        <tr>
                            <td><strong>${e.day}</strong></td>
                            <td>${e.start_time.substring(0, 5)} - ${e.end_time.substring(0, 5)}</td>
                            <td>${e.subject_name || 'Subject #' + e.subject_id}</td>
                            <td>${e.teacher_name || 'Teacher #' + e.teacher_id}</td>
                            <td>${e.resource_name || '<span class="text-muted">Standard</span>'}</td>
                            <td><span class="badge badge-success">Candidate</span></td>
                        </tr>
                    `;
                });
                $('#previewEntriesBody').html(entriesHtml);

                $('#generationPreviewModal').modal('show');
            },
            error: function(xhr) {
                $('#generationProgressContainer').addClass('d-none');
                $('#btnWizardPreview, #btnWizardSubmit').prop('disabled', false);
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Preview generation failed.';
                showToast(msg, 'error');
            }
        });
    }

    // Apply Previewed Generation Run
    function applyPreviewRun() {
        const runId = $('#previewRunId').val();
        if (!runId) return;

        $('#btnApplyPreviewRun').prop('disabled', true);
        $.ajax({
            url: `{{ url('timetable/generation') }}/${runId}/apply`,
            type: 'POST',
            data: { _token: "{{ csrf_token() }}" },
            success: function(res) {
                $('#btnApplyPreviewRun').prop('disabled', false);
                $('#generationPreviewModal').modal('hide');
                showToast(res.message || 'Timetable applied successfully!', 'success');
                loadMatrixData();
            },
            error: function(xhr) {
                $('#btnApplyPreviewRun').prop('disabled', false);
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Apply failed.';
                showToast(msg, 'error');
            }
        });
    }

    // Cancel Previewed Generation Run
    function cancelPreviewRun() {
        const runId = $('#previewRunId').val();
        if (!runId) {
            $('#generationPreviewModal').modal('hide');
            return;
        }

        $.ajax({
            url: `{{ url('timetable/generation') }}/${runId}/cancel`,
            type: 'POST',
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                $('#generationPreviewModal').modal('hide');
                showToast('Generation run discarded.', 'info');
            },
            error: function() {
                $('#generationPreviewModal').modal('hide');
            }
        });
    }

    function deleteSlotFromQuickEdit() {
        const id = $('#quickEditSlotId').val();
        deleteSlotEntry(id);
        $('#quickEditSlotModal').modal('hide');
    }

    function deleteSlotEntry(id) {
        if (!confirm("Are you sure you want to remove this timetable slot?")) return;
        $.ajax({
            url: `{{ url('timetable') }}/${id}`,
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                showToast('Slot removed.', 'success');
                loadMatrixData();
            },
            error: function() {
                showToast('Failed to remove slot.', 'error');
            }
        });
    }

    function clearClassTimetable() {
        if (!confirm("Are you sure you want to clear the entire timetable for this class?")) return;
        $.ajax({
            url: `{{ url('timetable/delete') }}/${classSectionId}`,
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function() {
                showToast('Timetable cleared.', 'success');
                loadMatrixData();
            },
            error: function() {
                showToast('Failed to clear timetable.', 'error');
            }
        });
    }

    function showToast(msg, type) {
        if (typeof $.toast === 'function') {
            $.toast({
                text: msg,
                icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                loader: false,
                position: 'top-right',
                duration: 3500
            });
        } else {
            alert(msg);
        }
    }

    // FullCalendar Events Initialization (Preserved for Timeline View)
    $(document).ready(function () {
        loadMatrixData();

        @if(isset($timetables))
            @foreach ($timetables as $timetable)
                @php
                    $filtered = null;
                    if ($timetable->subject && $timetable->subject->class_subjects) {
                        $filtered = $timetable->subject->class_subjects->where('class_id', $classSection->class_id)->first();
                    }
                @endphp
                if (typeof createTimetable !== 'undefined' && typeof days !== 'undefined') {
                    createTimetable.addEvent({
                        title: "{{ $timetable->title }}",
                        daysOfWeek: [days.indexOf("{{ $timetable->day }}")],
                        startTime: "{{ $timetable->start_time }}",
                        endTime: "{{ $timetable->end_time }}",
                        color: "{{ $timetable->subject->bg_color ?? 'Black' }}",
                        id: "{{ $timetable->id }}",
                        subject_type: "{{ $filtered?->type ?? '' }}",
                    });
                }
            @endforeach
        @endif

        if (typeof createTimetable !== 'undefined') {
            createTimetable.setOption("slotMinTime", "{{ !empty($timetableSettingsData['timetable_start_time']) && $timetableSettingsData['timetable_start_time'] !== '00:00:00' ? $timetableSettingsData['timetable_start_time'] : '08:00:00' }}");
            createTimetable.setOption("slotMaxTime", "{{ !empty($timetableSettingsData['timetable_end_time']) && $timetableSettingsData['timetable_end_time'] !== '00:00:00' ? $timetableSettingsData['timetable_end_time'] : '18:00:00' }}");
            createTimetable.setOption("slotDuration", "{{ !empty($timetableSettingsData['timetable_duration']) && $timetableSettingsData['timetable_duration'] !== '00:00:00' ? $timetableSettingsData['timetable_duration'] : '00:45:00' }}");
        }

        // Auto-launch generator if requested via ?autogen=1
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autogen') === '1') {
            setTimeout(function() {
                if (typeof goToWizardStep === 'function') {
                    goToWizardStep(1);
                }
                $('#autoGenerateModal').modal('show');
            }, 400);
        }
    });
</script>
@endsection