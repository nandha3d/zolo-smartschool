@extends('layouts.master')

@section('title')
    {{ __('Generate Student ID Card') }}
@endsection

@section('css')
<style>
    :root {
        --card-primary: {{ $schoolSettings['primary_color'] ?? $schoolSettings['header_color'] ?? '#1E293B' }};
        --card-accent: {{ $schoolSettings['accent_color'] ?? $schoolSettings['footer_color'] ?? '#06B6D4' }};
        --card-bg: #FFFFFF;
    }

    /* =========================================================
       1. Active Blueprint Summary Banner
       ========================================================= */
    .blueprint-banner-card {
        border-radius: 14px;
        background: #F8FAFC;
        border: 1px solid #E2E8F0 !important;
        padding: 16px 20px;
        margin-bottom: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .blueprint-icon-box {
        width: 46px;
        height: 46px;
        min-width: 46px;
        border-radius: 10px;
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 14px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .color-swatch-badge {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: inline-block;
        border: 2px solid #FFFFFF;
        box-shadow: 0 1px 3px rgba(0,0,0,0.22);
        vertical-align: middle;
        margin: 0 4px;
    }

    /* =========================================================
       2. Table View & Cells Sleek Polish
       ========================================================= */
    .table-container-card {
        border-radius: 14px;
        border: 1px solid #E2E8F0 !important;
        box-shadow: 0 1px 4px rgba(0,0,0,0.03);
    }

    #table_list {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }
    #table_list thead th {
        background: #F8FAFC !important;
        color: #475569 !important;
        font-size: 11.5px !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        padding: 14px 16px !important;
        border-top: none !important;
        border-bottom: 2px solid #E2E8F0 !important;
        vertical-align: middle !important;
        white-space: nowrap;
    }
    #table_list tbody td {
        padding: 13px 16px !important;
        vertical-align: middle !important;
        border-top: 1px solid #F1F5F9 !important;
        border-bottom: 1px solid #F1F5F9 !important;
        color: #1E293B;
        font-size: 13.5px;
    }
    #table_list tbody tr:hover td {
        background-color: #F8FAFC !important;
    }
    #table_list .bs-checkbox {
        width: 44px;
        text-align: center;
        vertical-align: middle !important;
    }
    #table_list .bs-checkbox input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #2563EB;
        margin: 0 auto;
        display: block;
    }

    /* Photo Status & Action Cell */
    .student-photo-cell {
        display: flex;
        align-items: center;
        min-width: 185px;
    }
    .student-photo-thumb-wrap {
        position: relative;
        margin-right: 12px;
        flex-shrink: 0;
    }
    .student-photo-thumb {
        width: 44px;
        height: 52px;
        object-fit: cover;
        border-radius: 8px;
        border: 1.5px solid #E2E8F0;
        background: #F8FAFC;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        display: block;
    }
    .student-photo-indicator {
        position: absolute;
        top: -3px;
        right: -3px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #FFFFFF;
        box-shadow: 0 1px 2px rgba(0,0,0,0.2);
    }
    .student-photo-indicator.ready {
        background: #10B981;
    }
    .student-photo-indicator.missing {
        background: #F59E0B;
    }
    .student-photo-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .photo-badge-soft {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.3px;
        line-height: 1.3;
        margin-bottom: 4px;
    }
    .photo-badge-ready {
        background: #ECFDF5;
        color: #065F46;
        border: 1px solid #A7F3D0;
    }
    .photo-badge-missing {
        background: #FFFBEB;
        color: #92400E;
        border: 1px solid #FDE68A;
    }
    .btn-photo-capture {
        display: inline-flex;
        align-items: center;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 6px;
        background: #FFFFFF;
        color: #2563EB;
        border: 1px solid #BFDBFE;
        white-space: nowrap;
        line-height: 1.4;
        transition: all 0.15s ease;
        cursor: pointer;
    }
    .btn-photo-capture:hover {
        background: #EFF6FF;
        border-color: #2563EB;
        color: #1D4ED8;
        box-shadow: 0 1px 3px rgba(37,99,235,0.12);
    }

    /* Filter Toolbar Inputs */
    .filter-select {
        border-radius: 8px !important;
        border: 1px solid #CBD5E1 !important;
        height: 38px !important;
        font-size: 13.5px !important;
        color: #0F172A !important;
        padding: 6px 12px !important;
        background-color: #FFFFFF !important;
    }
    .filter-select:focus {
        border-color: #2563EB !important;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12) !important;
    }

    /* =========================================================
       3. Print Layout & Paper Format Cards
       ========================================================= */
    .print-format-card {
        border: 2px solid #E2E8F0;
        border-radius: 12px;
        background: #FFFFFF;
        padding: 16px 18px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        position: relative;
        display: flex;
        align-items: flex-start;
    }
    .print-format-card:hover {
        border-color: #93C5FD;
        background: #F8FAFC;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    }
    .print-format-card.active {
        border-color: #2563EB !important;
        background: #F0F7FF !important;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.12) !important;
    }
    .format-radio-indicator {
        width: 20px;
        height: 20px;
        min-width: 20px;
        border-radius: 50%;
        border: 2px solid #CBD5E1;
        margin-right: 12px;
        margin-top: 2px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #FFFFFF;
        transition: all 0.2s ease;
    }
    .print-format-card.active .format-radio-indicator {
        border-color: #2563EB;
        background: #2563EB;
    }
    .format-radio-indicator::after {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #FFFFFF;
        display: block;
        opacity: 0;
        transform: scale(0.5);
        transition: all 0.2s ease;
    }
    .print-format-card.active .format-radio-indicator::after {
        opacity: 1;
        transform: scale(1);
    }
    .format-icon-pill {
        width: 38px;
        height: 38px;
        min-width: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        font-size: 16px;
    }
    .format-icon-pvc {
        background: #EEF2FF;
        color: #4F46E5;
    }
    .format-icon-a4 {
        background: #ECFEFF;
        color: #0891B2;
    }
    .format-icon-a3 {
        background: #FAF5FF;
        color: #9333EA;
    }

    /* Finishing options card */
    .finishing-options-card {
        border-radius: 12px;
        background: #FFFFFF;
        border: 1px solid #E2E8F0 !important;
    }

    /* Card Side Selection Cards */
    .card-side-card {
        border: 2px solid #E2E8F0;
        border-radius: 10px;
        background: #FFFFFF;
        padding: 10px 14px;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        height: 100%;
    }
    .card-side-card:hover {
        border-color: #93C5FD;
        background: #F8FAFC;
    }
    .card-side-card.active {
        border-color: #2563EB !important;
        background: #F0F7FF !important;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12) !important;
    }
    .side-radio-indicator {
        width: 18px;
        height: 18px;
        min-width: 18px;
        border-radius: 50%;
        border: 2px solid #CBD5E1;
        margin-right: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #FFFFFF;
        transition: all 0.2s ease;
    }
    .card-side-card.active .side-radio-indicator {
        border-color: #2563EB;
        background: #2563EB;
    }
    .side-radio-indicator::after {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #FFFFFF;
        display: none;
    }
    .card-side-card.active .side-radio-indicator::after {
        display: block;
    }

    /* Webcam Modal Styles */
    .webcam-container {
        position: relative;
        width: 100%;
        max-width: 440px;
        height: 330px;
        margin: 0 auto;
        border-radius: 12px;
        overflow: hidden;
        background: #0F172A;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #webcamVideo {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .webcam-overlay-guide {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        border: 2px dashed rgba(255, 255, 255, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .guide-oval {
        width: 170px;
        height: 220px;
        border: 2px solid rgba(37, 99, 235, 0.8);
        border-radius: 50%;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);
    }
    #capturedCanvas {
        display: none;
        width: 100%;
        max-width: 240px;
        border-radius: 10px;
        border: 2px solid #2563EB;
        margin: 0 auto;
    }
