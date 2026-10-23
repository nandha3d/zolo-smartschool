@extends('layouts.master')

@section('title')
    {{ __('ID Card Settings') }}
@endsection

@section('css')
<style>
    :root {
        --card-primary: {{ $settings['primary_color'] ?? $settings['header_color'] ?? '#1E293B' }};
        --card-accent: {{ $settings['accent_color'] ?? $settings['footer_color'] ?? '#06B6D4' }};
        --card-bg: #FFFFFF;
    }

    .theme-primary-bg { background-color: var(--card-primary) !important; }
    .theme-primary-color { color: var(--card-primary) !important; }
    .theme-primary-border { border-color: var(--card-primary) !important; }
    .theme-primary-fill { fill: var(--card-primary) !important; }

    .theme-accent-bg { background-color: var(--card-accent) !important; }
    .theme-accent-color { color: var(--card-accent) !important; }
    .theme-accent-border { border-color: var(--card-accent) !important; }
    .theme-accent-fill { fill: var(--card-accent) !important; }

    /* Palette Presets & Color Customizer */
    .palette-pill {
        cursor: pointer;
        border: 2px solid #E2E8F0;
        border-radius: 20px;
        padding: 4px 10px 4px 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 600;
        transition: all 0.2s ease;
        background: #FFFFFF;
        color: #334155;
    }
    .palette-pill:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.08);
        border-color: #94A3B8;
    }
    .palette-pill.active {
        border-color: #2563EB !important;
        background: #EFF6FF !important;
        color: #1D4ED8 !important;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
    }
    .palette-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: inline-block;
        border: 1.5px solid #FFFFFF;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        flex-shrink: 0;
    }
    .color-picker-input {
        width: 36px;
        height: 36px;
        padding: 0;
        border: 2px solid #CBD5E1;
        border-radius: 8px;
        cursor: pointer;
        background: transparent;
        transition: border-color 0.2s ease;
    }
    .color-picker-input:hover {
        border-color: #2563EB;
    }
    .color-picker-input::-webkit-color-swatch-wrapper {
        padding: 2px;
    }
    .color-picker-input::-webkit-color-swatch {
        border-radius: 6px;
        border: none;
    }

    /* Template Selection Gallery */
    .template-card {
        border: 2px solid #E2E8F0;
        border-radius: 12px;
        padding: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #ffffff;
        position: relative;
        text-align: center;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .template-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 18px rgba(0,0,0,0.08);
        border-color: #93C5FD;
    }
    .template-card.active {
        border-color: #2563EB !important;
        background: #F8FAFF !important;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25);
    }
    .template-check-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #2563EB;
        color: #ffffff;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        z-index: 5;
    }
    .template-card.active .template-check-badge {
        display: flex;
    }

    /* Miniature Card Previews */
    .mini-card-preview {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #CBD5E1;
        margin-bottom: 8px;
        box-shadow: 0 3px 8px rgba(0,0,0,0.06);
        background: #ffffff;
        position: relative;
        text-align: left;
        user-select: none;
        transition: transform 0.2s ease;
    }
    .mini-card-vertical {
        height: 206px;
        width: 134px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }
    .mini-card-horizontal {
        height: 134px;
        width: 200px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
    }
    .mini-school-name {
        font-size: 7px;
        font-weight: 800;
        color: #ffffff;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        line-height: 1.1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .mini-photo {
        border-radius: 4px;
        object-fit: cover;
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        display: block;
        margin: 0 auto;
    }
    .mini-student-name {
        font-size: 8px;
        font-weight: 800;
        line-height: 1.1;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .mini-badge {
        display: inline-block;
        font-size: 5px;
        font-weight: 800;
        padding: 1px 4px;
        border-radius: 3px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .mini-field-row {
        display: flex;
        justify-content: space-between;
        font-size: 5.5px;
        line-height: 1.25;
        color: #334155;
    }
    .mini-field-label {
        font-weight: 700;
        color: #64748B;
    }
    .mini-field-val {
        font-weight: 700;
        color: #0F172A;
    }
    .mini-card-ftr {
        padding: 2px 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 5px;
        font-weight: 700;
        color: #ffffff;
    }

    /* Selection Pills & Toggle Cards */
    .selection-pill.active div {
        border-color: #2563EB !important;
        background: #EFF6FF !important;
        color: #1D4ED8 !important;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
    }
    .field-toggle-card {
        border: 1.5px solid #E2E8F0 !important;
        transition: all 0.2s ease;
        background: #FFFFFF;
    }
    .field-toggle-card:hover {
        border-color: #94A3B8 !important;
        background: #F8FAFC;
    }
    .field-toggle-card.active {
        border-color: #2563EB !important;
        background: #EFF6FF !important;
    }
    .field-check-indicator {
        width: 18px;
        height: 18px;
        border-radius: 4px;
        border: 1.5px solid #CBD5E1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: transparent;
        transition: all 0.2s ease;
        background: #FFFFFF;
    }
    .field-toggle-card.active .field-check-indicator {
        background: #2563EB;
        border-color: #2563EB;
        color: #FFFFFF;
    }

    /* Segmented Target Switcher */
    .segmented-type-btn {
        cursor: pointer;
        padding: 10px 24px;
        font-weight: 700;
        border-radius: 10px;
        transition: all 0.2s ease;
        border: 2px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #64748B;
        background: #F1F5F9;
    }
    .segmented-type-btn.active {
        background: #2563EB !important;
        color: #FFFFFF !important;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
</style>
@endsection

@section('content')
<div class="content-wrapper">
    <!-- Header -->
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between mb-3">
        <div>
            <h3 class="page-title font-weight-bold text-dark mb-1">
                <i class="fa fa-id-badge text-primary mr-2"></i>{{ __('ID Card Settings') }}
            </h3>
            <p class="text-muted small mb-0">{{ __('Configure institution blueprints, brand palettes, photo styles, and visible fields for students and staff.') }}</p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="border-radius: 14px;">
                <div class="card-body p-4">
                    <form class="create-form-without-reset" id="formdata" action="{{ url('id-card-settings') }}" method="POST" enctype="multipart/form-data" novalidate="novalidate">
                        @csrf

                        <!-- User-Friendly Segmented Target Switcher -->
                        <div class="mb-4 text-center">
                            <span class="text-muted small font-weight-bold d-block mb-2">{{ __('CONFIGURE SETTINGS FOR:') }}</span>
                            <div class="d-inline-flex p-1 bg-light rounded-pill border" style="background: #F8FAFC !important;">
                                <label class="segmented-type-btn rounded-pill active mb-0" id="btnTypeStudent" onclick="switchSettingsType('Student')">
                                    <input type="radio" class="d-none type" name="type" id="type_student" value="Student" checked required>
                                    <i class="fa fa-graduation-cap"></i> {{ __('Student ID Card Settings') }}
                                </label>
                                <label class="segmented-type-btn rounded-pill mb-0" id="btnTypeStaff" onclick="switchSettingsType('Staff')">
                                    <input type="radio" class="d-none type" name="type" id="type_staff" value="Staff" required>
                                    <i class="fa fa-user-circle"></i> {{ __('Staff ID Card Settings') }}
                                </label>
                            </div>
                        </div>

                        <!-- STUDENT ID CARD PANEL -->
                        <div id="student-id-card-section">
                            @include('school-settings.student_id_card')
                        </div>

                        <!-- STAFF ID CARD PANEL -->
                        <div id="staff-id-card-section" style="display: none;">
                            @include('school-settings.staff_id_card')
                        </div>

                        <!-- 5. AUTHORIZED SIGNATURE SECTION -->
                        <div class="card mb-4 border" style="border-radius: 12px; border-color: #E2E8F0 !important;">
                            <div class="card-header bg-white py-3 border-bottom">
                                <h6 class="font-weight-bold text-dark mb-0">
                                    <i class="fa fa-pencil text-primary mr-2"></i>{{ __('Authorized School Authority Signature') }}
                                </h6>
                                <small class="text-muted">{{ __('Official principal / director signature printed on ID cards and certificates.') }}</small>
                            </div>
                            <div class="card-body p-3">
                                <div class="row align-items-center">
                                    <div class="col-md-7 mb-3 mb-md-0">
                                        <label class="font-weight-bold text-dark small mb-1">{{ __('Signature File (PNG with transparent background recommended)') }}</label>
                                        <input type="file" name="signature" accept="image/jpg,image/png,image/jpeg,image/svg" class="file-upload-default" />
                                        <div class="input-group">
                                            <input type="text" id="image" class="form-control file-upload-info form-control-sm" disabled placeholder="{{ __('Upload signature image') }}" />
                                            <span class="input-group-append">
                                                <button class="file-upload-browse btn btn-sm btn-secondary" type="button">
                                                    <i class="fa fa-upload mr-1"></i>{{ __('Upload') }}
                                                </button>
                                            </span>
                                        </div>
                                        <small class="text-info mt-1 d-block">
                                            <i class="fa fa-info-circle mr-1"></i>{{ __('Note: This signature is also automatically used in student certificates.') }}
                                        </small>
                                    </div>
                                    <div class="col-md-5">
                                        @if ($settings['signature'] ?? '')
                                            <div id="signature" class="p-2 border rounded text-center bg-light">
                                                <div class="small text-muted font-weight-bold mb-1">{{ __('Current Signature Preview') }}</div>
                                                <img src="{{ $settings['signature'] }}" class="img-fluid bg-white p-2 border rounded" style="max-height: 55px;" alt="Signature">
                                                <div class="mt-2">
                                                    <a href="" data-type="signature" class="btn btn-xs btn-danger id-card-settings" title="Remove signature">
                                                        <i class="fa fa-times mr-1"></i> {{ __('Remove') }}
                                                    </a>
                                                </div>
                                            </div>
                                        @else
                                            <div class="p-2 border rounded text-center text-muted small bg-light">
                                                <i class="fa fa-file-image-o fa-2x d-block mb-1 text-secondary"></i>
                                                {{ __('No signature uploaded yet') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- SUBMIT & RESET ACTIONS (Using Reusable x-button Component) --}}
                        <div class="d-flex align-items-center justify-content-end gap-2 pt-2 border-top">
                            <x-button buttonType="reset" type="secondary" size="md" icon="fa fa-undo" :label="__('Reset')" />
                            <x-button buttonType="submit" type="primary" size="md" icon="fa fa-check" :label="__('Save ID Card Settings')" id="create-btn" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    // Switch between Student and Staff settings
    function switchSettingsType(type) {
        if (type === 'Student') {
            $('#type_student').prop('checked', true);
            $('#btnTypeStudent').addClass('active');
            $('#btnTypeStaff').removeClass('active');
            $('#student-id-card-section').slideDown(300);
            $('#staff-id-card-section').slideUp(300);
        } else {
            $('#type_staff').prop('checked', true);
            $('#btnTypeStaff').addClass('active');
            $('#btnTypeStudent').removeClass('active');
            $('#student-id-card-section').slideUp(300);
            $('#staff-id-card-section').slideDown(300);
        }
    }

    // Template Archetype Picker (Student)
    function selectStudentDesign(designKey, element, layout) {
        $('.template-card').removeClass('active');
        $(element).addClass('active');
        $('#student_selected_template_design').val(designKey);
        $('#student_layout_type').val(layout);
    }

    function setLayoutType(layout) {
        $('#student_layout_type').val(layout);
    }

    // Color Customizer Reactivity (Student)
    function applyPalette(primary, accent, pillElement) {
        $('#palettePillContainer .palette-pill').removeClass('active');
        if (pillElement) {
            $(pillElement).addClass('active');
        }
        updateStudentColors(primary, accent);
    }

    function onCustomColorChange() {
        var primary = $('#primaryColorPicker').val();
        var accent = $('#accentColorPicker').val();
        $('#palettePillContainer .palette-pill').removeClass('active');
        updateStudentColors(primary, accent);
    }

    function onHexInputChange(type, hexVal) {
        if (!hexVal.startsWith('#')) {
            hexVal = '#' + hexVal;
        }
        if (/^#[0-9A-F]{6}$/i.test(hexVal)) {
            if (type === 'primary') {
                $('#primaryColorPicker').val(hexVal);
            } else {
                $('#accentColorPicker').val(hexVal);
            }
            onCustomColorChange();
        }
    }

    function onTextColorChange(hexVal) {
        if (!hexVal.startsWith('#')) {
            hexVal = '#' + hexVal;
        }
        $('#textColorPicker').val(hexVal);
        $('#header_footer_text_color').val(hexVal.toUpperCase());
    }

    function updateStudentColors(primary, accent) {
        document.documentElement.style.setProperty('--card-primary', primary);
        document.documentElement.style.setProperty('--card-accent', accent);

        $('#primaryColorPicker').val(primary);
        $('#accentColorPicker').val(accent);
        $('#header_color').val(primary.toUpperCase());
        $('#footer_color').val(accent.toUpperCase());
        $('#student_primary_color').val(primary);
        $('#student_accent_color').val(accent);
    }

    // Color Customizer Reactivity (Staff)
    function applyStaffPalette(primary, accent, pillElement) {
        $(pillElement).siblings().removeClass('active');
        $(pillElement).addClass('active');
        updateStaffColors(primary, accent);
    }

    function onStaffColorChange() {
        var primary = $('#staffPrimaryColorPicker').val();
        var accent = $('#staffAccentColorPicker').val();
        updateStaffColors(primary, accent);
    }

    function onStaffHexInputChange(type, hexVal) {
        if (!hexVal.startsWith('#')) {
            hexVal = '#' + hexVal;
        }
        if (/^#[0-9A-F]{6}$/i.test(hexVal)) {
            if (type === 'header') {
                $('#staffPrimaryColorPicker').val(hexVal);
            } else {
                $('#staffAccentColorPicker').val(hexVal);
            }
            onStaffColorChange();
        }
    }

    function onStaffTextColorChange(hexVal) {
        if (!hexVal.startsWith('#')) {
            hexVal = '#' + hexVal;
        }
        $('#staffTextColorPicker').val(hexVal);
        $('#staff_header_footer_text_color').val(hexVal.toUpperCase());
    }

    function updateStaffColors(primary, accent) {
        $('#staffPrimaryColorPicker').val(primary);
        $('#staffAccentColorPicker').val(accent);
        $('#staff_header_color').val(primary.toUpperCase());
        $('#staff_footer_color').val(accent.toUpperCase());
        $('#staff_primary_color').val(primary);
        $('#staff_accent_color').val(accent);
    }

    // Physical Dimension Setters
    function setDimensions(w, h) {
        $('#page_width').val(w);
        $('#page_height').val(h);
    }

    function setStaffDimensions(w, h) {
        $('#staff_page_width').val(w);
        $('#staff_page_height').val(h);
    }

    // Radio Pill Selection
    function setSelectionPill(inputEl) {
        var name = $(inputEl).attr('name');
        $('input[name="' + name + '"]').closest('.selection-pill').removeClass('active');
        $(inputEl).closest('.selection-pill').addClass('active');
    }

    // Card Field Toggles
    function toggleFieldCard(checkboxEl) {
        if ($(checkboxEl).is(':checked')) {
            $(checkboxEl).closest('.field-toggle-card').addClass('active');
        } else {
            $(checkboxEl).closest('.field-toggle-card').removeClass('active');
        }
    }

    function toggleAllCheckboxes(containerSelector, checkAll) {
        $(containerSelector + ' .field-toggle-input').each(function() {
            $(this).prop('checked', checkAll);
            toggleFieldCard(this);
        });
    }

    $(document).ready(function() {
        // Initialize active colors on document
        var initPrimary = $('#header_color').val() || '#1E293B';
        var initAccent = $('#footer_color').val() || '#06B6D4';
        updateStudentColors(initPrimary, initAccent);
    });
</script>
@endsection
