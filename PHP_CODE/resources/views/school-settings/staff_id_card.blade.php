{{-- =========================================================================
     STAFF ID CARD SETTINGS - MODERNIZED & USER FRIENDLY
     Includes Staff Brand Colors, Photo Shape, Physical Dimensions,
     Background Uploader, and Field Toggles
     ========================================================================= --}}

<!-- 1. STAFF BRAND COLORS & PALETTE -->
<div class="card mb-4 border" style="border-radius: 12px; border-color: #E2E8F0 !important;">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="font-weight-bold text-dark mb-0">
            <i class="fa fa-paint-brush text-primary mr-2"></i>{{ __('Staff Identity Badge Brand Colors') }}
        </h6>
        <small class="text-muted">{{ __('Configure header and accent color styling for teacher and faculty ID badges.') }}</small>
    </div>
    <div class="card-body p-3">
        <!-- Hidden Inputs for Submission -->
        <input type="hidden" name="staff_template_design" id="staff_selected_template_design" value="{{ $settings['staff_template_design'] ?? 'modern_split' }}">
        <input type="hidden" name="staff_layout_type" id="staff_layout_type" value="{{ $settings['staff_layout_type'] ?? 'vertical' }}">

        <div class="row align-items-center">
            <!-- Palette Presets for Staff -->
            <div class="col-xl-6 col-lg-12 mb-3 mb-xl-0">
                <span class="font-weight-bold text-dark small d-block mb-2">
                    <i class="fa fa-magic text-primary mr-1"></i> {{ __('One-Click Recommended Themes:') }}
                </span>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="palette-pill active" onclick="applyStaffPalette('#1E293B', '#2563EB', this)">
                        <span class="palette-dot" style="background: linear-gradient(135deg, #1E293B 50%, #2563EB 50%);"></span>
                        <span>Slate & Royal</span>
                    </button>
                    <button type="button" class="palette-pill" onclick="applyStaffPalette('#0F172A', '#06B6D4', this)">
                        <span class="palette-dot" style="background: linear-gradient(135deg, #0F172A 50%, #06B6D4 50%);"></span>
                        <span>Obsidian Cyan</span>
                    </button>
                    <button type="button" class="palette-pill" onclick="applyStaffPalette('#1E3A8A', '#3B82F6', this)">
                        <span class="palette-dot" style="background: linear-gradient(135deg, #1E3A8A 50%, #3B82F6 50%);"></span>
                        <span>Deep Navy</span>
                    </button>
                    <button type="button" class="palette-pill" onclick="applyStaffPalette('#065F46', '#10B981', this)">
                        <span class="palette-dot" style="background: linear-gradient(135deg, #065F46 50%, #10B981 50%);"></span>
                        <span>Forest Green</span>
                    </button>
                    <button type="button" class="palette-pill" onclick="applyStaffPalette('#312E81', '#8B5CF6', this)">
                        <span class="palette-dot" style="background: linear-gradient(135deg, #312E81 50%, #8B5CF6 50%);"></span>
                        <span>Indigo Violet</span>
                    </button>
                </div>
            </div>

            <!-- Custom HTML5 Color Inputs for Staff -->
            <div class="col-xl-6 col-lg-12 border-left-xl pl-xl-3">
                <span class="font-weight-bold text-dark small d-block mb-2">
                    <i class="fa fa-sliders text-primary mr-1"></i> {{ __('Custom Staff Colors:') }}
                </span>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <!-- Staff Header / Primary Color -->
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" id="staffPrimaryColorPicker" class="color-picker-input"
                               value="{{ $settings['staff_primary_color'] ?? $settings['staff_header_color'] ?? '#1E293B' }}"
                               onchange="onStaffColorChange()" title="Staff Header Color">
                        <div>
                            <label class="small text-muted font-weight-bold d-block mb-0" style="font-size: 10px;">HEADER COLOR</label>
                            <input type="text" name="staff_header_color" id="staff_header_color"
                                   class="form-control form-control-sm text-uppercase font-weight-bold px-2 py-0"
                                   style="width: 82px; height: 26px; font-size: 11px;"
                                   value="{{ $settings['staff_primary_color'] ?? $settings['staff_header_color'] ?? '#1E293B' }}"
                                   onchange="onStaffHexInputChange('header', this.value)" required>
                            <input type="hidden" name="staff_primary_color" id="staff_primary_color" value="{{ $settings['staff_primary_color'] ?? $settings['staff_header_color'] ?? '#1E293B' }}">
                        </div>
                    </div>

                    <!-- Staff Footer / Accent Color -->
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" id="staffAccentColorPicker" class="color-picker-input"
                               value="{{ $settings['staff_accent_color'] ?? $settings['staff_footer_color'] ?? '#2563EB' }}"
                               onchange="onStaffColorChange()" title="Staff Footer Color">
                        <div>
                            <label class="small text-muted font-weight-bold d-block mb-0" style="font-size: 10px;">FOOTER COLOR</label>
                            <input type="text" name="staff_footer_color" id="staff_footer_color"
                                   class="form-control form-control-sm text-uppercase font-weight-bold px-2 py-0"
                                   style="width: 82px; height: 26px; font-size: 11px;"
                                   value="{{ $settings['staff_accent_color'] ?? $settings['staff_footer_color'] ?? '#2563EB' }}"
                                   onchange="onStaffHexInputChange('footer', this.value)" required>
                            <input type="hidden" name="staff_accent_color" id="staff_accent_color" value="{{ $settings['staff_accent_color'] ?? $settings['staff_footer_color'] ?? '#2563EB' }}">
                        </div>
                    </div>

                    <!-- Staff Text Color -->
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" id="staffTextColorPicker" class="color-picker-input"
                               value="{{ $settings['staff_header_footer_text_color'] ?? '#FFFFFF' }}"
                               onchange="onStaffTextColorChange(this.value)" title="Staff Header/Footer Text Color">
                        <div>
                            <label class="small text-muted font-weight-bold d-block mb-0" style="font-size: 10px;">TEXT COLOR</label>
                            <input type="text" name="staff_header_footer_text_color" id="staff_header_footer_text_color"
                                   class="form-control form-control-sm text-uppercase font-weight-bold px-2 py-0"
                                   style="width: 82px; height: 26px; font-size: 11px;"
                                   value="{{ $settings['staff_header_footer_text_color'] ?? '#FFFFFF' }}"
                                   onchange="onStaffTextColorChange(this.value)" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. STAFF PHOTO STYLE, ORIENTATION & PHYSICAL DIMENSIONS -->