</style>
@endsection

@section('content')
<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between mb-3">
        <div>
            <h3 class="page-title font-weight-bold text-dark mb-1">
                <i class="fa fa-id-card text-primary mr-2"></i>{{ __('Student ID Card Generator') }}
            </h3>
            <div class="text-muted small">{{ __('Select students, verify photos, and generate batch ID badges formatted for PVC card printers or combined A4/A3 sheets.') }}</div>
        </div>
        <div>
            <x-button type="secondary" size="sm" icon="fa fa-sliders" :label="__('ID Card Settings')" :href="route('id-card-settings')" />
        </div>
    </div>

    @php
        $activeDesign = $schoolSettings['template_design'] ?? $schoolSettings['id_card_template'] ?? 'wave';
        $designLabels = [
            'wave'        => 'Curved Wave Arc',
            'diamond'     => 'Diamond Crest',
            'split'       => 'Modern Corporate Split',
            'minimal'     => 'Tech & Barcode',
            'ribbon'      => 'Ribbon Crest',
            'hexagon'     => 'Hexagon Polygon',
            'stripe'      => 'Diagonal Stripe',
            'clean'       => 'Clean Minimalist',
            'h_split'     => 'Split Horizon (Horizontal)',
            'h_executive' => 'Executive Crest (Horizontal)',
            'h_angled'    => 'Angled Diagonal (Horizontal)',
        ];
        $activeDesignLabel = $designLabels[$activeDesign] ?? ucwords(str_replace('_', ' ', $activeDesign));
        $primaryColor = $schoolSettings['primary_color'] ?? $schoolSettings['header_color'] ?? '#1E293B';
        $accentColor = $schoolSettings['accent_color'] ?? $schoolSettings['footer_color'] ?? '#06B6D4';
        $layoutType = $schoolSettings['layout_type'] ?? (str_starts_with($activeDesign, 'h') ? 'horizontal' : 'vertical');
    @endphp

    <!-- ACTIVE BLUEPRINT & BRAND CONFIGURATION SUMMARY -->
    <div class="blueprint-banner-card">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="blueprint-icon-box">
                    <i class="fa fa-id-badge fa-2x text-primary"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center">
                        <span class="font-weight-bold text-dark font-size-14">{{ __('Active Blueprint:') }} {{ $activeDesignLabel }}</span>
                        <span class="badge badge-primary text-uppercase font-weight-bold ml-2" style="font-size: 10px; padding: 3px 8px; border-radius: 6px;">{{ $layoutType }}</span>
                    </div>
                    <div class="d-flex align-items-center text-muted small mt-1">
                        <span>{{ __('Theme Colors:') }}</span>
                        <span class="color-swatch-badge" style="background-color: {{ $primaryColor }};" title="Primary Color: {{ $primaryColor }}"></span>
                        <span class="color-swatch-badge" style="background-color: {{ $accentColor }};" title="Accent Color: {{ $accentColor }}"></span>
                        <span class="text-secondary mx-2">&bull;</span>
                        <span><i class="fa fa-arrows-alt text-muted mr-1"></i>{{ __('Print Size:') }} {{ $schoolSettings['page_width'] ?? 54 }}mm &times; {{ $schoolSettings['page_height'] ?? 86 }}mm</span>
                    </div>
                </div>
            </div>
            <div class="mt-2 mt-sm-0">
                <x-button type="outline-primary" size="sm" icon="fa fa-sliders" :label="__('Customize in Settings')" :href="route('id-card-settings')" />
            </div>
        </div>
    </div>

    <!-- 1. STUDENT ROSTER & SELECTION TABLE -->
    <div class="card table-container-card mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="card-title font-weight-bold text-dark mb-0 d-flex align-items-center">
                        <span class="badge badge-primary font-weight-bold mr-2" style="font-size: 11px; padding: 4px 8px; border-radius: 6px;">Step 1</span>
                        <i class="fa fa-users text-primary mr-2"></i>{{ __('Select Students & Verify Photos') }}
                    </h5>
                    <small class="text-muted">{{ __('Select one or more students to generate cards. You can update any missing photo directly via webcam or image upload.') }}</small>
                </div>
            </div>

            <!-- Filter Toolbar (Clean inline flex layout) -->
            <div id="toolbar" class="d-flex align-items-center flex-wrap py-1">
                <div class="mr-3 mb-2" style="min-width: 250px;">
                    <label class="filter-menu font-weight-bold text-dark small mb-1 d-block">
                        <i class="fa fa-graduation-cap text-primary mr-1"></i>{{ __('Class Section') }} <span class="text-danger">*</span>
                    </label>
                    <select name="filter_class_section_id" id="filter_class_section_id" class="form-control filter-select">
                        <option value="">{{ __('select_class_section') }}</option>
                        @foreach ($class_sections as $class_section)
                            <option value="{{ $class_section->id }}">{{ $class_section->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mr-3 mb-2" style="min-width: 190px;">
                    <label class="filter-menu font-weight-bold text-dark small mb-1 d-block">
                        <i class="fa fa-calendar text-primary mr-1"></i>{{ __('Session Year') }} <span class="text-danger">*</span>
                    </label>
                    <select name="filter_session_year_id" id="filter_session_year_id" class="form-control filter-select">
                        @foreach ($sessionYears as $sessionYear)
                            <option value="{{ $sessionYear->id }}" {{ $sessionYear->default == 1 ? "selected" : "" }}>{{ $sessionYear->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="row mt-2">
                <div class="col-12">
                    <table aria-describedby="mydesc" class="table" id="table_list"
                           data-toggle="table" data-url="{{ route('students.show',[1]) }}" data-click-to-select="true"
                           data-side-pagination="server" data-pagination="true"
                           data-page-list="[10, 20, 50, 100, 200]" data-search="true"
                           data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                           data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id"
                           data-sort-order="desc" data-maintain-selected="true" data-query-params="studentDetailsQueryParams"
                           data-check-on-init="true" data-escape="false" data-response-handler="responseHandler">
                        <thead>
                        <tr>
                            <th data-field="state" data-checkbox="true" class="text-center align-middle"></th>
                            <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                            <th scope="col" data-field="no" class="text-center align-middle">{{ __('No.') }}</th>
                            <th scope="col" data-field="user.id" data-visible="false">{{ __('User Id') }}</th>
                            <th scope="col" data-field="user.full_name" class="align-middle font-weight-bold">{{ __('Student Name') }}</th>
                            <th scope="col" data-field="user.image" data-formatter="studentIdCardPhotoFormatter" class="align-middle">{{ __('Photo Status & Action') }}</th>
                            <th scope="col" data-field="class_section.full_name" class="align-middle">{{ __('Class Section') }}</th>
                            <th scope="col" data-field="admission_no" class="align-middle">{{ __('GR Number') }}</th>
                            <th scope="col" data-field="roll_number" class="align-middle text-center">{{ __('Roll No.') }}</th>
                            <th scope="col" data-field="user.dob" class="align-middle">{{ __('Date of Birth') }}</th>
                            <th scope="col" data-field="guardian.full_name" class="align-middle">{{ __('Guardian') }}</th>
                            <th scope="col" data-field="guardian.mobile" class="align-middle">{{ __('Guardian Mobile') }}</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>

            <!-- 2. PRINT LAYOUT & PAPER FORMAT SELECTION -->
            <div class="mt-4 pt-2">
                <div class="mb-3">
                    <h5 class="font-weight-bold text-dark mb-1 d-flex align-items-center">
                        <span class="badge badge-primary font-weight-bold mr-2" style="font-size: 11px; padding: 4px 8px; border-radius: 6px;">Step 2</span>
                        <i class="fa fa-print text-primary mr-2"></i> {{ __('Choose Print Layout & Paper Format') }}
                    </h5>
                    <div class="text-muted small">
                        {{ __('Select the target output format based on your printing equipment: dedicated PVC card printers or multi-card gang sheets.') }}
                    </div>
                </div>

                <!-- 3 Sleek Format Selection Cards -->
                <div class="row">
                    <!-- Option 1: Single Card PVC -->
                    <div class="col-12 col-md-4 mb-3 mb-md-0">
                        <div class="print-format-card active" id="formatOpt_single" onclick="setPrintFormat('single')">
                            <div class="format-radio-indicator"></div>
                            <div class="format-icon-pill format-icon-pvc">
                                <i class="fa fa-id-card"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold text-dark" style="font-size: 14px;">
                                    {{ __('Single Card (PVC / CR-80)') }}
                                </div>
                                <div class="text-muted small mt-1">
                                    {{ __('1 card per page • Thermal & PVC plastic printers') }}
                                </div>
                                <span class="badge badge-pill badge-primary mt-2" style="font-size: 10px; font-weight: 700; padding: 3px 8px;">
                                    CR-80 Thermal
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Option 2: Combined A4 Sheet -->
                    <div class="col-12 col-md-4 mb-3 mb-md-0">
                        <div class="print-format-card" id="formatOpt_a4" onclick="setPrintFormat('a4')">
                            <div class="format-radio-indicator"></div>
                            <div class="format-icon-pill format-icon-a4">
                                <i class="fa fa-file-text-o"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold text-dark" style="font-size: 14px;">
                                    {{ __('Combined A4 Sheet') }}
                                </div>
                                <div class="text-muted small mt-1">
                                    {{ __('8–9 cards per page • Standard office laser/inkjet') }}
                                </div>
                                <span class="badge badge-pill badge-info mt-2" style="font-size: 10px; font-weight: 700; padding: 3px 8px;">
                                    8–9 Cards / Page
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Option 3: Combined A3 Sheet -->
                    <div class="col-12 col-md-4">
                        <div class="print-format-card" id="formatOpt_a3" onclick="setPrintFormat('a3')">
                            <div class="format-radio-indicator"></div>
                            <div class="format-icon-pill format-icon-a3">
                                <i class="fa fa-files-o"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold text-dark" style="font-size: 14px;">
                                    {{ __('Combined A3 Sheet') }}
                                </div>
                                <div class="text-muted small mt-1">
                                    {{ __('16–18 cards per page • High-density bulk batch') }}
                                </div>
                                <span class="badge badge-pill badge-purple mt-2" style="font-size: 10px; font-weight: 700; padding: 3px 8px; background: #9333EA; color: #fff;">
                                    16–18 Cards / Page
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Sides to Print (Front, Back, or Both) -->
                <div class="mt-3 pt-2">
                    <label class="font-weight-bold text-dark small mb-2 d-flex align-items-center">
                        <i class="fa fa-clone text-primary mr-2"></i> {{ __('Card Sides to Include in Print / Export:') }}
                    </label>
                    <div class="row">
                        <!-- Option 1: Both Sides -->
                        <div class="col-12 col-md-4 mb-2 mb-md-0">
                            <div class="card-side-card active" id="sideOpt_both" onclick="setCardSide('both')">
                                <div class="side-radio-indicator"></div>
                                <div>
                                    <div class="font-weight-bold text-dark font-size-13">{{ __('Both Sides (Front & Back)') }}</div>
                                    <div class="text-muted small" style="font-size: 11px;">{{ __('Duplex PVC printer / Front & Back pages') }}</div>
                                </div>
                            </div>
                        </div>
                        <!-- Option 2: Front Side Only -->
                        <div class="col-12 col-md-4 mb-2 mb-md-0">
                            <div class="card-side-card" id="sideOpt_front" onclick="setCardSide('front')">
                                <div class="side-radio-indicator"></div>
                                <div>
                                    <div class="font-weight-bold text-dark font-size-13">{{ __('Front Side Only') }}</div>
                                    <div class="text-muted small" style="font-size: 11px;">{{ __('Student photo, name, and core details') }}</div>
                                </div>
                            </div>
                        </div>
                        <!-- Option 3: Back Side Only -->
                        <div class="col-12 col-md-4">
                            <div class="card-side-card" id="sideOpt_back" onclick="setCardSide('back')">
                                <div class="side-radio-indicator"></div>
                                <div>
                                    <div class="font-weight-bold text-dark font-size-13">{{ __('Back Side Only') }}</div>
                                    <div class="text-muted small" style="font-size: 11px;">{{ __('Rules, emergency, return info & signatures') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Finishing & Trimming Options -->
                <div class="card finishing-options-card mt-3">
                    <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="p-2 rounded bg-light mr-3 text-secondary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                <i class="fa fa-scissors text-primary font-size-16"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-dark mb-0" style="font-size: 13.5px;">
                                    {{ __('Dashed Cutting Guides & Crop Marks') }}
                                </div>
                                <div class="text-muted small">
                                    {{ __('Draws dashed alignment lines and corner marks to quickly slice badges with a guillotine paper trimmer on multi-card sheets.') }}
                                </div>
                            </div>
                        </div>
                        <div class="custom-control custom-switch mt-2 mt-sm-0">
                            <input type="checkbox" class="custom-control-input" id="toggleCutGuides" checked onchange="setCutGuides(this.checked)">
                            <label class="custom-control-label font-weight-bold text-dark cursor-pointer" for="toggleCutGuides">
                                <span class="badge badge-success ml-1 font-weight-bold" id="cutGuidesLabel">{{ __('Enabled') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. ACTION FOOTER & GENERATION FORM -->
            <div class="card mt-4 border shadow-sm" style="border-radius: 14px; background: #FFFFFF; border-color: #E2E8F0 !important;">
                <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between">
                    <div class="d-flex align-items-center mb-2 mb-sm-0">
                        <div class="mr-3">
                            <span id="selectedCountBadge" class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 13px; border-radius: 8px;">
                                <i class="fa fa-users mr-1"></i> <span id="selectedCountNumber">0</span> {{ __('Students Selected') }}
                            </span>
                        </div>
                        <div class="text-muted small d-none d-md-block">
                            {{ __('Select students from the table above, then click generate to print or export PDF.') }}
                        </div>
                    </div>
                    <form action="{{ url('students/generate-id-card') }}" target="_blank" method="post" id="idCardForm" class="m-0">
                        @csrf
                        <textarea id="user_id" name="user_id" style="display: none"></textarea>
                        <input type="hidden" name="template_design" id="selected_template_design" value="{{ $activeDesign }}">
                        <input type="hidden" name="template_id" id="selected_template_id" value="{{ $activeDesign }}">
                        <input type="hidden" name="primary_color" id="selected_primary_color" value="{{ $primaryColor }}">
                        <input type="hidden" name="accent_color" id="selected_accent_color" value="{{ $accentColor }}">
                        <input type="hidden" name="print_format" id="selected_print_format" value="single">
                        <input type="hidden" name="card_side" id="selected_card_side" value="both">
                        <input type="hidden" name="show_cut_guides" id="selected_show_cut_guides" value="1">

                        <button type="submit" class="btn btn-primary btn-lg font-weight-bold px-4 py-2" id="btnGenerateIdCard" style="border-radius: 10px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                            <i class="fa fa-print mr-2"></i> {{ __('Generate ID Cards') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL: Live Webcam Photo Booth & Crop Modal
     ========================================================================= -->
<div class="modal fade" id="photoCaptureModal" tabindex="-1" role="dialog" aria-labelledby="photoCaptureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 500px;">
        <div class="modal-content" style="border-radius: 16px;">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title font-weight-bold text-dark" id="photoCaptureModalLabel">
                        <i class="fa fa-camera text-primary mr-1"></i> {{ __('Live Photo Capture / Edit') }}
                    </h5>
                    <small class="text-muted" id="captureStudentName">Student Name</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="stopWebcamStream()">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3 text-center">
                <input type="hidden" id="captureStudentUserId" />

                <!-- Video View -->
                <div id="videoContainer" class="webcam-container mb-3">
                    <video id="webcamVideo" autoplay playsinline muted></video>
                    <div class="webcam-overlay-guide">
                        <div class="guide-oval"></div>
                    </div>
                </div>

                <!-- Canvas Snapshot Preview -->
                <div id="canvasPreviewContainer" class="d-none text-center mb-3">
                    <canvas id="capturedCanvas" width="300" height="400"></canvas>
                    <div class="badge badge-success mt-2 py-1 px-3" style="border-radius: 6px;">
                        <i class="fa fa-check mr-1"></i> {{ __('Snapshot Captured (3:4 ID Ratio)') }}
                    </div>
                </div>

                <!-- Camera Controls -->
                <div id="cameraActionButtons" class="d-flex align-items-center justify-content-center mb-2">
                    <button type="button" class="btn btn-primary px-4 py-2 font-weight-bold" id="btnSnapPhoto" onclick="captureSnapshot()">
                        <i class="fa fa-camera mr-2"></i>{{ __('Take Snapshot') }}
                    </button>
                </div>

                <div id="canvasActionButtons" class="d-none d-flex align-items-center justify-content-center mb-2">
                    <button type="button" class="btn btn-secondary px-3 py-2 mr-2 font-weight-bold" onclick="retakeSnapshot()">
                        <i class="fa fa-refresh mr-1"></i>{{ __('Retake') }}
                    </button>
                    <button type="button" class="btn btn-success px-4 py-2 font-weight-bold" id="btnSavePhoto" onclick="saveCapturedPhoto()">
                        <i class="fa fa-save mr-1"></i>{{ __('Save & Apply Photo') }}
                    </button>
                </div>

                <!-- Fallback File Upload -->
                <div class="pt-3 border-top mt-3">
                    <label class="small text-muted font-weight-bold mb-1">{{ __('Or upload image file from device:') }}</label>
                    <input type="file" id="filePhotoInput" class="form-control-file form-control-sm mx-auto" style="max-width: 260px;" accept="image/*" onchange="handleFileUpload(this)">
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    var $tableList = $('#table_list');
    var selections = [];
    var user_list = [];
    var activeWebcamStream = null;

    // Print Format Selection
    function setPrintFormat(val) {
        $('#selected_print_format').val(val);
        $('.print-format-card').removeClass('active');
        $('#formatOpt_' + val).addClass('active');
    }

    // Card Side Selection (both, front, back)
    function setCardSide(val) {
        $('#selected_card_side').val(val);
        $('.card-side-card').removeClass('active');
        $('#sideOpt_' + val).addClass('active');
    }

    // Cut Guides Toggle
    function setCutGuides(checked) {
        $('#selected_show_cut_guides').val(checked ? 1 : 0);
        $('#cutGuidesLabel')
            .removeClass('badge-success badge-secondary')
            .addClass(checked ? 'badge-success' : 'badge-secondary')
            .text(checked ? "{{ __('Enabled') }}" : "{{ __('Disabled') }}");
    }

    // Refresh table when class or session year changes
    $('#filter_class_section_id, #filter_session_year_id').on('change', function () {
        $tableList.bootstrapTable('refresh');
    });

    // Form Submission Validation
    $('#idCardForm').on('submit', function(e) {
        if (!user_list || user_list.length === 0) {
            e.preventDefault();
            alert('Please select at least one student from the table before generating ID cards.');
            return false;
        }
    });

    // Sleek Photo Status & Action Formatter for Bootstrap Table
    function studentIdCardPhotoFormatter(value, row) {
        const hasPhoto = (value && value !== '' && !value.includes('dummy_logo'));
        const photoUrl = hasPhoto ? value : "{{ asset('assets/dummy_logo.jpg') }}";
        const studentId = row.user ? row.user.id : row.id;
        const studentName = row.user ? row.user.full_name : (row.first_name + ' ' + row.last_name);

        return `
            <div class="student-photo-cell" id="photoRowUser_${studentId}">
                <div class="student-photo-thumb-wrap">
                    <img src="${photoUrl}" class="student-photo-thumb" id="imgPreview_${studentId}" alt="${escapeQuotes(studentName)}">
                    <span class="student-photo-indicator ${hasPhoto ? 'ready' : 'missing'}" id="imgIndicator_${studentId}" title="${hasPhoto ? 'Photo Ready' : 'No Photo'}"></span>
                </div>
                <div class="student-photo-meta">
                    <div class="mb-1" id="photoBadgeWrap_${studentId}">
                        ${hasPhoto 
                            ? '<span class="photo-badge-soft photo-badge-ready"><i class="fa fa-check-circle mr-1"></i>Photo Ready</span>' 
                            : '<span class="photo-badge-soft photo-badge-missing"><i class="fa fa-exclamation-circle mr-1"></i>No Photo</span>'}
                    </div>
                    <button type="button" class="btn-photo-capture" onclick="openPhotoCaptureModal(${studentId}, '${escapeQuotes(studentName)}', '${photoUrl}')">
                        <i class="fa fa-camera mr-1"></i>Capture / Edit
                    </button>
                </div>
            </div>
        `;
    }

    function escapeQuotes(str) {
        return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    // Response handler for selection tracking
    function responseHandler(res) {
        $.each(res.rows, function (i, row) {
            row.state = $.inArray(row.id, selections) !== -1;
        });
        return res;
    }

    $(function () {
        $tableList.on('check.bs.table check-all.bs.table uncheck.bs.table uncheck-all.bs.table',
            function (e, rowsAfter, rowsBefore) {
                user_list = [];
                var rows = rowsAfter;
                if (e.type === 'uncheck-all') {
                    rows = rowsBefore;
                }
                var ids = $.map(!$.isArray(rows) ? [rows] : rows, function (row) {
                    return row.id;
                });

                var func = $.inArray(e.type, ['check', 'check-all']) > -1 ? 'union' : 'difference';
                selections = window._[func](selections, ids);
                selections.forEach(element => {
                    user_list.push(element);
                });
                $('textarea#user_id').val(user_list.join(','));
                $('#selectedCountNumber').text(user_list.length);
                $('#selectedCountBadge').html('<i class="fa fa-users mr-1"></i> <span id="selectedCountNumber">' + user_list.length + '</span> {{ __("Students Selected") }}');
            });
    });

    // Open Live Webcam Modal
    function openPhotoCaptureModal(userId, name, currentImg) {
        $('#captureStudentUserId').val(userId);
        $('#captureStudentName').text(name);
        retakeSnapshot();
        $('#photoCaptureModal').modal('show');
        startWebcamStream();
    }

    function startWebcamStream() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: 'user'
                },
                audio: false
            }).then(function (stream) {
                activeWebcamStream = stream;
                var video = document.getElementById('webcamVideo');
                video.srcObject = stream;
                video.play();
            }).catch(function (err) {
                console.error("Camera access error:", err);
                alert("Could not access camera. You can still upload a photo file from your device.");
            });
        } else {
            alert("Webcam not supported in this browser. Please upload an image file.");
        }
    }

    function stopWebcamStream() {
        if (activeWebcamStream) {
            activeWebcamStream.getTracks().forEach(track => track.stop());
            activeWebcamStream = null;
        }
    }

    // Capture Snapshot from Video Stream (Crops to 3:4 ID Card Portrait)
    function captureSnapshot() {
        var video = document.getElementById('webcamVideo');
        var canvas = document.getElementById('capturedCanvas');
        var ctx = canvas.getContext('2d');

        canvas.width = 300;
        canvas.height = 400;

        var vW = video.videoWidth || 640;
        var vH = video.videoHeight || 480;
        var targetAspect = 3 / 4;
        var cropW = vH * targetAspect;
        var cropH = vH;

        if (cropW > vW) {
            cropW = vW;
            cropH = vW / targetAspect;
        }

        var startX = (vW - cropW) / 2;
        var startY = (vH - cropH) / 2;

        ctx.drawImage(video, startX, startY, cropW, cropH, 0, 0, 300, 400);

        $('#videoContainer').addClass('d-none');
        $('#cameraActionButtons').addClass('d-none');
        $('#canvasPreviewContainer').removeClass('d-none');
        $('#capturedCanvas').show();
        $('#canvasActionButtons').removeClass('d-none');
    }

    function retakeSnapshot() {
        $('#videoContainer').removeClass('d-none');
        $('#cameraActionButtons').removeClass('d-none');
        $('#canvasPreviewContainer').addClass('d-none');
        $('#capturedCanvas').hide();
        $('#canvasActionButtons').addClass('d-none');
    }

    // Save Captured Photo to Server
    function saveCapturedPhoto() {
        var userId = $('#captureStudentUserId').val();
        var canvas = document.getElementById('capturedCanvas');
        var base64Data = canvas.toDataURL('image/jpeg', 0.9);

        $('#btnSavePhoto').prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');

        $.ajax({
            url: `{{ url('students/update-photo') }}/${userId}`,
            type: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                image_data: base64Data
            },
            success: function (res) {
                $('#btnSavePhoto').prop('disabled', false).html('<i class="fa fa-save mr-1"></i> Save & Apply Photo');
                stopWebcamStream();
                $('#photoCaptureModal').modal('hide');

                if (typeof $.toast === 'function') {
                    $.toast({
                        text: res.message || 'Photo updated successfully!',
                        icon: 'success',
                        position: 'top-right'
                    });
                } else {
                    alert(res.message);
                }

                // Update table row preview instantly
                $(`#imgPreview_${userId}`).attr('src', res.image_url);
                $(`#imgIndicator_${userId}`).removeClass('missing').addClass('ready').attr('title', 'Photo Ready');
                $(`#photoBadgeWrap_${userId}`).html('<span class="photo-badge-soft photo-badge-ready"><i class="fa fa-check-circle mr-1"></i>Photo Ready</span>');
            },
            error: function (xhr) {
                $('#btnSavePhoto').prop('disabled', false).html('<i class="fa fa-save mr-1"></i> Save & Apply Photo');
                alert('Failed to save photo: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Server error'));
            }
        });
    }

    // Handle File Upload Fallback
    function handleFileUpload(input) {
        if (input.files && input.files[0]) {
            var userId = $('#captureStudentUserId').val();
            var formData = new FormData();
            formData.append('_token', "{{ csrf_token() }}");
            formData.append('image', input.files[0]);

            $.ajax({
                url: `{{ url('students/update-photo') }}/${userId}`,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    stopWebcamStream();
                    $('#photoCaptureModal').modal('hide');
                    if (typeof $.toast === 'function') {
                        $.toast({
                            text: 'Photo uploaded and saved!',
                            icon: 'success',
                            position: 'top-right'
                        });
                    } else {
                        alert('Photo updated successfully!');
                    }
                    $(`#imgPreview_${userId}`).attr('src', res.image_url);
                    $(`#imgIndicator_${userId}`).removeClass('missing').addClass('ready').attr('title', 'Photo Ready');
                    $(`#photoBadgeWrap_${userId}`).html('<span class="photo-badge-soft photo-badge-ready"><i class="fa fa-check-circle mr-1"></i>Photo Ready</span>');
                },
                error: function () {
                    alert('Upload failed.');
                }
            });
        }
    }

    $('#photoCaptureModal').on('hidden.bs.modal', function () {
        stopWebcamStream();
    });
</script>
@endsection
