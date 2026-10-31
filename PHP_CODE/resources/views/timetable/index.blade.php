@extends('layouts.master')

@section('title')
    {{ __('timetable') }}
@endsection

@section('css')
<style>
    /* =========================================================
       Zolo SmartSchool Modern Timetable Design System
       High Legibility Inter Typography & Unified Box Aesthetics
       ========================================================= */
    .timetable-wrapper {
        font-family: var(--zolo-font-body, 'Inter', sans-serif);
        padding: 10px 14px !important;
    }

    /* Top Bars (Compact 2-Line Header) */
    .timetable-topbar-line1 {
        background: #ffffff;
        border: 1px solid #D8E2EC;
        border-radius: 10px;
        padding: 6px 12px !important;
        margin-bottom: 6px !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        transition: all 0.2s ease;
    }
    .timetable-topbar-line2 {
        background: #ffffff;
        border: 1px solid #D8E2EC;
        border-radius: 10px;
        padding: 6px 12px !important;
        margin-bottom: 8px !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        transition: all 0.2s ease;
    }
    .timetable-topbar-line1:hover,
    .timetable-topbar-line2:hover {
        border-color: #CBD5E1;
    }

    /* View Switcher Segmented Control */
    .view-mode-switcher .btn {
        font-size: 11.5px;
        font-weight: 600;
        padding: 3px 10px;
        letter-spacing: 0.2px;
        border-color: #CBD5E1;
    }
    .view-mode-switcher .btn.active {
        background: var(--theme-color, #22577A) !important;
        border-color: var(--theme-color, #22577A) !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(34, 87, 122, 0.25);
    }

    /* =========================================================
       View 1: Single Class Detailed Timetable Grid
       ========================================================= */
    .single-class-container {
        background: #ffffff;
        border: 1px solid #D8E2EC;
        border-radius: 12px;
        padding: 10px 14px;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
    }

    /* Subject Palette Tray (Docked at top when editing) */
    .subject-palette-tray {
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        padding: 8px 12px;
        margin-bottom: 10px;
    }
    .palette-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        background: #ffffff;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        cursor: grab;
        user-select: none;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .palette-chip:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        border-color: #3B82F6;
    }
    .palette-chip.dragging {
        opacity: 0.4;
        cursor: grabbing;
    }
    .palette-color-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .palette-subject-name {
        font-weight: 700;
        font-size: 11.5px;
        color: #0F172A;
    }
    .palette-badge {
        font-size: 9.5px;
        font-weight: 600;
        padding: 1px 5px;
        border-radius: 4px;
        background: #F1F5F9;
        color: #475569;
    }

    /* The Main Timetable Matrix Table - Screen-Fitting & Zero Overflow */
    .single-matrix-table {
        width: 100% !important;
        max-width: 100% !important;
        border-collapse: separate;
        border-spacing: 5px;
        table-layout: fixed;
    }
    #single-matrix-wrapper {
        width: 100%;
        overflow-x: hidden;
    }
    @media (max-width: 991px) {
        #single-matrix-wrapper {
            overflow-x: auto;
        }
    }
    .single-matrix-table th {
        background: var(--theme-color, #22577A) !important;
        color: #FFFFFF !important;
        font-family: var(--zolo-font-heading, 'Inter', sans-serif) !important;
        font-weight: 700;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 8px 6px;
        text-align: center;
        border-radius: 6px;
        border: 1px solid rgba(0, 0, 0, 0.12);
        height: 34px;
        vertical-align: middle;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }
    .single-matrix-table th.day-header-col {
        background: var(--theme-color, #22577A) !important;
        color: #FFFFFF !important;
        font-size: 13px;
        font-weight: 700;
        border-color: rgba(255, 255, 255, 0.18);
    }
    .single-matrix-table th.time-header-col,
    .single-matrix-table th:first-child {
        background: #1B4461 !important;
        color: #FFFFFF !important;
        font-weight: 700;
        border-color: rgba(255, 255, 255, 0.18);
    }
    .single-matrix-table td {
        vertical-align: top;
        padding: 0;
        height: 68px;
    }
    .single-matrix-table td.time-col-cell {
        vertical-align: middle;
        text-align: center;
        background: #F8FAFC;
        border: 1px solid #CBD5E1;
        border-left: 3px solid var(--theme-color, #22577A);
        border-radius: 8px;
        padding: 4px 6px;
        width: 100px !important;
        max-width: 100px !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
    }
    .period-number-badge {
        font-size: 11.5px;
        font-weight: 700;
        color: #1E293B;
        line-height: 1.1;
    }
    .period-time-range {
        font-size: 9.5px;
        color: #64748B;
        font-weight: 500;
        margin-top: 1px;
        line-height: 1.1;
    }

    /* Modern Slot Card (Compact & Crisp) */
    .modern-slot-card {
        background: #ffffff;
        border: 1px solid #CBD5E1;
        border-radius: 8px;
        padding: 5px 7px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }
    .modern-slot-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
        border-color: #CBD5E1;
    }
    .modern-slot-card.is-draggable {
        cursor: grab;
    }
    .modern-slot-card.is-draggable:active {
        cursor: grabbing;
    }
    .modern-slot-card.dragging {
        opacity: 0.4;
    }
    .slot-top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2px;
    }
    .slot-subject-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 11px;
        color: #ffffff;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
    }
    .slot-teacher-row {
        font-size: 10.5px;
        font-weight: 500;
        color: #334155;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 1px;
        line-height: 1.2;
    }
    .slot-bottom-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 3px;
        margin-top: 2px;
        font-size: 9px;
    }

    /* Proximity / Block Tagging */
    .proximity-badge {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        padding: 1px 5px;
        border-radius: 3px;
        font-weight: 600;
        font-size: 9px;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.2;
    }
    .proximity-badge.same-block {
        background: #DCFCE7;
        color: #15803D;
        border: 1px solid #BBF7D0;
    }
    .proximity-badge.diff-block {
        background: #FEF3C7;
        color: #B45309;
        border: 1px solid #FDE68A;
    }

    /* Empty Drop Zone Cell */
    .empty-slot-zone {
        height: 100%;
        min-height: 56px;
        border: 1.5px dashed #E2E8F0;
        border-radius: 8px;
        background: #FAFAFA;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #94A3B8;
        font-size: 10px;
        font-weight: 500;
        transition: all 0.15s ease;
        padding: 3px;
        text-align: center;
    }
    .empty-slot-zone:hover {
        border-color: #94A3B8;
        background: #F8FAFC;
        color: #64748B;
    }
    .cell-drop-target.drag-over .empty-slot-zone,
    .cell-drop-target.drag-over .modern-slot-card {
        border: 2px dashed #2563EB !important;
        background: #EFF6FF !important;
        transform: scale(1.01);
    }

    /* =========================================================
       SLEEK BREAK ROW & CELLS (Compact ~32px Height)
       ========================================================= */
    .single-matrix-table tr.break-period-row td {
        height: 32px !important;
        min-height: 32px !important;
        max-height: 32px !important;
        padding: 0 !important;
        vertical-align: middle;
    }
    .single-matrix-table tr.break-period-row td.time-col-cell {
        height: 32px !important;
        min-height: 32px !important;
        max-height: 32px !important;
        padding: 2px 4px !important;
        background: #FEF3C7;
        border-color: #FDE68A;
    }
    .single-matrix-table tr.break-period-row .period-number-badge {
        font-size: 10.5px;
        color: #92400E;
        font-weight: 700;
        line-height: 1;
    }
    .single-matrix-table tr.break-period-row .period-time-range {
        font-size: 9px;
        color: #B45309;
        margin-top: 1px;
        line-height: 1;
    }

    .break-slot-cell {
        height: 30px !important;
        min-height: 30px !important;
        max-height: 30px !important;
        border-radius: 6px;
        background: #FFFDF5;
        border: 1px dashed #FCD34D;
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        gap: 6px;
        color: #92400E;
        font-size: 11px;
        font-weight: 600;
        padding: 0 8px;
        margin: 0;
        box-shadow: none;
    }
    .break-slot-cell i {
        font-size: 11px;
        color: #D97706;
        margin: 0 !important;
    }

    /* Card Action Overlay in Edit Mode */
    .slot-edit-tools {
        position: absolute;
        top: 6px;
        right: 6px;
        display: none;
        gap: 3px;
        z-index: 5;
    }
    .modern-slot-card:hover .slot-edit-tools {
        display: flex;
    }
    .slot-tool-btn {
        width: 22px;
        height: 22px;
        border-radius: 5px;
        border: 1px solid #CBD5E1;
        background: #ffffff;
        color: #475569;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        transition: all 0.15s;
    }
    .slot-tool-btn:hover {
        background: #F1F5F9;
        color: #0F172A;
    }
    .slot-tool-btn.btn-delete:hover {
        background: #FEE2E2;
        color: #DC2626;
        border-color: #FCA5A5;
    }

    /* =========================================================
       View 2: 3-Column Card View Preview
       Fresh modern card grid without repetitive AI boxes
       ========================================================= */
    .class-card-preview {
        background: #ffffff;
        border: 1px solid #E2E8F0;
        border-radius: 16px;
        padding: 18px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .class-card-preview:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
        border-color: #93C5FD;
    }
    .class-card-preview::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #2563EB, #7C3AED);
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .class-card-preview:hover::before {
        opacity: 1;
    }
    .class-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .class-card-title {
        font-size: 16px;
        font-weight: 700;
        color: #0F172A;
        line-height: 1.3;
        margin: 0;
    }
    .class-card-meta {
        font-size: 12px;
        color: #64748B;
        margin-top: 2px;
    }
    .class-progress-bar-wrap {
        margin: 12px 0;
    }
    .class-progress-track {
        height: 8px;
        background: #E2E8F0;
        border-radius: 999px;
        overflow: hidden;
    }
    .class-progress-fill {
        height: 100%;
        border-radius: 999px;
        transition: width 0.4s ease;
    }
    .subject-pill-cluster {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-bottom: 14px;
    }
    .subject-mini-chip {
        font-size: 11px;
        font-weight: 500;
        padding: 2px 7px;
        border-radius: 6px;
        background: #F1F5F9;
        color: #334155;
    }

    /* Print Optimization */
    @media print {
        .navbar, .sidebar, .page-header, .timetable-topbar-line1, .timetable-topbar-line2, .subject-palette-tray, .slot-edit-tools, #btn-print-timetable {
            display: none !important;
        }
        .content-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
        }
        .single-class-container {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
        .single-matrix-table td {
            height: 90px !important;
        }
    }