<div class="card mb-4 border" style="border-radius: 12px; border-color: #E2E8F0 !important;">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="font-weight-bold text-dark mb-0">
            <i class="fa fa-sliders text-primary mr-2"></i>{{ __('Orientation, Photo Shape & Dimensions') }}
        </h6>
        <small class="text-muted">{{ __('Configure card orientation, profile photo geometry, and physical print size for staff.') }}</small>
    </div>
    <div class="card-body p-3">
        <div class="row">
            <!-- Orientation (Vertical vs Horizontal) -->
            <div class="col-md-3 mb-3">
                <label class="font-weight-bold text-dark small mb-2 d-block">
                    {{ __('Layout Orientation') }} <span class="text-danger">*</span>
                </label>
                <div class="d-flex gap-2">
                    <label class="selection-pill flex-fill @if(($settings['staff_layout_type'] ?? 'vertical') == 'vertical') active @endif" style="cursor: pointer;">
                        <input type="radio" class="d-none" name="staff_layout_type" value="vertical"
                               @if(($settings['staff_layout_type'] ?? 'vertical') == 'vertical') checked @endif
                               onchange="setSelectionPill(this)">
                        <div class="text-center py-2 px-3 border rounded">
                            <i class="fa fa-mobile fa-lg text-primary d-block mb-1"></i>
                            <span class="font-weight-bold small">{{ __('Vertical') }}</span>
                        </div>
                    </label>
                    <label class="selection-pill flex-fill @if(($settings['staff_layout_type'] ?? '') == 'horizontal') active @endif" style="cursor: pointer;">
                        <input type="radio" class="d-none" name="staff_layout_type" value="horizontal"
                               @if(($settings['staff_layout_type'] ?? '') == 'horizontal') checked @endif
                               onchange="setSelectionPill(this)">
                        <div class="text-center py-2 px-3 border rounded">
                            <i class="fa fa-credit-card fa-lg text-primary d-block mb-1"></i>
                            <span class="font-weight-bold small">{{ __('Horizontal') }}</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Profile Image Style (Round vs Square) -->
            <div class="col-md-3 mb-3">
                <label class="font-weight-bold text-dark small mb-2 d-block">
                    {{ __('Photo Shape') }} <span class="text-danger">*</span>
                </label>
                <div class="d-flex gap-2">
                    <label class="selection-pill flex-fill @if(($settings['staff_profile_image_style'] ?? 'round') == 'round') active @endif" style="cursor: pointer;">
                        <input type="radio" class="d-none" name="staff_profile_image_style" value="round"
                               @if(($settings['staff_profile_image_style'] ?? 'round') == 'round') checked @endif
                               onchange="setSelectionPill(this)">
                        <div class="text-center py-2 px-3 border rounded">
                            <i class="fa fa-circle-o fa-lg text-primary d-block mb-1"></i>
                            <span class="font-weight-bold small">{{ __('Round') }}</span>
                        </div>
                    </label>
                    <label class="selection-pill flex-fill @if(($settings['staff_profile_image_style'] ?? '') == 'squre') active @endif" style="cursor: pointer;">
                        <input type="radio" class="d-none" name="staff_profile_image_style" value="squre"
                               @if(($settings['staff_profile_image_style'] ?? '') == 'squre') checked @endif
                               onchange="setSelectionPill(this)">
                        <div class="text-center py-2 px-3 border rounded">
                            <i class="fa fa-square-o fa-lg text-primary d-block mb-1"></i>
                            <span class="font-weight-bold small">{{ __('Square') }}</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Page Width & Height -->
            <div class="col-md-3 mb-3">
                <label class="font-weight-bold text-dark small mb-2 d-block">
                    {{ __('Dimensions (mm)') }} <span class="text-danger">*</span>
                </label>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="flex-fill">
                        <small class="text-muted font-weight-bold d-block" style="font-size: 10px;">WIDTH</small>
                        <input type="number" step="0.1" name="staff_page_width" id="staff_page_width" class="form-control form-control-sm font-weight-bold"
                               value="{{ $settings['staff_page_width'] ?? 54 }}" required placeholder="54">
                    </div>
                    <span class="text-muted font-weight-bold mt-3">&times;</span>
                    <div class="flex-fill">
                        <small class="text-muted font-weight-bold d-block" style="font-size: 10px;">HEIGHT</small>
                        <input type="number" step="0.1" name="staff_page_height" id="staff_page_height" class="form-control form-control-sm font-weight-bold"
                               value="{{ $settings['staff_page_height'] ?? 86 }}" required placeholder="86">
                    </div>
                </div>
                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 10px;" onclick="setStaffDimensions(54, 86)">
                    {{ __('Standard CR-80 (54×86mm)') }}
                </button>
            </div>

            <!-- Staff Background Image -->
            <div class="col-md-3 mb-3">
                <label class="font-weight-bold text-dark small mb-2 d-block">
                    {{ __('Background Image') }} <small class="text-muted">({{ __('Optional') }})</small>
                </label>
                <input type="file" name="staff_background_image" accept="image/jpg,image/png,image/jpeg,image/svg" class="file-upload-default" />
                <div class="input-group">
                    <input type="text" class="form-control file-upload-info form-control-sm" disabled placeholder="{{ __('Upload background') }}" />
                    <span class="input-group-append">
                        <button class="file-upload-browse btn btn-sm btn-secondary" type="button">
                            <i class="fa fa-upload mr-1"></i>{{ __('Upload') }}
                        </button>
                    </span>
                </div>
                @if ($settings['staff_background_image'] ?? '')
                    <div id="staff_background" class="mt-2 d-flex align-items-center gap-2 p-1 border rounded bg-light">
                        <img src="{{ $settings['staff_background_image'] }}" class="rounded" style="height: 34px; max-width: 60px; object-fit: contain;" alt="Background">
                        <span class="small text-muted flex-fill">{{ __('Active') }}</span>
                        <a href="" data-type="staff_background" class="btn btn-xs btn-danger id-card-settings" title="Remove image">
                            <i class="fa fa-times"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- 3. VISIBLE STAFF FIELDS SELECTION -->
