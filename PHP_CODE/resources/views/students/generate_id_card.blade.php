@extends('layouts.master')

@section('title')
    {{ __('Generate Student ID Card') }}
@endsection

@section('css')
<style>
    :root {
        --card-primary: #1E293B;
        --card-accent: #06B6D4;
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
        border-color: #2F6BFF !important;
        background: #EFF6FF !important;
        color: #1D4ED8 !important;
        box-shadow: 0 0 0 2px rgba(47, 107, 255, 0.2);
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
        border-color: #2F6BFF;
    }
    .color-picker-input::-webkit-color-swatch-wrapper {
        padding: 2px;
    }
    .color-picker-input::-webkit-color-swatch {
        border-radius: 6px;
        border: none;
    }

    /* Template Selection Gallery Styles */
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
        border-color: #2F6BFF !important;
        background: #F8FAFF !important;
        box-shadow: 0 0 0 2px rgba(47, 107, 255, 0.25);
    }
    .template-check-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #2F6BFF;
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

    .mini-school-logo {
        width: 14px;
        height: 14px;
        border-radius: 3px;
        background: rgba(255,255,255,0.95);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8px;
        flex-shrink: 0;
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

    /* Photo Booth Webcam Modal */
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
        border: 2px solid rgba(47, 107, 255, 0.7);
        border-radius: 50%;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35);
    }
    #capturedCanvas {
        display: none;
        width: 100%;
        max-width: 240px;
        border-radius: 10px;
        border: 2px solid #2F6BFF;
        margin: 0 auto;
    }
</style>
@endsection