</style>
@endsection

@section('content')
<div class="content-wrapper timetable-wrapper">
    <!-- Page Title -->
    <div class="page-header d-flex justify-content-between align-items-center mb-2">
        <h3 class="page-title mb-0 font-weight-bold text-dark">
            <i class="fa fa-calendar-check-o text-primary mr-2"></i>{{ __('Timetable Management') }}
        </h3>
    </div>

    <!-- Line 1: School Operating Hours Settings & Global Action Hub -->
    <div class="timetable-topbar-line1 d-flex flex-wrap align-items-center justify-content-between p-2 mb-2">
        <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
            <form class="form-inline d-flex flex-wrap align-items-center m-0 timetable-settings-form" action="{{ route('timetable.settings') }}" method="POST" id="compactSettingsForm">
                @csrf
                <span class="text-dark font-weight-bold mr-2 small">
                    <i class="fa fa-clock-o text-primary mr-1"></i>{{ __('Hours') }}:
                </span>
                <div class="input-group input-group-sm mr-2" style="width: 135px;" title="{{ __('Starting Time') }}">
                    <div class="input-group-prepend"><span class="input-group-text px-1 py-0"><small>{{ __('Start') }}</small></span></div>
                    <input type="time" name="timetable_start_time" class="form-control form-control-sm px-1" value="{{ $timetableData['timetable_start_time'] ?? '09:00:00' }}" required />
                </div>
                <div class="input-group input-group-sm mr-2" style="width: 135px;" title="{{ __('Ending Time') }}">
                    <div class="input-group-prepend"><span class="input-group-text px-1 py-0"><small>{{ __('End') }}</small></span></div>
                    <input type="time" name="timetable_end_time" class="form-control form-control-sm px-1" value="{{ $timetableData['timetable_end_time'] ?? '18:00:00' }}" required />
                </div>
                <div class="input-group input-group-sm mr-2" style="width: 110px;" title="{{ __('Timeslot Duration in minutes') }}">
                    <div class="input-group-prepend"><span class="input-group-text px-1 py-0"><small>{{ __('Slot') }}</small></span></div>
                    <input type="number" name="timetable_duration" min="1" max="180" class="form-control form-control-sm px-1" value="{{ $timetableData['timetable_duration'] ?? 45 }}" required />
                    <div class="input-group-append"><span class="input-group-text px-1 py-0"><small>m</small></span></div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary px-2" title="{{ __('Save Timetable Settings') }}">
                    <i class="fa fa-save mr-1"></i><span class="d-none d-sm-inline">{{ __('Save') }}</span>
                </button>
            </form>

            <div class="custom-control custom-switch ml-md-2" title="{{ __('Prioritize scheduling lab/room resources located in the same building/block as the class section') }}">
                <input type="checkbox" class="custom-control-input" id="prioritize_same_block_switch" {{ !empty($prioritizeSameBlock) ? 'checked' : '' }} onchange="togglePrioritizeSameBlock(this.checked)">
                <label class="custom-control-label small font-weight-bold text-dark cursor-pointer mb-0" for="prioritize_same_block_switch">
                    <i class="fa fa-building-o text-info mr-1"></i>{{ __('Prioritize Same-Block Labs') }}
                </label>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center mt-2 mt-lg-0" style="gap: 5px;">
            <button type="button" class="btn btn-sm btn-outline-info" data-toggle="modal" data-target="#templatesModal">
                <i class="fa fa-calendar-check-o mr-1"></i> {{ __('Bell Schedules') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#resourcesModal">
                <i class="fa fa-building mr-1"></i> {{ __('Rooms & Labs') }}
            </button>
            <a href="{{ route('timetable.teacher-matrix') }}" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-th mr-1"></i> {{ __('Teacher Matrix') }}
            </a>
            <button type="button" class="btn btn-sm btn-gradient-primary" data-toggle="modal" data-target="#chooseClassForAutoGenerateModal">
                <i class="fa fa-magic mr-1"></i> {{ __('Auto-Generate') }}
            </button>
            @can('timetable-create')
            <button type="button" class="btn btn-sm btn-gradient-success" data-toggle="modal" data-target="#batchGenerateModal">
                <i class="fa fa-sitemap mr-1"></i> {{ __('School-Wide') }}
            </button>
            @endcan
        </div>
    </div>

    <!-- Line 2: View Switcher, Filters & Navigation Controls -->
    <div class="timetable-topbar-line2 d-flex flex-wrap align-items-center justify-content-between p-2 mb-3">
        <!-- View Switcher Tabs -->
        <div class="btn-group btn-group-sm mr-2 view-mode-switcher" role="group">
            <button type="button" class="btn btn-outline-primary active" id="btn-view-single" onclick="switchMainView('single')">
                <i class="fa fa-calendar mr-1"></i> {{ __('Single Class View') }}
            </button>
            <button type="button" class="btn btn-outline-primary" id="btn-view-cards" onclick="switchMainView('cards')">
                <i class="fa fa-th-large mr-1"></i> {{ __('Card View (3-Col)') }}
            </button>
            <button type="button" class="btn btn-outline-primary" id="btn-view-list" onclick="switchMainView('list')">
                <i class="fa fa-list mr-1"></i> {{ __('List View') }}
            </button>
        </div>

        <!-- Filters & Step Navigator -->
        <div class="d-flex flex-wrap align-items-center flex-grow-1 justify-content-end" style="gap: 8px;">
            <div class="d-flex align-items-center" style="max-width: 140px;">
                {!! Form::select('medium_id', $mediums, null, ['class' => 'form-control form-control-sm','id' => 'filter_medium_id', 'placeholder' => __('All Mediums')]) !!}
            </div>

            <!-- Single Class Step Navigator -->
            <div id="single-class-nav-group" class="d-flex align-items-center">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <button class="btn btn-outline-secondary px-2" type="button" id="btn-nav-prev-class" title="{{ __('Previous Class') }}" onclick="navigateClassSection(-1)">
                            <i class="fa fa-chevron-left"></i>
                        </button>
                    </div>
                    <select id="main_class_selector" class="form-control form-control-sm font-weight-bold" style="min-width: 180px;" onchange="onClassSectionChange(this.value)">
                        @foreach($classSections as $cs)
                            <option value="{{ $cs->id }}" data-medium="{{ $cs->medium_id }}" data-block="{{ $cs->block }}" {{ $loop->first ? 'selected' : '' }}>
                                {{ $cs->full_name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary px-2" type="button" id="btn-nav-next-class" title="{{ __('Next Class') }}" onclick="navigateClassSection(1)">
                            <i class="fa fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- List View Class Filter (School-Wide by default or filter by specific class) -->
            <div id="list-view-filter-group" class="d-none align-items-center">
                <select id="list_filter_class_section_id" class="form-control form-control-sm font-weight-bold" style="min-width: 200px;" onchange="$('#table_list').bootstrapTable('refresh')">
                    <option value="">{{ __('All Classes (School-Wide)') }}</option>
                    @foreach($classSections as $cs)
                        <option value="{{ $cs->id }}" data-medium="{{ $cs->medium_id }}">
                            {{ $cs->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Class Section Metadata Chips -->
            <div id="single-class-meta-badges" class="d-flex align-items-center" style="gap: 6px;">
                <button type="button" class="btn btn-xs btn-outline-dark font-weight-bold" id="badge-class-block" onclick="openClassBlockModal()" title="{{ __('Click to set or change Building / Block') }}">
                    <i class="fa fa-map-marker text-danger mr-1"></i><span id="label-class-block">{{ __('Block: -') }}</span>
                </button>
                <span class="badge badge-light border text-dark py-1 px-2 font-weight-bold d-none d-md-inline-block" id="badge-class-teacher" title="{{ __('Class Teacher') }}">
                    <i class="fa fa-user-circle text-primary mr-1"></i><span id="label-class-teacher">-</span>
                </span>
            </div>

            <!-- Interactive Mode Switch & Print -->
            <div id="single-class-actions" class="d-flex align-items-center" style="gap: 6px;">
                <div class="custom-control custom-switch mr-1" title="{{ __('Enable drag & drop and slot swapping') }}">
                    <input type="checkbox" class="custom-control-input" id="interactive_edit_mode_toggle" onchange="toggleEditMode(this.checked)">
                    <label class="custom-control-label small font-weight-bold text-primary cursor-pointer mb-0" for="interactive_edit_mode_toggle">
                        <i class="fa fa-pencil-square-o mr-1"></i>{{ __('Edit Mode') }}
                    </label>
                </div>
                <button type="button" class="btn btn-xs btn-outline-secondary" onclick="printSingleClassTimetable()" title="{{ __('Print Printable Timetable') }}">
                    <i class="fa fa-print mr-1"></i>{{ __('Print') }}
                </button>
            </div>
        </div>
    </div>

    <!-- VIEW 1: SINGLE CLASS VIEW (High Legibility Roboto, Interactive Drag & Drop / Swappable) -->
    <div id="view-container-single">
        <!-- Interactive Subject Palette Tray (Active during Edit Mode) -->
        <div id="subject-palette-container" class="subject-palette-tray d-none">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="font-weight-bold text-dark small">
                    <i class="fa fa-hand-grab-o text-primary mr-1"></i>{{ __('Subject Palette — Drag any subject card into an empty or occupied slot in the timetable below') }}
                </span>
                <span class="badge badge-primary px-2 py-1" id="palette-class-title">{{ __('Class') }}</span>
            </div>
            <div id="subject-palette-list" class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                <!-- Populated via AJAX -->
            </div>
        </div>

        <!-- The Single Class Timetable Grid -->
        <div class="single-class-container">
            <div id="single-matrix-loading" class="text-center py-5 d-none">
                <i class="fa fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                <p class="text-muted small">{{ __('Loading timetable schedule…') }}</p>
            </div>
            <div id="single-matrix-wrapper" class="table-responsive">
                <table class="single-matrix-table" id="single-matrix-table">
                    <thead id="single-matrix-thead">
                        <!-- Populated via JS -->
                    </thead>
                    <tbody id="single-matrix-tbody">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- VIEW 2: CARD VIEW PREVIEW (3 COLUMNS) -->
    <div id="view-container-cards" class="d-none">
        <div id="cards-loading" class="text-center py-5 d-none">
            <i class="fa fa-spinner fa-spin fa-2x text-primary mb-2"></i>
            <p class="text-muted small">{{ __('Loading class timetable cards…') }}</p>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3" id="cards-grid-container" style="row-gap: 20px;">
            <!-- Populated via AJAX -->
        </div>
    </div>

    <!-- VIEW 3: LIST VIEW (Traditional Bootstrap Table) -->
    <div id="view-container-list" class="d-none">
        <div class="card">
            <div class="card-body p-3">
                <div class="row">
                    <div class="col-12">
                        <table aria-describedby="mydesc" class='table table-bordered' id='table_list'
                               data-toggle="table" data-url="{{ route('timetable.show',[1]) }}"
                               data-click-to-select="true" data-side-pagination="server"
                               data-pagination="true" data-page-list="[5, 10, 20, 50, 100, 200]"
                               data-search="true"
                               data-show-columns="true" data-show-refresh="true"
                               data-fixed-columns="false"
                               data-trim-on-search="false"
                               data-mobile-responsive="true" data-sort-name="id"
                               data-query-params="timetableQueryParams" data-sort-order="desc"
                               data-maintain-selected="true" data-export-data-type='all' data-show-export="true"
                               data-export-options='{ "fileName": "timetable-list-{{ date("d-m-y") }}" }'>
                            <thead>
                            <tr>
                                <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                                <th scope="col" data-field="no" data-width="40">{{ __('no.') }}</th>
                                <th scope="col" data-field="full_name" data-width="140">{{ __('Class Section') }}</th>
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

<!-- =========================================================
     MODAL: Popup Class Timetable View (Opened from Card Click)
     ========================================================= -->
<div class="modal fade" id="classTimetablePopupModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content" style="border-radius: 16px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark" id="popup-modal-class-title">
                        <i class="fa fa-calendar text-primary mr-1"></i> {{ __('Class Timetable Preview') }}
                    </h5>
                    <small class="text-muted" id="popup-modal-class-subtitle"></small>
                </div>
                <div class="d-flex align-items-center" style="gap: 8px;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printPopupTimetable()">
                        <i class="fa fa-print mr-1"></i> {{ __('Print') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" onclick="openPopupClassInSingleView()">
                        <i class="fa fa-pencil mr-1"></i> {{ __('Interactive Edit') }}
                    </button>
                    <button type="button" class="close ml-2" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body p-3" id="popup-timetable-container">
                <!-- Timetable injected here -->
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: Teacher Schedule Conflict Warning & Smart Resolution
     ========================================================= -->
<div class="modal fade" id="teacherConflictModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-white text-danger d-flex align-items-center justify-content-center mr-3" style="width: 38px; height: 38px;">
                        <i class="fa fa-exclamation-triangle fa-lg"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0 text-white">{{ __('Teacher Schedule Conflict') }}</h5>
                        <small class="text-white-50">{{ __('Assigned teacher is already occupied at this time') }}</small>
                    </div>
                </div>
                <button type="button" class="close text-white opacity-1" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <!-- Conflict Summary Banner -->
                <div class="alert alert-danger border-danger shadow-sm mb-4" id="conflict-modal-banner" style="border-radius: 10px;">
                    <!-- Injected dynamically -->
                </div>

                <!-- Section: Smart Calculated Solutions -->
                <h6 class="font-weight-bold text-dark mb-3">
                    <i class="fa fa-lightbulb-o text-warning mr-1"></i> {{ __('Recommended Conflict Resolutions') }}
                </h6>

                <!-- Option A: Free Alternate Slots -->
                <div class="card mb-3 border" style="border-radius: 12px;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-weight-bold text-dark small">
                                <i class="fa fa-calendar-check-o text-success mr-1"></i>
                                {{ __('Option 1: Pick an Alternative Free Slot') }}
                            </span>
                            <span class="badge badge-light border text-muted small" id="conflict-free-slots-count">0 available</span>
                        </div>
                        <p class="text-muted small mb-2">
                            {{ __('Timeslots where both this teacher and this class are completely free:') }}
                        </p>
                        <div id="conflict-alternative-slots-list" class="d-flex flex-wrap" style="gap: 8px;">
                            <!-- Injected dynamically -->
                        </div>
                    </div>
                </div>

                <!-- Option B: Alternative Available Teachers -->
                <div class="card mb-3 border" style="border-radius: 12px;">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="font-weight-bold text-dark small">
                                <i class="fa fa-users text-primary mr-1"></i>
                                {{ __('Option 2: Assign an Available Alternate Teacher') }}
                            </span>
                            <span class="badge badge-light border text-muted small" id="conflict-alt-teachers-count">0 available</span>
                        </div>
                        <p class="text-muted small mb-2">
                            {{ __('Other qualified teachers for this subject who are free at this hour:') }}
                        </p>
                        <div id="conflict-alternative-teachers-list" class="d-flex flex-wrap" style="gap: 8px;">
                            <!-- Injected dynamically -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-dismiss="modal">
                    <i class="fa fa-times mr-1"></i> {{ __('Cancel') }}
                </button>
                <button type="button" class="btn btn-danger btn-sm px-3 font-weight-bold" id="btn-conflict-override" onclick="executePendingConflictOverride()">
                    <i class="fa fa-bolt mr-1"></i> {{ __('Assign Anyway (Override Conflict)') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: Assign Building / Block to Class Section
     ========================================================= -->
<div class="modal fade" id="setClassBlockModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <h6 class="modal-title font-weight-bold text-dark">
                    <i class="fa fa-building-o text-primary mr-1"></i> {{ __('Assign Building / Block') }}
                </h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form onsubmit="saveClassBlockSubmit(event)">
                <div class="modal-body p-3">
                    <div class="form-group mb-2">
                        <label class="small font-weight-bold text-muted">{{ __('Class Section') }}</label>
                        <input type="text" id="modal_block_class_name" class="form-control form-control-sm" readonly />
                        <input type="hidden" id="modal_block_class_id" />
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-dark">{{ __('Block / Wing Name') }}</label>
                        <input type="text" id="modal_block_input" class="form-control form-control-sm" list="available-blocks-datalist" placeholder="e.g. Block A, Science Wing" />
                        <datalist id="available-blocks-datalist">
                            @foreach($availableBlocks ?? [] as $b)
                                <option value="{{ $b }}">
                            @endforeach
                        </datalist>
                        <small class="text-muted">{{ __('Assigning same block prioritizes nearby labs.') }}</small>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2">
                    <button type="button" class="btn btn-xs btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-xs btn-primary">{{ __('Save Block') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================
     MODAL: Rooms & Labs Management (with Block Support)
     ========================================================= -->
<div class="modal fade" id="resourcesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark">
                        <i class="fa fa-building text-primary mr-1"></i> {{ __('Rooms, Labs & Resource Allocation') }}
                    </h5>
                    <small class="text-muted">{{ __('Specialized rooms with Building/Block assignment to optimize travel proximity.') }}</small>
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
                                <th>{{ __('Block / Building') }}</th>
                                <th>{{ __('Capacity') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resources ?? [] as $res)
                                <tr>
                                    <td><strong>{{ $res->name }}</strong></td>
                                    <td><span class="badge badge-info">{{ $res->type }}</span></td>
                                    <td>
                                        @if($res->block)
                                            <span class="badge badge-light border text-dark font-weight-bold"><i class="fa fa-map-marker text-danger mr-1"></i>{{ $res->block }}</span>
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
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
                                    <td colspan="6" class="text-center text-muted py-3">
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
                            <div class="col-md-4 form-group">
                                <label class="small font-weight-bold">{{ __('Room / Lab Name') }} <span class="text-danger">*</span></label>
                                <input type="text" id="new_resource_name" class="form-control form-control-sm" placeholder="e.g. Physics Lab, Computer Room" required />
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="small font-weight-bold">{{ __('Type') }} <span class="text-danger">*</span></label>
                                <select id="new_resource_type" class="form-control form-control-sm" required>
                                    <option value="Lab">{{ __('Laboratory') }}</option>
                                    <option value="Room">{{ __('Special Classroom') }}</option>
                                    <option value="Auditorium">{{ __('Auditorium / Hall') }}</option>
                                    <option value="Ground">{{ __('Sports Ground / Gym') }}</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="small font-weight-bold">{{ __('Building / Block') }}</label>
                                <input type="text" id="new_resource_block" class="form-control form-control-sm" list="available-blocks-datalist" placeholder="e.g. Block A" />
                            </div>
                            <div class="col-md-2 form-group">
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

<!-- Bell Schedules Modal -->
<div class="modal fade" id="templatesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark">
                        <i class="fa fa-calendar-check-o text-primary mr-1"></i> {{ __('Bell Schedules & Period Templates') }}
                    </h5>
                    <small class="text-muted">{{ __('Templates define standard school hours, period timings, and break intervals.') }}</small>
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
                                <th>{{ __('Periods') }}</th>
                                <th>{{ __('Working Days') }}</th>
                                <th>{{ __('Default') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates ?? [] as $tmpl)
                                <tr>
                                    <td>
                                        <strong>{{ $tmpl->name }}</strong>
                                        @if($tmpl->description)<br><small class="text-muted">{{ $tmpl->description }}</small>@endif
                                    </td>
                                    <td><span class="badge badge-info">{{ $tmpl->periods->count() }} {{ __('slots') }}</span></td>
                                    <td><small>{{ implode(', ', array_map(fn($d) => substr($d, 0, 3), $tmpl->working_days ?? [])) }}</small></td>
                                    <td>
                                        @if($tmpl->is_default)
                                            <span class="badge badge-success">{{ __('Default') }}</span>
                                        @else
                                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="makeTemplateDefault({{ $tmpl->id }})">
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
                                <tr><td colspan="5" class="text-center text-muted py-3">{{ __('No templates created yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="card bg-light border p-3">
                    <h6 class="font-weight-bold text-dark mb-2">{{ __('Create New Schedule Template') }}</h6>
                    <form id="createTemplateForm" onsubmit="createTemplateSubmit(event)">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">{{ __('Template Name') }} <span class="text-danger">*</span></label>
                                <input type="text" id="new_template_name" class="form-control form-control-sm" placeholder="e.g. High School Bell Schedule" required />
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="small font-weight-bold">{{ __('Description') }}</label>
                                <input type="text" id="new_template_desc" class="form-control form-control-sm" placeholder="e.g. 8 periods daily with lunch after period 4" />
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

<!-- Modal to Select Class for Auto-Generation -->
<div class="modal fade" id="chooseClassForAutoGenerateModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold text-dark">
                    <i class="fa fa-magic text-primary mr-1"></i> {{ __('Auto-Generate Timetable') }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    {{ __('Select a class section below to open its Intelligent Auto-Generator wizard. The system will build a conflict-free schedule, balancing teacher workloads and lab proximity.') }}
                </p>
                <div class="form-group">
                    <label class="font-weight-bold">{{ __('Select Class Section') }} <span class="text-danger">*</span></label>
                    <select id="select_class_section_for_gen" class="form-control" style="height: 45px;">
                        <option value="">-- {{ __('Choose Class & Section') }} --</option>
                        @foreach($classSections as $cs)
                            <option value="{{ $cs->id }}">{{ $cs->full_name }}</option>
                        @endforeach
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

<!-- School-wide Batch Generate Modal -->
<div class="modal fade" id="batchGenerateModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content" style="border-radius: 14px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark">
                        <i class="fa fa-sitemap text-primary mr-1"></i> {{ __('School-Wide Timetable Generator') }}
                    </h5>
                    <small class="text-muted">{{ __('Generate several classes in one run preventing clashes and respecting building block proximity.') }}</small>
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
                                @foreach($templates ?? [] as $tmpl)
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

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="font-weight-bold small mb-0">{{ __('Select classes to generate') }}</label>
                        <div>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="batchSelect('all')">{{ __('Select all') }}</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="batchSelect('ready')">{{ __('Ready only') }}</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="batchSelect('none')">{{ __('Clear') }}</button>
                        </div>
                    </div>

                    <div class="border rounded p-2" style="max-height: 380px; overflow-y: auto;">
                        @foreach($classSections->groupBy('class_id') as $cId => $sections)
                            <div class="mb-2">
                                <div class="custom-control custom-checkbox font-weight-bold small bg-light p-1 pl-4 rounded">
                                    <input type="checkbox" class="custom-control-input batch-class-toggle" id="batch-cls-{{ $cId }}" data-class="{{ $cId }}">
                                    <label class="custom-control-label" for="batch-cls-{{ $cId }}">
                                        {{ $sections->first()->class?->full_name ?? ('Class #' . $cId) }}
                                    </label>
                                </div>
                                <div class="row pl-4 pt-1">
                                    @foreach($sections as $cs)
                                        @php $r = $readiness[$cs->id] ?? null; @endphp
                                        <div class="col-md-6 col-lg-4 mb-1">
                                            <div class="custom-control custom-checkbox small">
                                                <input type="checkbox" class="custom-control-input batch-section"
                                                       id="batch-sec-{{ $cs->id }}" value="{{ $cs->id }}"
                                                       data-class="{{ $cId }}"
                                                       data-ready="{{ ($r && $r['subjects'] > 0 && $r['no_teacher'] === 0) ? 1 : 0 }}">
                                                <label class="custom-control-label" for="batch-sec-{{ $cs->id }}">
                                                    {{ $cs->full_name }}
                                                    @if($cs->block)
                                                        <span class="badge badge-light border text-primary px-1 font-weight-bold">{{ $cs->block }}</span>
                                                    @endif
                                                    @if($r && $r['subjects'] > 0)
                                                        <span class="text-muted">({{ $r['periods'] }}p)</span>
                                                    @else
                                                        <span class="badge badge-warning px-1">{{ __('no reqs') }}</span>
                                                    @endif
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div id="batch-step-result" class="d-none">
                    <div id="batch-result-summary" class="alert alert-success p-2 small mb-2"></div>
                    <ul id="batch-result-conflicts" class="text-danger small mb-2 pl-3"></ul>
                    <div id="batch-preview-timetable-container"></div>
                </div>
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <div>
                    <span class="small text-muted">{{ __('Selected classes:') }} <strong id="batch-selected-count">0</strong></span>
                </div>
                <div style="gap: 6px;" class="d-flex">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('Close') }}</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="batch-back-btn" onclick="batchBack()">{{ __('Back') }}</button>
                    <button type="button" class="btn btn-danger btn-sm d-none" id="batch-discard-btn" onclick="batchDiscard()">{{ __('Discard') }}</button>
                    <button type="button" class="btn btn-success btn-sm d-none" id="batch-apply-btn" onclick="batchApply()">{{ __('Apply to Live Timetable') }}</button>
                    <button type="button" class="btn btn-gradient-primary btn-sm" id="batch-generate-btn" onclick="batchGenerate()">
                        <i class="fa fa-magic mr-1"></i> {{ __('Generate Preview') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    // State Management
    var currentView = 'single'; // 'single', 'cards', 'list'
    var currentClassSectionId = null;
    var currentMatrixData = null;
    var isEditMode = false;
    var draggedPayload = null;
    var cardsDataLoaded = false;
    var allClassSections = @json($classSections);

    $(document).ready(function () {
        var firstClassId = $('#main_class_selector').val();
        if (firstClassId) {
            currentClassSectionId = firstClassId;
            loadSingleClassMatrix(firstClassId);
        }

        // Check if previously selected view in localStorage
        var savedView = localStorage.getItem('zolo_timetable_view');
        if (savedView && ['single', 'cards', 'list'].indexOf(savedView) !== -1) {
            switchMainView(savedView);
        }
    });

    // =========================================================
    // View Switcher Logic
    // =========================================================
    function switchMainView(view) {
        currentView = view;
        localStorage.setItem('zolo_timetable_view', view);

        $('.view-mode-switcher .btn').removeClass('active');
        $('#btn-view-' + view).addClass('active');

        $('#view-container-single').toggleClass('d-none', view !== 'single');
        $('#view-container-cards').toggleClass('d-none', view !== 'cards');
        $('#view-container-list').toggleClass('d-none', view !== 'list');

        $('#single-class-nav-group').toggleClass('d-none', view !== 'single');
        $('#single-class-meta-badges').toggleClass('d-none', view !== 'single');
        $('#single-class-actions').toggleClass('d-none', view !== 'single');
        $('#list-view-filter-group').toggleClass('d-none', view !== 'list');

        if (view === 'cards' && !cardsDataLoaded) {
            loadCardViewData();
        } else if (view === 'list') {
            $('#table_list').bootstrapTable('refresh');
        }
    }

    // =========================================================
    // Single Class Navigation (< and > buttons)
    // =========================================================
    function navigateClassSection(delta) {
        var select = document.getElementById('main_class_selector');
        var curIdx = select.selectedIndex;
        var nextIdx = curIdx + delta;
        if (nextIdx >= 0 && nextIdx < select.options.length) {
            select.selectedIndex = nextIdx;
            onClassSectionChange(select.value);
        }
    }

    function onClassSectionChange(classSectionId) {
        currentClassSectionId = classSectionId;
        loadSingleClassMatrix(classSectionId);
        if (currentView === 'list') {
            $('#table_list').bootstrapTable('refresh');
        }
    }

    // Filter by Medium
    $('#filter_medium_id').on('change', function () {
        var medId = $(this).val();
        var select = $('#main_class_selector');
        select.find('option').each(function () {
            var m = $(this).data('medium');
            if (!medId || m == medId) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
        var visibleFirst = select.find('option:visible').first().val();
        if (visibleFirst && currentView === 'single') {
            select.val(visibleFirst);
            onClassSectionChange(visibleFirst);
        }

        // Also filter List View Class dropdown options
        var listSelect = $('#list_filter_class_section_id');
        listSelect.find('option').each(function () {
            var val = $(this).val();
            if (!val) return; // Keep "All Classes" visible
            var m = $(this).data('medium');
            if (!medId || m == medId) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        if (currentView === 'list') {
            $('#table_list').bootstrapTable('refresh');
        }
    });

    // =========================================================
    // View 1: Single Class View & Drag-and-Drop Matrix
    // =========================================================
    function toggleEditMode(enabled) {
        isEditMode = enabled;
        $('#subject-palette-container').toggleClass('d-none', !isEditMode);
        renderSingleMatrixTable(currentMatrixData);
    }

    function loadSingleClassMatrix(classSectionId) {
        if (!classSectionId) return;
        $('#single-matrix-loading').removeClass('d-none');
        $('#single-matrix-wrapper').addClass('d-none');

        $.ajax({
            url: "{{ url('timetable/get-matrix') }}/" + classSectionId,
            type: 'GET',
            success: function (res) {
                currentMatrixData = res;
                $('#single-matrix-loading').addClass('d-none');
                $('#single-matrix-wrapper').removeClass('d-none');

                // Update class section metadata badges
                if (res.class_section) {
                    var blockText = res.class_section.block ? 'Block: ' + res.class_section.block : 'Block: Unassigned';
                    $('#label-class-block').text(blockText);
                    $('#label-class-teacher').text(res.class_section.class_teacher_name || 'No Class Teacher');
                    $('#palette-class-title').text(res.class_section.full_name);
                }

                renderSubjectPalette(res.subject_palette, res.class_section);
                renderSingleMatrixTable(res);
            },
            error: function () {
                $('#single-matrix-loading').html('<div class="alert alert-danger py-2 mb-0">Failed to load timetable matrix.</div>');
            }
        });
    }

    function renderSubjectPalette(palette, classSection) {
        var container = $('#subject-palette-list').empty();
        if (!palette || !palette.length) {
            container.html('<span class="text-muted small">No subject requirements assigned to this class yet. Use Auto-Generate or Course Setup.</span>');
            return;
        }

        palette.forEach(function (sub) {
            var isCompleted = sub.remaining_periods === 0;
            var chip = $('<div class="palette-chip" draggable="true"></div>');
            chip.data('subject', sub);

            var labHtml = '';
            if (sub.preferred_resource_name) {
                var sameBlock = (classSection && classSection.block && sub.preferred_resource_block === classSection.block);
                labHtml = '<span class="badge ' + (sameBlock ? 'badge-success' : 'badge-warning') + ' ml-1" style="font-size: 9px;" title="Lab: ' + sub.preferred_resource_name + ' (' + (sub.preferred_resource_block || 'No Block') + ')">' +
                    '<i class="fa fa-building-o mr-1"></i>' + sub.preferred_resource_name + '</span>';
            }

            chip.html(
                '<span class="palette-color-dot" style="background:' + (sub.bg_color || '#4F46E5') + '"></span>' +
                '<span class="palette-subject-name">' + sub.subject_name + '</span>' +
                (sub.teacher_name ? '<span class="text-muted small" style="font-size: 11px;"><i class="fa fa-user-circle mr-1"></i>' + sub.teacher_name.split(' ')[0] + '</span>' : '') +
                labHtml +
                '<span class="palette-badge ' + (isCompleted ? 'bg-success text-white' : '') + '">' +
                sub.assigned_periods + '/' + sub.required_weekly + 'p' +
                '</span>'
            );

            chip[0].addEventListener('dragstart', function (e) {
                draggedPayload = {
                    type: 'new_subject',
                    subject_id: sub.subject_id,
                    teacher_id: sub.teacher_id,
                    resource_id: sub.preferred_resource_id,
                    subject_name: sub.subject_name,
                    bg_color: sub.bg_color,
                    teacher_name: sub.teacher_name
                };
                e.dataTransfer.setData('text/plain', JSON.stringify(draggedPayload));
                $(this).addClass('dragging');
            });

            chip[0].addEventListener('dragend', function () {
                $(this).removeClass('dragging');
                draggedPayload = null;
            });

            container.append(chip);
        });
    }

    function renderSingleMatrixTable(res) {
        if (!res) return;
        var thead = $('#single-matrix-thead').empty();
        var tbody = $('#single-matrix-tbody').empty();

        var days = res.days || ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        var timeSlots = res.time_slots || [];
        var matrix = res.matrix || {};
        var classSection = res.class_section || {};

        // Build Table Header (Days along the columns)
        var headTr = $('<tr></tr>');
        headTr.append('<th style="width: 95px; max-width: 95px;" class="text-center">{{ __("Time / Period") }}</th>');
        days.forEach(function (day) {
            headTr.append('<th class="day-header-col" style="width: calc((100% - 95px) / ' + days.length + ');">' + day + '</th>');
        });
        thead.append(headTr);

        if (!timeSlots.length) {
            tbody.html('<tr><td colspan="' + (days.length + 1) + '" class="text-center py-5 text-muted">No timetable periods configured. Please add time slots in Bell Schedules.</td></tr>');
            return;
        }

        // Build Table Rows (Periods along rows)
        timeSlots.forEach(function (slot) {
            var rowTr = $('<tr></tr>');
            var isBreakPeriod = (slot.type === 'Break' || (slot.title && slot.title.toLowerCase().indexOf('break') !== -1));
            if (isBreakPeriod) {
                rowTr.addClass('break-period-row');
            }

            // Clean Period Number formatting (e.g. Period 1, Break, etc.)
            var periodLabel = slot.type === 'Break' ? 'Break' : (slot.title || ('Period ' + (slot.period_no || '')));
            if (periodLabel.indexOf(' - ') !== -1 || periodLabel.indexOf('(') !== -1) {
                periodLabel = slot.type === 'Break' ? 'Break' : ('Period ' + (slot.period_no || ''));
            }

            // Time Slot Column
            var timeHtml = '<td class="time-col-cell' + (isBreakPeriod ? ' break-time-cell' : '') + '">';
            timeHtml += '<div class="period-number-badge">' + (isBreakPeriod ? '<i class="fa fa-coffee mr-1 text-warning"></i>' : '') + periodLabel + '</div>';
            timeHtml += '<div class="period-time-range">' + slot.start_time.substring(0, 5) + ' - ' + slot.end_time.substring(0, 5) + '</div>';
            timeHtml += '</td>';
            rowTr.append(timeHtml);

            // Day Columns
            days.forEach(function (day) {
                var cellTd = $('<td class="cell-drop-target"></td>');
                cellTd.data('day', day);
                cellTd.data('start_time', slot.start_time);
                cellTd.data('end_time', slot.end_time);
                cellTd.data('period_id', slot.period_id);

                var slotEntry = matrix[day] ? matrix[day][slot.start_time] : null;

                if (isBreakPeriod && (!slotEntry || slotEntry.type === 'Break')) {
                    cellTd.html('<div class="break-slot-cell"><i class="fa fa-coffee mr-1"></i><span>' + (slot.title || 'Break') + '</span></div>');
                } else if (slotEntry && slotEntry.type === 'Lecture') {
                    var card = $('<div class="modern-slot-card ' + (isEditMode ? 'is-draggable' : '') + '"></div>');
                    if (isEditMode) {
                        card.attr('draggable', 'true');
                    }

                    var isSameBlock = (classSection.block && slotEntry.resource_block && classSection.block === slotEntry.resource_block);
                    var proximityHtml = '';
                    if (slotEntry.resource_name) {
                        if (isSameBlock) {
                            proximityHtml = '<span class="proximity-badge same-block" title="Located in the same building (' + slotEntry.resource_block + ')"><i class="fa fa-building-o"></i> ' + slotEntry.resource_name + '</span>';
                        } else if (slotEntry.resource_block) {
                            proximityHtml = '<span class="proximity-badge diff-block" title="Different block (' + slotEntry.resource_block + ') - consider walking distance"><i class="fa fa-exclamation-triangle"></i> ' + slotEntry.resource_name + ' [' + slotEntry.resource_block + ']</span>';
                        } else {
                            proximityHtml = '<span class="proximity-badge" style="background:#F1F5F9; color:#475569;"><i class="fa fa-building-o"></i> ' + slotEntry.resource_name + '</span>';
                        }
                    }

                    var conflictHtml = slotEntry.conflict ? '<div class="badge badge-danger text-truncate mt-1" style="font-size: 9px; width: 100%;" title="' + slotEntry.conflict + '"><i class="fa fa-warning mr-1"></i>' + slotEntry.conflict + '</div>' : '';

                    var toolsHtml = isEditMode ? (
                        '<div class="slot-edit-tools">' +
                            '<button type="button" class="slot-tool-btn btn-delete" title="Remove Lesson" onclick="clearSlotDirect(' + slotEntry.id + ')"><i class="fa fa-times"></i></button>' +
                        '</div>'
                    ) : '';

                    card.html(
                        toolsHtml +
                        '<div class="slot-top-bar">' +
                            '<span class="slot-subject-pill" style="background:' + (slotEntry.bg_color || '#2563EB') + '">' +
                                slotEntry.subject_name +
                            '</span>' +
                            (slotEntry.is_locked ? '<i class="fa fa-lock text-warning" title="Locked slot"></i>' : '') +
                        '</div>' +
                        '<div class="slot-teacher-row">' +
                            '<i class="fa fa-user-circle text-primary"></i>' +
                            '<span>' + (slotEntry.primary_teacher_name || 'No Teacher') + '</span>' +
                        '</div>' +
                        '<div class="slot-bottom-meta">' +
                            proximityHtml +
                        '</div>' +
                        conflictHtml
                    );

                    if (isEditMode) {
                        card[0].addEventListener('dragstart', function (e) {
                            draggedPayload = {
                                type: 'move_slot',
                                id: slotEntry.id,
                                day: day,
                                start_time: slot.start_time,
                                end_time: slot.end_time,
                                period_id: slot.period_id,
                                subject_name: slotEntry.subject_name
                            };
                            e.dataTransfer.setData('text/plain', JSON.stringify(draggedPayload));
                            $(this).addClass('dragging');
                        });
                        card[0].addEventListener('dragend', function () {
                            $(this).removeClass('dragging');
                            draggedPayload = null;
                        });
                    }

                    cellTd.append(card);
                } else {
                    // Empty Cell
                    if (isEditMode) {
                        cellTd.html('<div class="empty-slot-zone"><i class="fa fa-plus-circle mb-1"></i><span>{{ __("Drop subject here") }}</span></div>');
                    } else {
                        cellTd.html('<div class="empty-slot-zone" style="border-style: solid; border-color: #F1F5F9; background: #FAFAFA;"><span class="text-muted" style="opacity: 0.4;">-</span></div>');
                    }
                }

                // Drag & Drop event bindings on cell
                if (isEditMode && !isBreakPeriod) {
                    cellTd[0].addEventListener('dragover', function (e) {
                        e.preventDefault();
                        $(this).addClass('drag-over');
                    });
                    cellTd[0].addEventListener('dragleave', function () {
                        $(this).removeClass('drag-over');
                    });
                    cellTd[0].addEventListener('drop', function (e) {
                        e.preventDefault();
                        $(this).removeClass('drag-over');
                        handleCellDrop(day, slot.start_time, slot.end_time, slot.period_id, slotEntry);
                    });
                }

                rowTr.append(cellTd);
            });

            tbody.append(rowTr);
        });
    }

    var pendingConflictAction = null;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function executeSlotPlacement(payload, force) {
        var postData = $.extend({}, payload, { force: force ? 1 : 0 });
        $.ajax({
            url: "{{ route('timetable.place-slot') }}",
            type: 'POST',
            data: postData,
            success: function (res) {
                $('#teacherConflictModal').modal('hide');
                showSuccessToast(res.message || "{{ __('Subject placed successfully.') }}");
                loadSingleClassMatrix(currentClassSectionId);
            },
            error: function (xhr) {
                if (xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.has_teacher_conflict) {
                    pendingConflictAction = {
                        type: 'place_slot',
                        payload: payload,
                        conflictData: xhr.responseJSON
                    };
                    showTeacherConflictModal(pendingConflictAction);
                } else {
                    var msg = xhr.responseJSON ? xhr.responseJSON.message : "Failed to place slot.";
                    showErrorToast(msg);
                }
            }
        });
    }

    function executeSlotMove(payload, force) {
        var postData = $.extend({}, payload, { force: force ? 1 : 0 });
        $.ajax({
            url: "{{ route('timetable.move') }}",
            type: 'POST',
            data: postData,
            success: function (res) {
                $('#teacherConflictModal').modal('hide');
                showSuccessToast(res.message || "{{ __('Slot updated.') }}");
                loadSingleClassMatrix(currentClassSectionId);
            },
            error: function (xhr) {
                if (xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.has_teacher_conflict) {
                    pendingConflictAction = {
                        type: 'move_slot',
                        payload: payload,
                        conflictData: xhr.responseJSON
                    };
                    showTeacherConflictModal(pendingConflictAction);
                } else {
                    var msg = xhr.responseJSON ? xhr.responseJSON.message : "Move failed.";
                    showErrorToast(msg);
                }
            }
        });
    }

    // Handle Drop onto Timetable Cell
    function handleCellDrop(targetDay, targetStart, targetEnd, targetPeriodId, existingSlot) {
        if (!draggedPayload) return;

        if (draggedPayload.type === 'new_subject') {
            executeSlotPlacement({
                _token: "{{ csrf_token() }}",
                class_section_id: currentClassSectionId,
                day: targetDay,
                start_time: targetStart,
                end_time: targetEnd,
                period_id: targetPeriodId,
                subject_id: draggedPayload.subject_id,
                teacher_id: draggedPayload.teacher_id,
                resource_id: draggedPayload.resource_id,
                type: 'Lecture'
            }, 0);
        } else if (draggedPayload.type === 'move_slot') {
            executeSlotMove({
                _token: "{{ csrf_token() }}",
                id: draggedPayload.id,
                day: targetDay,
                start_time: targetStart,
                end_time: targetEnd,
                period_id: targetPeriodId
            }, 0);
        }
    }

    function showTeacherConflictModal(action) {
        var data = action.conflictData || {};
        var details = data.conflict_details || {};
        var options = data.options || {};

        var teacherName = details.teacher_name || "{{ __('The assigned teacher') }}";
        var clashingClass = details.clashing_class || "{{ __('another class') }}";
        var clashingSubject = details.clashing_subject ? (' (' + details.clashing_subject + ')') : '';
        var day = details.day || '';
        var timeStr = (details.start_time ? details.start_time.substring(0, 5) : '') + ' - ' + (details.end_time ? details.end_time.substring(0, 5) : '');

        var bannerHtml = '<div class="d-flex align-items-start">' +
            '<i class="fa fa-user-times fa-2x text-danger mr-3 mt-1"></i>' +
            '<div>' +
                '<h6 class="font-weight-bold text-danger mb-1">' +
                    escapeHtml(teacherName) + ' {{ __("is already scheduled at this time!") }}' +
                '</h6>' +
                '<div class="text-dark small">' +
                    '{{ __("Currently teaching") }} <strong>' + escapeHtml(clashingSubject.replace(/[()]/g, '')) + '</strong> {{ __("in") }} <strong>' + escapeHtml(clashingClass) + '</strong> ' +
                    '{{ __("on") }} <strong>' + escapeHtml(day) + ' ' + escapeHtml(timeStr) + '</strong>.' +
                '</div>' +
            '</div>' +
        '</div>';
        $('#conflict-modal-banner').html(bannerHtml);

        // Option 1: Alternate Free Slots
        var freeSlots = options.alternative_slots || [];
        $('#conflict-free-slots-count').text(freeSlots.length + ' {{ __("available") }}');
        var slotsList = $('#conflict-alternative-slots-list').empty();
        if (freeSlots.length > 0) {
            freeSlots.forEach(function (slot) {
                var btn = $('<button type="button" class="btn btn-sm btn-outline-success font-weight-bold text-left">' +
                    '<i class="fa fa-calendar-check-o mr-1"></i> ' +
                    slot.day + ' ' + slot.start_time.substring(0, 5) + ' - ' + slot.end_time.substring(0, 5) +
                    '<span class="badge badge-success ml-2">' + escapeHtml(slot.period_title || 'Free') + '</span>' +
                '</button>');
                btn.on('click', function () {
                    resolveConflictWithAlternateSlot(slot);
                });
                slotsList.append(btn);
            });
        } else {
            slotsList.html('<span class="text-muted small"><i class="fa fa-info-circle mr-1"></i>{{ __("No free overlapping timeslots found where both teacher and class are available.") }}</span>');
        }

        // Option 2: Alternate Available Teachers
        var altTeachers = options.alternative_teachers || [];
        $('#conflict-alt-teachers-count').text(altTeachers.length + ' {{ __("available") }}');
        var teachersList = $('#conflict-alternative-teachers-list').empty();
        if (altTeachers.length > 0) {
            altTeachers.forEach(function (t) {
                var btn = $('<button type="button" class="btn btn-sm btn-outline-primary font-weight-bold">' +
                    '<i class="fa fa-user-plus mr-1"></i> ' + escapeHtml(t.name) +
                '</button>');
                btn.on('click', function () {
                    resolveConflictWithAlternateTeacher(t);
                });
                teachersList.append(btn);
            });
        } else {
            teachersList.html('<span class="text-muted small"><i class="fa fa-info-circle mr-1"></i>{{ __("No other qualified teachers are free at this hour for this subject.") }}</span>');
        }

        $('#teacherConflictModal').modal('show');
    }

    function resolveConflictWithAlternateSlot(newSlot) {
        if (!pendingConflictAction) return;
        var p = pendingConflictAction.payload;
        p.day = newSlot.day;
        p.start_time = newSlot.start_time;
        p.end_time = newSlot.end_time;
        p.period_id = newSlot.period_id || null;

        if (pendingConflictAction.type === 'place_slot') {
            executeSlotPlacement(p, 0);
        } else {
            executeSlotMove(p, 0);
        }
    }

    function resolveConflictWithAlternateTeacher(teacher) {
        if (!pendingConflictAction) return;
        var p = pendingConflictAction.payload;
        p.teacher_id = teacher.id;

        if (pendingConflictAction.type === 'place_slot') {
            executeSlotPlacement(p, 0);
        } else {
            executeSlotMove(p, 0);
        }
    }

    function executePendingConflictOverride() {
        if (!pendingConflictAction) return;
        if (pendingConflictAction.type === 'place_slot') {
            executeSlotPlacement(pendingConflictAction.payload, 1);
        } else {
            executeSlotMove(pendingConflictAction.payload, 1);
        }
    }

    function clearSlotDirect(slotId) {
        if (!confirm("Are you sure you want to remove this lesson slot?")) return;
        $.ajax({
            url: "{{ route('timetable.clear-slot') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                id: slotId
            },
            success: function (res) {
                showSuccessToast(res.message || "{{ __('Slot cleared.') }}");
                loadSingleClassMatrix(currentClassSectionId);
            },
            error: function (xhr) {
                showErrorToast(xhr.responseJSON ? xhr.responseJSON.message : "Failed to clear slot.");
            }
        });
    }

    function printSingleClassTimetable() {
        window.print();
    }

    // =========================================================
    // View 2: Card View Preview (3 Columns)
    // =========================================================
    function loadCardViewData() {
        $('#cards-loading').removeClass('d-none');
        $('#cards-grid-container').empty();

        $.ajax({
            url: "{{ route('timetable.card-data') }}",
            type: 'GET',
            success: function (res) {
                cardsDataLoaded = true;
                $('#cards-loading').addClass('d-none');
                renderCardGrid(res.data || []);
            },
            error: function () {
                $('#cards-loading').html('<div class="alert alert-danger py-2">Failed to load class cards.</div>');
            }
        });
    }

    function renderCardGrid(cards) {
        var container = $('#cards-grid-container').empty();
        if (!cards.length) {
            container.html('<div class="col-12 text-center py-5 text-muted">{{ __("No classes found.") }}</div>');
            return;
        }

        cards.forEach(function (c) {
            var col = $('<div class="col"></div>');
            var card = $('<div class="class-card-preview" onclick="openClassTimetablePopup(' + c.id + ')"></div>');

            var statusBadge = '';
            var progressColor = '#2563EB';
            if (c.status === 'Complete') {
                statusBadge = '<span class="badge badge-success px-2 py-1"><i class="fa fa-check mr-1"></i>{{ __("Complete") }}</span>';
                progressColor = '#16A34A';
            } else if (c.status === 'Incomplete') {
                statusBadge = '<span class="badge badge-warning px-2 py-1"><i class="fa fa-clock-o mr-1"></i>{{ __("In Progress") }}</span>';
                progressColor = '#D97706';
            } else {
                statusBadge = '<span class="badge badge-secondary px-2 py-1">{{ __("Empty") }}</span>';
                progressColor = '#94A3B8';
            }

            var blockBadge = c.block ? '<span class="badge badge-light border text-primary font-weight-bold ml-1"><i class="fa fa-map-marker text-danger mr-1"></i>' + c.block + '</span>' : '';

            var subjectsHtml = '';
            if (c.top_subjects && c.top_subjects.length) {
                c.top_subjects.forEach(function (sub) {
                    subjectsHtml += '<span class="subject-mini-chip" style="border-left: 2px solid ' + (sub.bg_color || '#2563EB') + ';">' + sub.name + ' (' + sub.count + 'p)</span>';
                });
            }

            card.html(
                '<div>' +
                    '<div class="class-card-header">' +
                        '<div>' +
                            '<h5 class="class-card-title">' + c.full_name + blockBadge + '</h5>' +
                            '<div class="class-card-meta">' +
                                (c.medium ? '<span class="mr-2"><i class="fa fa-language mr-1"></i>' + c.medium + '</span>' : '') +
                                '<span><i class="fa fa-user-circle mr-1"></i>' + (c.class_teacher_name || 'No Teacher') + '</span>' +
                            '</div>' +
                        '</div>' +
                        statusBadge +
                    '</div>' +

                    '<div class="class-progress-bar-wrap">' +
                        '<div class="d-flex justify-content-between align-items-center small mb-1">' +
                            '<span class="font-weight-bold text-dark">' + c.total_scheduled + ' / ' + c.total_required + ' {{ __("Periods Filled") }}</span>' +
                            '<span class="text-muted font-weight-bold">' + c.percentage + '%</span>' +
                        '</div>' +
                        '<div class="class-progress-track">' +
                            '<div class="class-progress-fill" style="width: ' + c.percentage + '%; background: ' + progressColor + ';"></div>' +
                        '</div>' +
                    '</div>' +

                    '<div class="subject-pill-cluster">' +
                        subjectsHtml +
                    '</div>' +
                '</div>' +

                '<div class="d-flex justify-content-between align-items-center pt-2 border-top">' +
                    '<button type="button" class="btn btn-xs btn-outline-primary font-weight-bold" onclick="event.stopPropagation(); openClassTimetablePopup(' + c.id + ');">' +
                        '<i class="fa fa-eye mr-1"></i> {{ __("View Timetable") }}' +
                    '</button>' +
                    '<a href="{{ url("timetable") }}/' + c.id + '/edit?autogen=1" class="btn btn-xs btn-outline-info" onclick="event.stopPropagation();">' +
                        '<i class="fa fa-magic mr-1"></i> {{ __("Auto-Gen") }}' +
                    '</a>' +
                '</div>'
            );

            col.append(card);
            container.append(col);
        });
    }

    var popupCurrentClassId = null;

    function openClassTimetablePopup(classSectionId) {
        popupCurrentClassId = classSectionId;
        $('#classTimetablePopupModal').modal('show');
        $('#popup-timetable-container').html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i></div>');

        $.ajax({
            url: "{{ url('timetable/get-matrix') }}/" + classSectionId,
            type: 'GET',
            success: function (res) {
                if (res.class_section) {
                    $('#popup-modal-class-title').html('<i class="fa fa-calendar text-primary mr-1"></i> ' + res.class_section.full_name);
                    $('#popup-modal-class-subtitle').text('Teacher: ' + (res.class_section.class_teacher_name || 'None') + ' | ' + (res.class_section.block ? 'Block ' + res.class_section.block : 'No Block Assigned'));
                }

                // Render standalone grid inside popup modal
                var days = res.days || ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                var timeSlots = res.time_slots || [];
                var matrix = res.matrix || {};

                var html = '<div class="table-responsive"><table class="single-matrix-table">';
                html += '<thead><tr><th style="width:95px; max-width:95px;">Time</th>';
                days.forEach(function (d) { html += '<th style="width: calc((100% - 95px) / ' + days.length + ');">' + d + '</th>'; });
                html += '</tr></thead><tbody>';

                timeSlots.forEach(function (slot) {
                    var isBreak = (slot.type === 'Break' || (slot.title && slot.title.toLowerCase().indexOf('break') !== -1));
                    html += '<tr class="' + (isBreak ? 'break-period-row' : '') + '">';
                    var periodLabel = slot.type === 'Break' ? 'Break' : (slot.title || ('Period ' + (slot.period_no || '')));
                    if (periodLabel.indexOf(' - ') !== -1 || periodLabel.indexOf('(') !== -1) {
                        periodLabel = slot.type === 'Break' ? 'Break' : ('Period ' + (slot.period_no || ''));
                    }
                    html += '<td class="time-col-cell' + (isBreak ? ' break-time-cell' : '') + '"><div class="period-number-badge">' + (isBreak ? '<i class="fa fa-coffee mr-1 text-warning"></i>' : '') + periodLabel + '</div><div class="period-time-range">' + slot.start_time.substring(0, 5) + ' - ' + slot.end_time.substring(0, 5) + '</div></td>';

                    days.forEach(function (day) {
                        var slotEntry = matrix[day] ? matrix[day][slot.start_time] : null;
                        if (isBreak && (!slotEntry || slotEntry.type === 'Break')) {
                            html += '<td><div class="break-slot-cell"><i class="fa fa-coffee mr-1"></i><span>' + (slot.title || 'Break') + '</span></div></td>';
                        } else if (slotEntry && slotEntry.type === 'Lecture') {
                            html += '<td><div class="modern-slot-card">' +
                                '<div class="slot-top-bar"><span class="slot-subject-pill" style="background:' + (slotEntry.bg_color || '#2563EB') + '">' + slotEntry.subject_name + '</span></div>' +
                                '<div class="slot-teacher-row"><i class="fa fa-user-circle text-primary"></i> ' + (slotEntry.primary_teacher_name || 'No Teacher') + '</div>' +
                                (slotEntry.resource_name ? '<div class="slot-bottom-meta"><span class="proximity-badge same-block"><i class="fa fa-building-o"></i> ' + slotEntry.resource_name + '</span></div>' : '') +
                            '</div></td>';
                        } else {
                            html += '<td><div class="empty-slot-zone"><span class="text-muted small">-</span></div></td>';
                        }
                    });

                    html += '</tr>';
                });

                html += '</tbody></table></div>';
                $('#popup-timetable-container').html(html);
            },
            error: function () {
                $('#popup-timetable-container').html('<div class="alert alert-danger py-2">Failed to load preview.</div>');
            }
        });
    }

    function printPopupTimetable() {
        var printContents = document.getElementById('popup-timetable-container').innerHTML;
        var printTitle = $('#popup-modal-class-title').text() || 'Class Timetable';
        var printSub = $('#popup-modal-class-subtitle').text() || '';

        var iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);

        var doc = iframe.contentWindow.document;
        doc.open();
        doc.write('<!DOCTYPE html>');
        doc.write('<html><head><title>' + printTitle + '<\/title>');
        doc.write('<link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap4.6/bootstrap.min.css') }}">');
        doc.write('<style>body{font-family:Inter,sans-serif;padding:20px;}.single-matrix-table{width:100%;border-collapse:separate;border-spacing:4px;}.single-matrix-table th,.single-matrix-table td{border:1px solid #ccc;padding:6px;text-align:center;}.modern-slot-card{border:1px solid #ddd;padding:6px;border-radius:6px;}.slot-subject-pill{font-weight:bold;color:#fff;padding:2px 6px;border-radius:4px;}<\/style>');
        doc.write('<\/head><body>');
        doc.write('<h4>' + printTitle + '<\/h4>');
        doc.write('<p>' + printSub + '<\/p>');
        doc.write(printContents);
        doc.write('<\/body><\/html>');
        doc.close();

        setTimeout(function () {
            iframe.contentWindow.focus();
            iframe.contentWindow.print();
            setTimeout(function () {
                document.body.removeChild(iframe);
            }, 1000);
        }, 300);
    }

    function openPopupClassInSingleView() {
        if (!popupCurrentClassId) return;
        $('#classTimetablePopupModal').modal('hide');
        $('#main_class_selector').val(popupCurrentClassId);
        onClassSectionChange(popupCurrentClassId);
        switchMainView('single');
        toggleEditMode(true);
        $('#interactive_edit_mode_toggle').prop('checked', true);
    }

    // =========================================================
    // Block Management & Setting Toggles
    // =========================================================
    function openClassBlockModal() {
        if (!currentClassSectionId) return;
        var sel = document.getElementById('main_class_selector');
        var opt = sel.options[sel.selectedIndex];
        var curBlock = opt.getAttribute('data-block') || '';

        $('#modal_block_class_id').val(currentClassSectionId);
        $('#modal_block_class_name').val(opt.text);
        $('#modal_block_input').val(curBlock);
        $('#setClassBlockModal').modal('show');
    }

    function saveClassBlockSubmit(e) {
        e.preventDefault();
        var cId = $('#modal_block_class_id').val();
        var block = $('#modal_block_input').val();

        $.ajax({
            url: "{{ route('timetable.class-block') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                class_section_id: cId,
                block: block
            },
            success: function (res) {
                $('#setClassBlockModal').modal('hide');
                showSuccessToast(res.message || "{{ __('Class block updated.') }}");

                // Update data-block attribute on selector option
                var opt = $('#main_class_selector').find('option[value="' + cId + '"]');
                opt.attr('data-block', res.block || '');

                loadSingleClassMatrix(cId);
            },
            error: function (xhr) {
                showErrorToast(xhr.responseJSON ? xhr.responseJSON.message : "Failed to update block.");
            }
        });
    }

    function togglePrioritizeSameBlock(checked) {
        $.ajax({
            url: "{{ route('timetable.block-settings') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                prioritize_same_block: checked ? 1 : 0
            },
            success: function (res) {
                showSuccessToast(res.message || "{{ __('Preference saved.') }}");
            },
            error: function () {
                showErrorToast("Failed to save setting.");
            }
        });
    }

    // =========================================================
    // Resources & Templates Management Modal Functions
    // =========================================================
    function createResourceSubmit(e) {
        e.preventDefault();
        var name = $('#new_resource_name').val();
        var type = $('#new_resource_type').val();
        var block = $('#new_resource_block').val();
        var cap = $('#new_resource_capacity').val();

        $.ajax({
            url: "{{ route('timetable.resources.store') }}",
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                name: name,
                type: type,
                block: block,
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

    function launchAutoGeneratorForSelected() {
        var id = $('#select_class_section_for_gen').val();
        if (!id) {
            alert("{{ __('Please select a class section.') }}");
            return;
        }
        window.location.href = "{{ url('timetable') }}/" + id + "/edit?autogen=1";
    }

    // =========================================================
    // School-Wide Batch Generation Functions
    // =========================================================
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
                $('#batch-result-summary').removeClass('alert-danger').addClass('alert-success')
                    .html('<i class="fa fa-check-circle mr-1"></i> ' + (res.message || '{{ __("Timetable generated successfully.") }}'));
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
                if (currentClassSectionId) {
                    loadSingleClassMatrix(currentClassSectionId);
                }
                cardsDataLoaded = false;
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

    // Bootstrap table query parameters for List View
    function timetableQueryParams(p) {
        var classSecId = '';
        if (currentView === 'list') {
            classSecId = $('#list_filter_class_section_id').val() || '';
        } else if (currentView === 'single') {
            classSecId = $('#main_class_selector').val() || '';
        }
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            search: p.search,
            medium_id: $('#filter_medium_id').val(),
            class_section_id: classSecId,
        };
    }
</script>
@endsection