<div class="card mb-4 border" style="border-radius: 12px; border-color: #E2E8F0 !important;">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h6 class="font-weight-bold text-dark mb-0">
                <i class="fa fa-check-square-o text-primary mr-2"></i>{{ __('Visible Staff Information Fields') }}
            </h6>
            <small class="text-muted">{{ __('Select faculty and employee attributes to display on the staff identity badge.') }}</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs btn-outline-primary" onclick="toggleAllCheckboxes('#staffFieldsContainer', true)">
                {{ __('Select All') }}
            </button>
            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleAllCheckboxes('#staffFieldsContainer', false)">
                {{ __('Deselect All') }}
            </button>
        </div>
    </div>
    <div class="card-body p-3" id="staffFieldsContainer">
        @php
            $activeStaffFields = $settings['staff_id_card_fields'] ?? [];
            if (!is_array($activeStaffFields)) {
                $activeStaffFields = explode(',', $activeStaffFields);
            }
        @endphp

        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-3 g-2">
            <!-- Staff Name -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('name', $activeStaffFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="staff_id_card_fields[]" value="name" class="d-none field-toggle-input"
                           @if(in_array('name', $activeStaffFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-user mr-1 text-muted"></i> {{ __('Staff Full Name') }}</span>
                </label>
            </div>

            <!-- Role / Designation -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('role', $activeStaffFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="staff_id_card_fields[]" value="role" class="d-none field-toggle-input"
                           @if(in_array('role', $activeStaffFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-briefcase mr-1 text-muted"></i> {{ __('Role / Designation') }}</span>
                </label>
            </div>

            <!-- Email -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('email', $activeStaffFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="staff_id_card_fields[]" value="email" class="d-none field-toggle-input"
                           @if(in_array('email', $activeStaffFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-envelope mr-1 text-muted"></i> {{ __('Email Address') }}</span>
                </label>
            </div>

            <!-- Mobile -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('mobile', $activeStaffFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="staff_id_card_fields[]" value="mobile" class="d-none field-toggle-input"
                           @if(in_array('mobile', $activeStaffFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-phone mr-1 text-muted"></i> {{ __('Contact / Mobile') }}</span>
                </label>
            </div>

            <!-- Date of Birth -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('dob', $activeStaffFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="staff_id_card_fields[]" value="dob" class="d-none field-toggle-input"
                           @if(in_array('dob', $activeStaffFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-calendar mr-1 text-muted"></i> {{ __('Date of Birth') }}</span>
                </label>
            </div>

            <!-- Gender -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('gender', $activeStaffFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="staff_id_card_fields[]" value="gender" class="d-none field-toggle-input"
                           @if(in_array('gender', $activeStaffFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-venus-mars mr-1 text-muted"></i> {{ __('Gender') }}</span>
                </label>
            </div>
        </div>
    </div>
</div>