@section('content')
<div class="content-wrapper">
    <div class="page-header d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="page-title mb-1">{{ __('Student ID Card Generator') }}</h3>
            <div class="text-muted small">{{ __('Select from 11 distinct structural designs with live customizable brand colors, and export to Single PVC or A4/A3 sheets.') }}</div>
        </div>
        <div>
            <a href="{{ route('id-card-settings') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-sliders mr-1"></i> {{ __('ID Card Settings') }}
            </a>
        </div>
    </div>

    <!-- 1. TEMPLATE SELECTION GALLERY WITH LIVE COLOR CUSTOMIZER -->
    <div class="card mb-4 shadow-sm border-0" style="border-radius: 14px;">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="font-weight-bold text-dark mb-0">
                        <i class="fa fa-id-card text-primary mr-2"></i>{{ __('Step 1: Choose Design Layout & Customize Brand Colors') }}
                    </h5>
                    <small class="text-muted">{{ __('Pick a distinct structural design and customize its primary and accent colors with real-time live preview.') }}</small>
                </div>
                <!-- Orientation Tabs -->
                <ul class="nav nav-pills" id="templateTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" id="vertical-tab" data-toggle="pill" href="#verticalTemplates" role="tab">
                            <i class="fa fa-mobile mr-1"></i> {{ __('Vertical Badges (8 Designs)') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="horizontal-tab" data-toggle="pill" href="#horizontalTemplates" role="tab">
                            <i class="fa fa-credit-card mr-1"></i> {{ __('Horizontal Badges (3 Designs)') }}
                        </a>
                    </li>
                </ul>
            </div>

            <!-- INTERACTIVE COLOR CUSTOMIZER BAR -->
            <div class="card bg-light border-0 mb-4 p-3" style="border-radius: 12px; background: #F8FAFC !important; border: 1px solid #E2E8F0 !important;">
                <div class="row align-items-center">
                    <!-- Palette Presets -->
                    <div class="col-xl-7 col-lg-12 mb-3 mb-xl-0">
                        <span class="font-weight-bold text-dark small d-block mb-2">
                            <i class="fa fa-paint-brush text-primary mr-1"></i> {{ __('One-Click Theme Palettes:') }}
                        </span>
                        <div class="d-flex flex-wrap gap-2" id="palettePillContainer">
                            <button type="button" class="palette-pill active" onclick="applyPalette('#1E293B', '#06B6D4', this)">
                                <span class="palette-dot" style="background: linear-gradient(135deg, #1E293B 50%, #06B6D4 50%);"></span>
                                <span>Navy & Cyan</span>
                            </button>
                            <button type="button" class="palette-pill" onclick="applyPalette('#9F1239', '#F43F5E', this)">
                                <span class="palette-dot" style="background: linear-gradient(135deg, #9F1239 50%, #F43F5E 50%);"></span>
                                <span>Crimson Metro</span>
                            </button>
                            <button type="button" class="palette-pill" onclick="applyPalette('#065F46', '#10B981', this)">
                                <span class="palette-dot" style="background: linear-gradient(135deg, #065F46 50%, #10B981 50%);"></span>
                                <span>Emerald Academy</span>
                            </button>
                            <button type="button" class="palette-pill" onclick="applyPalette('#4338CA', '#8B5CF6', this)">
                                <span class="palette-dot" style="background: linear-gradient(135deg, #4338CA 50%, #8B5CF6 50%);"></span>
                                <span>Royal Indigo</span>
                            </button>
                            <button type="button" class="palette-pill" onclick="applyPalette('#0F172A', '#F59E0B', this)">
                                <span class="palette-dot" style="background: linear-gradient(135deg, #0F172A 50%, #F59E0B 50%);"></span>
                                <span>Obsidian Gold</span>
                            </button>
                            <button type="button" class="palette-pill" onclick="applyPalette('#0F766E', '#14B8A6', this)">
                                <span class="palette-dot" style="background: linear-gradient(135deg, #0F766E 50%, #14B8A6 50%);"></span>
                                <span>Teal Horizon</span>
                            </button>
                            <button type="button" class="palette-pill" onclick="applyPalette('#C2410C', '#F97316', this)">
                                <span class="palette-dot" style="background: linear-gradient(135deg, #C2410C 50%, #F97316 50%);"></span>
                                <span>Sunset Amber</span>
                            </button>
                            <button type="button" class="palette-pill" onclick="applyPalette('#18181B', '#64748B', this)">
                                <span class="palette-dot" style="background: linear-gradient(135deg, #18181B 50%, #64748B 50%);"></span>
                                <span>Slate Pro</span>
                            </button>
                        </div>
                    </div>

                    <!-- Custom HTML5 Color Pickers -->
                    <div class="col-xl-5 col-lg-12 border-left-xl pl-xl-3">
                        <span class="font-weight-bold text-dark small d-block mb-2">
                            <i class="fa fa-sliders text-primary mr-1"></i> {{ __('Custom Brand Colors (Choose Any Hex):') }}
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <!-- Primary Picker -->
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" id="primaryColorPicker" class="color-picker-input" value="#1E293B" onchange="onCustomColorChange()" title="Primary Header & Structural Color">
                                <div>
                                    <label class="small text-muted font-weight-bold d-block mb-0" style="font-size: 10px;">PRIMARY</label>
                                    <input type="text" id="primaryHexInput" class="form-control form-control-sm text-uppercase font-weight-bold px-2 py-0" style="width: 78px; height: 26px; font-size: 11px;" value="#1E293B" onchange="onHexInputChange('primary', this.value)">
                                </div>
                            </div>

                            <!-- Accent Picker -->
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" id="accentColorPicker" class="color-picker-input" value="#06B6D4" onchange="onCustomColorChange()" title="Accent Highlight & Ribbon Color">
                                <div>
                                    <label class="small text-muted font-weight-bold d-block mb-0" style="font-size: 10px;">ACCENT</label>
                                    <input type="text" id="accentHexInput" class="form-control form-control-sm text-uppercase font-weight-bold px-2 py-0" style="width: 78px; height: 26px; font-size: 11px;" value="#06B6D4" onchange="onHexInputChange('accent', this.value)">
                                </div>
                            </div>

                            <!-- Reset Button -->
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-auto" onclick="resetDefaultColors()" title="Reset to Classic Navy">
                                <i class="fa fa-undo mr-1"></i> {{ __('Reset') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TEMPLATES GALLERY -->
            <div class="tab-content" id="templateTabsContent">
                <!-- TAB 1: VERTICAL TEMPLATES (8 DISTINCT ARCHETYPES) -->
                <div class="tab-pane fade show active" id="verticalTemplates" role="tabpanel">
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
                        @php
                            $schoolTitle = !empty($schoolSettings['school_name']) ? strtoupper($schoolSettings['school_name']) : 'ZOLO DEMO SCHOOL';
                        @endphp

                        <!-- 1. CURVED WAVE ARC -->
                        <div class="col mb-3">
                            <div class="template-card active" onclick="selectDesign('wave', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-vertical">
                                    <!-- Header -->
                                    <div>
                                        <div class="p-1 text-center theme-primary-bg">
                                            <div class="mini-school-name">{{ $schoolTitle }}</div>
                                        </div>
                                        <svg viewBox="0 0 200 24" style="display:block; width:100%; height:14px; margin-top:-1px;">
                                            <path d="M0,0 L200,0 L200,8 Q100,24 0,8 Z" class="theme-primary-fill" />
                                            <path d="M0,8 Q100,24 200,8 L200,11 Q100,27 0,11 Z" class="theme-accent-fill" />
                                        </svg>
                                    </div>
                                    <!-- Photo & Details -->
                                    <div style="padding: 0 6px; text-align: center; margin-top: -8px;">
                                        <img class="mini-photo" src="{{ asset('assets/mock_student_boy.jpg') }}" width="46" height="46" style="border-radius: 50%; border: 2px solid var(--card-accent);" alt="Photo">
                                        <div class="mini-student-name theme-primary-color">ALEX MORGAN</div>
                                        <span class="mini-badge theme-accent-bg text-white">STUDENT</span>
                                        <div style="background: rgba(241, 245, 249, 0.8); border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                            <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">10 - A</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Roll / GR:</span><span class="mini-field-val">#14 &bull; GR-1024</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">O+</span></div>
                                        </div>
                                    </div>
                                    <!-- Footer Wave -->
                                    <div>
                                        <svg viewBox="0 0 200 12" style="display:block; width:100%; height:8px;">
                                            <path d="M0,6 Q100,0 200,6 L200,12 L0,12 Z" class="theme-primary-fill" />
                                        </svg>
                                        <div class="mini-card-ftr theme-primary-bg" style="border-top: 1px solid var(--card-accent);">
                                            <span>Wave Arc</span>
                                            <span>Valid 2025</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">1. Curved Wave Arc</div>
                                <small class="text-muted" style="font-size: 10.5px;">Curved SVG crest & circular portrait</small>
                            </div>
                        </div>

                        <!-- 2. DIAMOND / RHOMBUS BADGE -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('diamond', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-vertical">
                                    <!-- Header -->
                                    <div>
                                        <div class="p-1 text-center theme-primary-bg">
                                            <div class="mini-school-name">{{ $schoolTitle }}</div>
                                            <div style="font-size: 5px; color: var(--card-accent); font-weight: 700;">SESSION 2024-25</div>
                                        </div>
                                        <div style="width: 0; height: 0; border-left: 67px solid transparent; border-right: 67px solid transparent; border-top: 8px solid var(--card-primary); margin: 0 auto;"></div>
                                    </div>
                                    <!-- Diamond Portrait & Details -->
                                    <div style="padding: 0 6px; text-align: center;">
                                        <div style="display: inline-block; padding: 2px; border: 2px solid var(--card-accent); border-radius: 8px; background: #fff;">
                                            <img class="mini-photo" src="{{ asset('assets/mock_student_girl.jpg') }}" width="42" height="48" style="border-radius: 5px;" alt="Photo">
                                        </div>
                                        <div class="mini-student-name" style="background: var(--card-primary); color: #fff; padding: 1px 6px; border-radius: 4px; display: inline-block; margin-top: 2px;">
                                            SOPHIA TAYLOR
                                        </div>
                                        <div style="font-size: 5px; color: #DC2626; font-weight: 800; margin: 1px 0;">BLOOD GROUP: B+</div>
                                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 4px; padding: 2px 4px; margin: 2px 0;">
                                            <div class="mini-field-row"><span class="mini-field-label">Adm No:</span><span class="mini-field-val">ADM-4091</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Grade 9-B</span></div>
                                        </div>
                                    </div>
                                    <!-- Footer Ribbon -->
                                    <div class="mini-card-ftr theme-primary-bg" style="border-top: 2px solid var(--card-accent);">
                                        <span>Diamond Rhombus</span>
                                        <span>Contact Verified</span>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">2. Diamond / Rhombus</div>
                                <small class="text-muted" style="font-size: 10.5px;">Framed rhombus badge & chevron split</small>
                            </div>
                        </div>

                        <!-- 3. DIAGONAL SPLIT -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('diagonal', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-vertical">
                                    <!-- Diagonal Top Banner -->
                                    <div>
                                        <div class="p-1 theme-primary-bg text-center">
                                            <div class="mini-school-name">{{ $schoolTitle }}</div>
                                        </div>
                                        <svg viewBox="0 0 200 24" style="display:block; width:100%; height:14px; margin-top:-1px;">
                                            <polygon points="0,0 200,0 200,8 0,24" class="theme-primary-fill" />
                                            <polygon points="0,24 200,8 200,12 0,28" class="theme-accent-fill" />
                                        </svg>
                                    </div>
                                    <!-- Photo & Details -->
                                    <div style="padding: 0 6px; text-align: center;">
                                        <img class="mini-photo" src="{{ asset('assets/mock_student_boy.jpg') }}" width="44" height="50" style="border: 2px solid var(--card-accent); border-radius: 4px;" alt="Photo">
                                        <div class="mini-student-name theme-primary-color">ETHAN HUNT</div>
                                        <span class="mini-badge" style="background: var(--card-accent); color: #fff;">CLASS 10-C</span>
                                        <div style="background: #F1F5F9; border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                            <div class="mini-field-row"><span class="mini-field-label">Roll:</span><span class="mini-field-val">#21</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">DOB:</span><span class="mini-field-val">14-08-2009</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">A+</span></div>
                                        </div>
                                    </div>
                                    <!-- Diagonal Bottom Stripe -->
                                    <div>
                                        <svg viewBox="0 0 200 14" style="display:block; width:100%; height:9px;">
                                            <polygon points="0,5 200,0 200,14 0,14" class="theme-primary-fill" />
                                        </svg>
                                        <div class="mini-card-ftr theme-primary-bg" style="border-top: 1px solid var(--card-accent);">
                                            <span>Diagonal Split</span>
                                            <span>Student ID</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">3. Diagonal Angle Split</div>
                                <small class="text-muted" style="font-size: 10.5px;">Dynamic angled slash & dual stripes</small>
                            </div>
                        </div>

                        <!-- 4. HEXAGON TECH -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('hexagon', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-vertical">
                                    <!-- Hex Header -->
                                    <div class="p-1 text-center theme-primary-bg" style="border-bottom: 2px solid var(--card-accent);">
                                        <div class="mini-school-name">{{ $schoolTitle }}</div>
                                        <span class="mini-badge text-white" style="background: rgba(255,255,255,0.2); font-size: 4.5px;">SECURITY IDENTIFIER</span>
                                    </div>
                                    <!-- Hexagon Photo -->
                                    <div style="padding: 4px 6px 0 6px; text-align: center;">
                                        <div style="display: inline-block; padding: 2px; background: var(--card-accent); clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);">
                                            <img class="mini-photo" src="{{ asset('assets/mock_student_girl.jpg') }}" width="44" height="48" style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);" alt="Photo">
                                        </div>
                                        <div class="mini-student-name theme-primary-color">MAYA SHARMA</div>
                                        <div style="font-size: 5px; font-weight: 800; color: var(--card-accent);">[ VERIFIED ID 2025 ]</div>
                                        <div style="background: #F8FAFC; border: 1px dashed var(--card-accent); border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                            <div class="mini-field-row"><span class="mini-field-label">GR ID:</span><span class="mini-field-val">GR-8832</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Sec:</span><span class="mini-field-val">10 - B</span></div>
                                        </div>
                                    </div>
                                    <!-- Footer -->
                                    <div class="mini-card-ftr theme-primary-bg" style="border-top: 1.5px solid var(--card-accent);">
                                        <span>Hexagon Cyber</span>
                                        <span>Authorized</span>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">4. Hexagon Tech Shield</div>
                                <small class="text-muted" style="font-size: 10.5px;">Futuristic hexagonal frame & tech badge</small>
                            </div>
                        </div>

                        <!-- 5. CLASSIC ACADEMIC CREST -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('classic', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-vertical" style="border: 2px double var(--card-primary);">
                                    <!-- Classic Arched Header -->
                                    <div class="p-1 text-center theme-primary-bg">
                                        <div class="mini-school-logo mx-auto mb-1">
                                            <i class="fa fa-graduation-cap theme-primary-color"></i>
                                        </div>
                                        <div class="mini-school-name">{{ $schoolTitle }}</div>
                                    </div>
                                    <div style="height: 2px; background: var(--card-accent);"></div>
                                    <!-- Center Portrait -->
                                    <div style="padding: 4px 6px; text-align: center;">
                                        <img class="mini-photo" src="{{ asset('assets/mock_student_boy.jpg') }}" width="44" height="52" style="border: 2px solid var(--card-accent);" alt="Photo">
                                        <div class="mini-student-name theme-primary-color">ARJUN PATEL</div>
                                        <div style="font-size: 5.5px; font-weight: 700; color: #64748B;">STUDENT IDENTITY CARD</div>
                                        <div style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 3px; padding: 2px 4px; margin: 3px 0;">
                                            <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">12 - Sci</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Roll:</span><span class="mini-field-val">#03</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">O+</span></div>
                                        </div>
                                    </div>
                                    <!-- Classic Footer -->
                                    <div class="mini-card-ftr theme-primary-bg" style="border-top: 1px solid var(--card-accent);">
                                        <span>Classic Crest</span>
                                        <span>Principal</span>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">5. Classic Academic Crest</div>
                                <small class="text-muted" style="font-size: 10.5px;">Formal double-frame border & institution crest</small>
                            </div>
                        </div>

                        <!-- 6. EXECUTIVE MINIMAL -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('minimal', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-vertical" style="border-left: 5px solid var(--card-primary);">
                                    <!-- Header -->
                                    <div class="p-1 border-bottom">
                                        <div class="d-flex align-items-center gap-1">
                                            <span style="width: 5px; height: 5px; border-radius: 50%; background: var(--card-accent); display: inline-block;"></span>
                                            <div class="mini-school-name theme-primary-color" style="font-size: 6.5px;">{{ $schoolTitle }}</div>
                                        </div>
                                    </div>
                                    <!-- Photo & Minimal Rows -->
                                    <div style="padding: 4px 6px; text-align: left;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <img class="mini-photo m-0" src="{{ asset('assets/mock_student_girl.jpg') }}" width="38" height="46" style="border: 1.5px solid var(--card-accent); border-radius: 3px;" alt="Photo">
                                            <div>
                                                <div class="mini-student-name theme-primary-color" style="font-size: 7.5px;">EMMA WATSON</div>
                                                <span class="mini-badge theme-accent-bg text-white">STUDENT</span>
                                            </div>
                                        </div>
                                        <div style="border-top: 1px solid #E2E8F0; padding-top: 2px; margin-top: 2px;">
                                            <div class="mini-field-row"><span class="mini-field-label">ID:</span><span class="mini-field-val">STU-9921</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Grade 11</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">AB+</span></div>
                                        </div>
                                    </div>
                                    <!-- Minimal Footer -->
                                    <div class="p-1 border-top d-flex justify-content-between text-muted" style="font-size: 5px;">
                                        <span class="theme-primary-color font-weight-bold">Minimal Spine</span>
                                        <span>Official Pass</span>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">6. Executive Minimalist</div>
                                <small class="text-muted" style="font-size: 10.5px;">Vertical color spine & crisp typography</small>
                            </div>
                        </div>

                        <!-- 7. CHEVRON V-RIBBON -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('chevron', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-vertical">
                                    <!-- Chevron V Header -->
                                    <div>
                                        <div class="p-1 text-center theme-primary-bg">
                                            <div class="mini-school-name">{{ $schoolTitle }}</div>
                                        </div>
                                        <svg viewBox="0 0 200 24" style="display:block; width:100%; height:14px; margin-top:-1px;">
                                            <polygon points="0,0 200,0 200,10 100,22 0,10" class="theme-primary-fill" />
                                            <polyline points="0,10 100,22 200,10" stroke="var(--card-accent)" stroke-width="2" fill="none" />
                                        </svg>
                                    </div>
                                    <!-- Photo & Details -->
                                    <div style="padding: 0 6px; text-align: center;">
                                        <img class="mini-photo" src="{{ asset('assets/mock_student_boy.jpg') }}" width="44" height="48" style="border: 2px solid var(--card-accent); border-radius: 6px;" alt="Photo">
                                        <div class="mini-student-name theme-primary-color">ROHIT VERMA</div>
                                        <span class="mini-badge" style="background: var(--card-primary); color: #fff;">CLASS 10-A</span>
                                        <div style="background: #F1F5F9; border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                            <div class="mini-field-row"><span class="mini-field-label">Roll:</span><span class="mini-field-val">#12</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">B+</span></div>
                                        </div>
                                    </div>
                                    <!-- Footer Chevron -->
                                    <div class="mini-card-ftr theme-primary-bg" style="border-top: 1.5px solid var(--card-accent);">
                                        <span>Chevron Ribbon</span>
                                        <span>Session 2025</span>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">7. Chevron V-Ribbon</div>
                                <small class="text-muted" style="font-size: 10.5px;">Athletic V-ribbon & angled banner</small>
                            </div>
                        </div>

                        <!-- 8. DUAL WAVE SWOOSH -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('swoosh', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-vertical">
                                    <!-- Dual Swoosh Header -->
                                    <div>
                                        <div class="p-1 text-center theme-primary-bg">
                                            <div class="mini-school-name">{{ $schoolTitle }}</div>
                                        </div>
                                        <svg viewBox="0 0 200 24" style="display:block; width:100%; height:14px; margin-top:-1px;">
                                            <path d="M0,0 L200,0 L200,12 C140,24 60,6 0,18 Z" class="theme-primary-fill" />
                                            <path d="M0,18 C60,6 140,24 200,12 L200,15 C140,27 60,9 0,21 Z" class="theme-accent-fill" />
                                        </svg>
                                    </div>
                                    <!-- Photo & Details -->
                                    <div style="padding: 0 6px; text-align: center; margin-top: -6px;">
                                        <img class="mini-photo" src="{{ asset('assets/mock_student_girl.jpg') }}" width="46" height="46" style="border-radius: 50%; border: 2.5px solid var(--card-accent);" alt="Photo">
                                        <div class="mini-student-name theme-primary-color">PRIYA NAIR</div>
                                        <span class="mini-badge theme-accent-bg text-white">STUDENT</span>
                                        <div style="background: rgba(241, 245, 249, 0.8); border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                            <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Grade 8</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">O+</span></div>
                                        </div>
                                    </div>
                                    <!-- Footer Swoosh -->
                                    <div>
                                        <svg viewBox="0 0 200 12" style="display:block; width:100%; height:8px;">
                                            <path d="M0,4 C80,12 160,0 200,8 L200,12 L0,12 Z" class="theme-primary-fill" />
                                        </svg>
                                        <div class="mini-card-ftr theme-primary-bg" style="border-top: 1px solid var(--card-accent);">
                                            <span>Fluid Swoosh</span>
                                            <span>ID Valid</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">8. Dual Wave Swoosh</div>
                                <small class="text-muted" style="font-size: 10.5px;">Fluid organic overlapping swooshes</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: HORIZONTAL TEMPLATES (3 DISTINCT ARCHETYPES) -->
                <div class="tab-pane fade" id="horizontalTemplates" role="tabpanel">
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                        <!-- H1: TWO-TONE HORIZONTAL SPLIT -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('h_split', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-horizontal">
                                    <div class="d-flex h-100">
                                        <!-- Left Photo Bar -->
                                        <div class="theme-primary-bg p-2 text-center text-white d-flex flex-column justify-content-between" style="width: 38%;">
                                            <img class="mini-photo" src="{{ asset('assets/mock_student_boy.jpg') }}" width="42" height="50" style="border: 2px solid var(--card-accent); border-radius: 4px;" alt="Photo">
                                            <div style="font-size: 5.5px; font-weight: 700; color: var(--card-accent);">GR: 1042</div>
                                        </div>
                                        <!-- Right Fields -->
                                        <div class="p-2 d-flex flex-column justify-content-between" style="width: 62%;">
                                            <div>
                                                <div class="mini-school-name theme-primary-color" style="font-size: 7px;">{{ $schoolTitle }}</div>
                                                <div class="mini-student-name theme-primary-color mt-1">DANIEL CRAIG</div>
                                                <span class="mini-badge theme-accent-bg text-white">STUDENT &bull; 10-A</span>
                                            </div>
                                            <div style="background: #F8FAFC; border-radius: 3px; padding: 2px 4px;">
                                                <div class="mini-field-row"><span class="mini-field-label">Roll:</span><span class="mini-field-val">#15</span></div>
                                                <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">O+</span></div>
                                                <div class="mini-field-row"><span class="mini-field-label">Contact:</span><span class="mini-field-val">+1 234 567</span></div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 5px;">
                                                <span>Signatory</span>
                                                <span class="badge badge-secondary" style="font-size: 4.5px;">Valid 2025</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">H1. Two-Tone Split</div>
                                <small class="text-muted" style="font-size: 10.5px;">Solid primary photo column & clean data matrix</small>
                            </div>
                        </div>

                        <!-- H2: HORIZONTAL FLUID WAVE -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('h_wave', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-horizontal">
                                    <!-- Wave Header -->
                                    <div>
                                        <div class="p-1 theme-primary-bg text-center">
                                            <div class="mini-school-name">{{ $schoolTitle }}</div>
                                        </div>
                                        <svg viewBox="0 0 300 16" style="display:block; width:100%; height:10px; margin-top:-1px;">
                                            <path d="M0,0 L300,0 L300,6 Q150,16 0,6 Z" class="theme-primary-fill" />
                                            <path d="M0,6 Q150,16 300,6 L300,9 Q150,19 0,9 Z" class="theme-accent-fill" />
                                        </svg>
                                    </div>
                                    <!-- Body -->
                                    <div class="px-2 d-flex align-items-center justify-content-between">
                                        <div class="text-center" style="width: 32%;">
                                            <img class="mini-photo" src="{{ asset('assets/mock_student_girl.jpg') }}" width="42" height="42" style="border-radius: 50%; border: 2px solid var(--card-accent);" alt="Photo">
                                            <div style="font-size: 5px; font-weight: 700; color: var(--card-primary);">GR-2041</div>
                                        </div>
                                        <div style="width: 65%;">
                                            <div class="mini-student-name theme-primary-color">CLARA OSWALD</div>
                                            <span class="mini-badge theme-accent-bg text-white">GRADE 11-B</span>
                                            <div style="background: #F1F5F9; border-radius: 3px; padding: 2px 4px; margin-top: 2px;">
                                                <div class="mini-field-row"><span class="mini-field-label">Roll:</span><span class="mini-field-val">#08</span></div>
                                                <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">B+</span></div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Wave Footer -->
                                    <div>
                                        <svg viewBox="0 0 300 12" style="display:block; width:100%; height:7px;">
                                            <path d="M0,8 Q150,0 300,8 L300,12 L0,12 Z" class="theme-primary-fill" />
                                        </svg>
                                        <div class="mini-card-ftr theme-primary-bg" style="border-top: 1px solid var(--card-accent);">
                                            <span>Fluid Horizontal Wave</span>
                                            <span>2024-2025</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">H2. Fluid Wave Horizontal</div>
                                <small class="text-muted" style="font-size: 10.5px;">Curved wave header/footer & round badge</small>
                            </div>
                        </div>

                        <!-- H3: ANGLED DIAGONAL HORIZONTAL -->
                        <div class="col mb-3">
                            <div class="template-card" onclick="selectDesign('h_diagonal', this)">
                                <div class="template-check-badge"><i class="fa fa-check"></i></div>
                                <div class="mini-card-preview mini-card-horizontal">
                                    <div class="d-flex h-100">
                                        <!-- Angled Left Column -->
                                        <div class="theme-primary-bg p-2 text-center text-white" style="width: 36%;">
                                            <img class="mini-photo" src="{{ asset('assets/mock_student_boy.jpg') }}" width="42" height="48" style="border: 2px solid var(--card-accent); border-radius: 4px;" alt="Photo">
                                            <div style="font-size: 5px; color: var(--card-accent); font-weight: 700; margin-top: 2px;">ID: 5542</div>
                                        </div>
                                        <!-- Right Fields -->
                                        <div class="p-2 d-flex flex-column justify-content-between" style="width: 64%;">
                                            <div>
                                                <div class="mini-school-name theme-primary-color" style="font-size: 7px;">{{ $schoolTitle }}</div>
                                                <div class="mini-student-name theme-primary-color mt-1">LUCAS VANCE</div>
                                                <div style="font-size: 5px; color: var(--card-accent); font-weight: 800;">STUDENT PASS</div>
                                            </div>
                                            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 3px; padding: 2px 4px;">
                                                <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Grade 10</span></div>
                                                <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">AB+</span></div>
                                            </div>
                                            <div class="mini-card-ftr theme-primary-bg p-1" style="border-radius: 3px;">
                                                <span>Angled Horizontal</span>
                                                <span>Session 2025</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="font-weight-bold small text-dark mt-1">H3. Angled Diagonal Horizontal</div>
                                <small class="text-muted" style="font-size: 10.5px;">Sleek corporate badge with diagonal block</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. STUDENT ROSTER & SELECTION TABLE -->
    <div class="card shadow-sm border-0" style="border-radius: 14px;">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="card-title font-weight-bold text-dark mb-0">
                        <i class="fa fa-list-alt text-primary mr-2"></i>{{ __('Step 2: Select Students & Verify Photos') }}
                    </h5>
                    <small class="text-muted">{{ __('Existing photos are automatically used. Click "Capture / Edit" on any student to update via live webcam or upload.') }}</small>
                </div>
            </div>

            <!-- Filter Toolbar -->
            <div class="row" id="toolbar">
                <div class="form-group col-sm-12 col-md-4">
                    <label class="filter-menu">{{ __('Class Section') }} <span class="text-danger">*</span></label>
                    <select name="filter_class_section_id" id="filter_class_section_id" class="form-control">
                        <option value="">{{ __('select_class_section') }}</option>
                        @foreach ($class_sections as $class_section)
                            <option value="{{ $class_section->id }}">{{ $class_section->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-sm-12 col-md-4">
                    <label class="filter-menu">{{ __('Session Year') }} <span class="text-danger">*</span></label>
                    <select name="filter_session_year_id" id="filter_session_year_id" class="form-control">
                        @foreach ($sessionYears as $sessionYear)
                            <option value="{{ $sessionYear->id }}" {{ $sessionYear->default == 1 ? "selected" : "" }}>{{ $sessionYear->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="row mt-2">
                <div class="col-12">
                    <table aria-describedby="mydesc" class='table' id='table_list'
                           data-toggle="table" data-url="{{ route('students.show',[1]) }}" data-click-to-select="true"
                           data-side-pagination="server" data-pagination="true"
                           data-page-list="[10, 20, 50, 100, 200]" data-search="true"
                           data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                           data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="id"
                           data-sort-order="desc" data-maintain-selected="true" data-query-params="studentDetailsQueryParams"
                           data-check-on-init="true" data-escape="false" data-response-handler="responseHandler">
                        <thead>
                        <tr>
                            <th data-field="state" data-checkbox="true"></th>
                            <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{ __('id') }}</th>
                            <th scope="col" data-field="no">{{ __('no.') }}</th>
                            <th scope="col" data-field="user.id" data-visible="false">{{ __('User Id') }}</th>
                            <th scope="col" data-field="user.full_name">{{ __('name') }}</th>
                            <th scope="col" data-field="user.image" data-formatter="studentIdCardPhotoFormatter">{{ __('Photo Status & Action') }}</th>
                            <th scope="col" data-field="class_section.full_name">{{ __('class_section') }}</th>
                            <th scope="col" data-field="admission_no">{{ __('Gr Number') }}</th>
                            <th scope="col" data-field="roll_number">{{ __('roll_no') }}</th>
                            <th scope="col" data-field="user.dob">{{ __('dob') }}</th>
                            <th scope="col" data-field="guardian.full_name">{{ __('Guardian') }}</th>
                            <th scope="col" data-field="guardian.mobile">{{ __('Guardian Mobile') }}</th>
                        </tr>
                        </thead>
                    </table>
                </div>
            </div>

            <!-- 3. PRINT LAYOUT & FINISHING OPTIONS -->
            <div class="card bg-light border-0 mt-4 mb-3 p-3" style="border-radius: 12px;">
                <div class="row align-items-center">
                    <div class="col-lg-7">
                        <label class="font-weight-bold text-dark mb-1 d-flex align-items-center">
                            <i class="fa fa-print text-primary mr-2"></i> {{ __('Step 3: Choose Print Layout & Paper Format') }}
                        </label>
                        <div class="text-muted small mb-2">
                            {{ __('Choose single cards for thermal/plastic PVC card printers, or combined multi-card aligned sheets for standard A4 or A3 printers.') }}
                        </div>
                        <div class="btn-group btn-group-toggle d-flex flex-wrap" data-toggle="buttons">
                            <label class="btn btn-outline-primary active flex-fill py-2 px-3 text-left">
                                <input type="radio" name="print_format_radio" value="single" checked onchange="setPrintFormat('single')">
                                <div class="font-weight-bold"><i class="fa fa-id-badge mr-1"></i> {{ __('Single Card (PVC / CR-80)') }}</div>
                                <div class="small text-muted">{{ __('1 card per page (Thermal / Plastic PVC)') }}</div>
                            </label>
                            <label class="btn btn-outline-primary flex-fill py-2 px-3 text-left">
                                <input type="radio" name="print_format_radio" value="a4" onchange="setPrintFormat('a4')">
                                <div class="font-weight-bold"><i class="fa fa-file-text-o mr-1"></i> {{ __('Combined A4 Sheet') }}</div>
                                <div class="small text-muted">{{ __('8–9 cards/page aligned with cut guides') }}</div>
                            </label>
                            <label class="btn btn-outline-primary flex-fill py-2 px-3 text-left">
                                <input type="radio" name="print_format_radio" value="a3" onchange="setPrintFormat('a3')">
                                <div class="font-weight-bold"><i class="fa fa-files-o mr-1"></i> {{ __('Combined A3 Sheet') }}</div>
                                <div class="small text-muted">{{ __('16–18 cards/page aligned with cut guides') }}</div>
                            </label>
                        </div>
                    </div>
                    <div class="col-lg-5 mt-3 mt-lg-0 border-left-lg pl-lg-4">
                        <label class="font-weight-bold text-dark mb-1 small">
                            <i class="fa fa-scissors text-secondary mr-1"></i> {{ __('Finishing & Trimming Options') }}
                        </label>
                        <div class="custom-control custom-checkbox mt-2">
                            <input type="checkbox" class="custom-control-input" id="toggleCutGuides" checked onchange="setCutGuides(this.checked)">
                            <label class="custom-control-label font-weight-bold small text-dark" for="toggleCutGuides">
                                {{ __('Dashed Cutting Guides & Crop Marks') }}
                            </label>
                        </div>
                        <div class="text-muted small mt-1">
                            {{ __('Renders dashed cutting lines to easily slice cards with a guillotine or paper trimmer.') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Form -->
            <div class="pt-2 d-flex align-items-center justify-content-between">
                <div class="text-muted small">
                    <span id="selectedCountBadge" class="badge badge-primary px-3 py-2">0 students selected</span>
                </div>
                <form action="{{ url('students/generate-id-card') }}" target="_blank" method="post" id="idCardForm">
                    @csrf
                    <textarea id="user_id" name="user_id" style="display: none"></textarea>
                    <input type="hidden" name="template_design" id="selected_template_design" value="wave">
                    <input type="hidden" name="template_id" id="selected_template_id" value="wave">
                    <input type="hidden" name="primary_color" id="selected_primary_color" value="#1E293B">
                    <input type="hidden" name="accent_color" id="selected_accent_color" value="#06B6D4">
                    <input type="hidden" name="print_format" id="selected_print_format" value="single">
                    <input type="hidden" name="show_cut_guides" id="selected_show_cut_guides" value="1">
                    <button type="submit" class="btn btn-gradient-primary btn-lg px-4" id="btnGenerateIdCard">
                        <i class="fa fa-print mr-2"></i> {{ __('Generate ID Cards') }}
                    </button>
                </form>
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
                    <canvas id="capturedCanvas" width="300" height="380"></canvas>
                    <div class="badge badge-success mt-2 py-1 px-3">
                        <i class="fa fa-check mr-1"></i> {{ __('Snapshot Captured (3:4 ID Ratio)') }}
                    </div>
                </div>

                <!-- Camera Controls -->
                <div id="cameraActionButtons" class="d-flex align-items-center justify-content-center gap-2 mb-2">
                    <button type="button" class="btn btn-primary px-4" id="btnSnapPhoto" onclick="captureSnapshot()">
                        <i class="fa fa-camera mr-1"></i> {{ __('Take Snapshot') }}
                    </button>
                </div>

                <div id="canvasActionButtons" class="d-none d-flex align-items-center justify-content-center gap-2 mb-2">
                    <button type="button" class="btn btn-outline-secondary mr-2" onclick="retakeSnapshot()">
                        <i class="fa fa-refresh mr-1"></i> {{ __('Retake') }}
                    </button>
                    <button type="button" class="btn btn-gradient-success px-4" id="btnSavePhoto" onclick="saveCapturedPhoto()">
                        <i class="fa fa-save mr-1"></i> {{ __('Save & Apply Photo') }}
                    </button>
                </div>

                <!-- Fallback File Upload -->
                <div class="pt-2 border-top mt-3">
                    <label class="small text-muted mb-1">{{ __('Or upload image file from device:') }}</label>
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

    // Structural Design Picker
    function selectDesign(designKey, element) {
        $('.template-card').removeClass('active');
        $(element).addClass('active');
        $('#selected_template_design').val(designKey);
        $('#selected_template_id').val(designKey);
    }

    // Color Customizer Reactivity
    function applyPalette(primary, accent, pillElement) {
        $('.palette-pill').removeClass('active');
        if (pillElement) {
            $(pillElement).addClass('active');
        }

        updateLiveColors(primary, accent);
    }

    function onCustomColorChange() {
        var primary = $('#primaryColorPicker').val();
        var accent = $('#accentColorPicker').val();
        $('.palette-pill').removeClass('active');
        updateLiveColors(primary, accent);
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

    function resetDefaultColors() {
        var defaultPrimary = '#1E293B';
        var defaultAccent = '#06B6D4';
        $('.palette-pill').first().click();
    }

    function updateLiveColors(primary, accent) {
        // Update CSS variables on document root
        document.documentElement.style.setProperty('--card-primary', primary);
        document.documentElement.style.setProperty('--card-accent', accent);

        // Update inputs
        $('#primaryColorPicker').val(primary);
        $('#accentColorPicker').val(accent);
        $('#primaryHexInput').val(primary.toUpperCase());
        $('#accentHexInput').val(accent.toUpperCase());

        // Update hidden form inputs
        $('#selected_primary_color').val(primary);
        $('#selected_accent_color').val(accent);
    }

    // Print Format & Cut Guides
    function setPrintFormat(val) {
        $('#selected_print_format').val(val);
    }

    function setCutGuides(checked) {
        $('#selected_show_cut_guides').val(checked ? 1 : 0);
    }

    // Form Submission Validation
    $('#idCardForm').on('submit', function(e) {
        if (!user_list || user_list.length === 0) {
            e.preventDefault();
            alert('Please select at least one student from the table before generating ID cards.');
            return false;
        }
    });

    // Photo Status & Action Formatter for Bootstrap Table
    function studentIdCardPhotoFormatter(value, row) {
        const hasPhoto = (value && value !== '' && !value.includes('dummy_logo'));
        const photoUrl = hasPhoto ? value : "{{ asset('assets/dummy_logo.jpg') }}";
        const studentId = row.user ? row.user.id : row.id;
        const studentName = row.user ? row.user.full_name : (row.first_name + ' ' + row.last_name);

        let html = `
            <div class="d-flex align-items-center gap-2" id="photoRowUser_${studentId}">
                <img src="${photoUrl}" class="rounded border" style="width: 42px; height: 50px; object-fit: cover;" id="imgPreview_${studentId}" alt="Student">
                <div>
                    <div>
                        ${hasPhoto ? '<span class="badge badge-success" style="font-size:10px;"><i class="fa fa-check"></i> Photo Ready</span>' : '<span class="badge badge-warning" style="font-size:10px;">No Photo</span>'}
                    </div>
                    <button type="button" class="btn btn-xs btn-outline-primary mt-1" onclick="openPhotoCaptureModal(${studentId}, '${escapeQuotes(studentName)}', '${photoUrl}')">
                        <i class="fa fa-camera"></i> Capture / Edit
                    </button>
                </div>
            </div>
        `;
        return html;
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
                $('#selectedCountBadge').text(`${user_list.length} students selected`);
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

        $('#btnSavePhoto').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

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
                $(`#photoRowUser_${userId} .badge`).replaceWith('<span class="badge badge-success" style="font-size:10px;"><i class="fa fa-check"></i> Photo Ready</span>');
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
                    $(`#photoRowUser_${userId} .badge`).replaceWith('<span class="badge badge-success" style="font-size:10px;"><i class="fa fa-check"></i> Photo Ready</span>');
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
