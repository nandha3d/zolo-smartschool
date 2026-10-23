{{-- =========================================================================
     STUDENT ID CARD SETTINGS - MODERNIZED & USER FRIENDLY
     Includes Visual Archetype Gallery, Brand Palette Pickers, Live Preview,
     Photo Style, Standard Dimension Presets, and Card Field Toggles
     ========================================================================= --}}

<!-- 1. TEMPLATE ARCHETYPE SELECTION GALLERY -->
<div class="card mb-4 border" style="border-radius: 12px; border-color: #E2E8F0 !important;">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h6 class="font-weight-bold text-dark mb-0">
                <i class="fa fa-id-card text-primary mr-2"></i>{{ __('ID Card Design & Template Archetype') }}
            </h6>
            <small class="text-muted">{{ __('Select the structural blueprint for student identity badges.') }}</small>
        </div>
        <!-- Orientation Switcher -->
        <ul class="nav nav-pills mt-2 mt-sm-0" id="studentTemplateTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link font-weight-bold py-1 px-3 @if(($settings['layout_type'] ?? 'vertical') == 'vertical') active @endif"
                   id="student-vertical-tab" data-toggle="pill" href="#studentVerticalTemplates" role="tab" onclick="setLayoutType('vertical')">
                    <i class="fa fa-mobile mr-1"></i> {{ __('Vertical Badges (8 Designs)') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold py-1 px-3 @if(($settings['layout_type'] ?? '') == 'horizontal') active @endif"
                   id="student-horizontal-tab" data-toggle="pill" href="#studentHorizontalTemplates" role="tab" onclick="setLayoutType('horizontal')">
                    <i class="fa fa-credit-card mr-1"></i> {{ __('Horizontal Badges (3 Designs)') }}
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body p-3">
        <!-- Hidden Inputs for Submission -->
        <input type="hidden" name="template_design" id="student_selected_template_design" value="{{ $settings['template_design'] ?? 'wave' }}">
        <input type="hidden" name="layout_type" id="student_layout_type" value="{{ $settings['layout_type'] ?? 'vertical' }}">

        <div class="tab-content" id="studentTemplateTabsContent">
            <!-- VERTICAL TEMPLATES (8 ARCHETYPES) -->
            <div class="tab-pane fade @if(($settings['layout_type'] ?? 'vertical') == 'vertical') show active @endif" id="studentVerticalTemplates" role="tabpanel">
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
                    @php
                        $schoolTitle = !empty($settings['school_name']) ? strtoupper($settings['school_name']) : 'ZOLO SMART SCHOOL';
                        $currDesign = $settings['template_design'] ?? 'wave';
                    @endphp

                    <!-- 1. CURVED WAVE ARC -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'wave') active @endif" onclick="selectStudentDesign('wave', this, 'vertical')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-vertical">
                                <div>
                                    <div class="p-1 text-center theme-primary-bg">
                                        <div class="mini-school-name">{{ $schoolTitle }}</div>
                                    </div>
                                    <svg viewBox="0 0 200 24" style="display:block; width:100%; height:14px; margin-top:-1px;">
                                        <path d="M0,0 L200,0 L200,8 Q100,24 0,8 Z" class="theme-primary-fill" />
                                        <path d="M0,8 Q100,24 200,8 L200,11 Q100,27 0,11 Z" class="theme-accent-fill" />
                                    </svg>
                                </div>
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
                                <div>
                                    <svg viewBox="0 0 200 12" style="display:block; width:100%; height:8px;">
                                        <path d="M0,6 Q100,0 200,6 L200,12 L0,12 Z" class="theme-primary-fill" />
                                    </svg>
                                    <div class="mini-card-ftr theme-primary-bg" style="border-top: 1px solid var(--card-accent);">
                                        <span>Wave Arc</span>
                                        <span>Valid 2026</span>
                                    </div>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">1. Curved Wave Arc</div>
                            <small class="text-muted" style="font-size: 10.5px;">Curved SVG crest & circular portrait</small>
                        </div>
                    </div>

                    <!-- 2. DIAMOND / RHOMBUS -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'diamond') active @endif" onclick="selectStudentDesign('diamond', this, 'vertical')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-vertical">
                                <div>
                                    <div class="p-1 text-center theme-primary-bg">
                                        <div class="mini-school-name">{{ $schoolTitle }}</div>
                                    </div>
                                    <svg viewBox="0 0 200 20" style="display:block; width:100%; height:12px; margin-top:-1px;">
                                        <polygon points="0,0 200,0 100,20" class="theme-primary-fill" />
                                    </svg>
                                </div>
                                <div style="padding: 0 6px; text-align: center; margin-top: -6px;">
                                    <div style="width: 44px; height: 44px; margin: 0 auto; transform: rotate(45deg); overflow: hidden; border: 2px solid var(--card-accent); border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                                        <img src="{{ asset('assets/mock_student_girl.jpg') }}" width="62" height="62" style="transform: rotate(-45deg) translate(-8px, -8px); display:block;" alt="Photo">
                                    </div>
                                    <div class="mini-student-name theme-primary-color mt-1">SARAH CONNOR</div>
                                    <span class="mini-badge theme-primary-bg text-white">STUDENT BADGE</span>
                                    <div style="background: rgba(241, 245, 249, 0.8); border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                        <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Grade 9</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Roll:</span><span class="mini-field-val">#08</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">DOB:</span><span class="mini-field-val">12 May 2011</span></div>
                                    </div>
                                </div>
                                <div class="mini-card-ftr theme-primary-bg">
                                    <span>Diamond</span>
                                    <span>Session 2026</span>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">2. Diamond Crest</div>
                            <small class="text-muted" style="font-size: 10.5px;">Rhombus framed avatar & angled badge</small>
                        </div>
                    </div>

                    <!-- 3. MODERN SPLIT -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'split') active @endif" onclick="selectStudentDesign('split', this, 'vertical')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-vertical">
                                <div class="theme-primary-bg p-2 text-center" style="border-bottom: 3px solid var(--card-accent);">
                                    <div class="mini-school-name">{{ $schoolTitle }}</div>
                                    <div style="font-size: 6px; color: rgba(255,255,255,0.85); font-weight: 600;">STUDENT IDENTITY PASS</div>
                                </div>
                                <div style="padding: 0 6px; text-align: center; margin-top: -12px;">
                                    <img class="mini-photo" src="{{ asset('assets/mock_student_boy.jpg') }}" width="46" height="46" style="border-radius: 8px; border: 2px solid #ffffff;" alt="Photo">
                                    <div class="mini-student-name theme-primary-color mt-1">ETHAN HUNT</div>
                                    <span class="mini-badge theme-accent-bg text-white">STUDENT</span>
                                    <div style="background: rgba(241, 245, 249, 0.8); border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                        <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">11 - B</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Admission:</span><span class="mini-field-val">GR-8842</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Emergency:</span><span class="mini-field-val">+1 987 654</span></div>
                                    </div>
                                </div>
                                <div class="mini-card-ftr theme-primary-bg">
                                    <span>Corporate Split</span>
                                    <span>2026</span>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">3. Modern Split</div>
                            <small class="text-muted" style="font-size: 10.5px;">Full-width header block with floating badge</small>
                        </div>
                    </div>

                    <!-- 4. MINIMAL CORPORATE -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'minimal') active @endif" onclick="selectStudentDesign('minimal', this, 'vertical')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-vertical" style="border-top: 4px solid var(--card-primary);">
                                <div class="p-1 px-2 d-flex align-items-center justify-content-between border-bottom">
                                    <div class="mini-school-name theme-primary-color" style="font-size: 6.5px;">{{ $schoolTitle }}</div>
                                    <div style="width: 12px; height: 12px; background: var(--card-accent); border-radius: 2px;"></div>
                                </div>
                                <div style="padding: 0 6px; text-align: center;">
                                    <img class="mini-photo mt-1" src="{{ asset('assets/mock_student_girl.jpg') }}" width="42" height="42" style="border-radius: 4px; border: 1px solid #CBD5E1;" alt="Photo">
                                    <div class="mini-student-name theme-primary-color mt-1">EMILY WATSON</div>
                                    <div style="font-size: 5px; color: #64748B; font-weight: 700;">ID: STU-2026-092</div>
                                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                        <div class="mini-field-row"><span class="mini-field-label">Grade:</span><span class="mini-field-val">Grade 12</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Section:</span><span class="mini-field-val">Science A</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">B+</span></div>
                                    </div>
                                </div>
                                <div style="padding: 2px 4px; border-top: 1px dashed #CBD5E1; text-align: center;">
                                    <div style="font-family: monospace; font-size: 6px; letter-spacing: 2px; color: var(--card-primary);">|||| ||| |||||||</div>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">4. Tech & Barcode</div>
                            <small class="text-muted" style="font-size: 10.5px;">Clean technical layout with barcode crest</small>
                        </div>
                    </div>

                    <!-- 5. RIBBON ACCENT -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'ribbon') active @endif" onclick="selectStudentDesign('ribbon', this, 'vertical')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-vertical">
                                <div style="position: relative;">
                                    <div class="p-1 text-center theme-primary-bg">
                                        <div class="mini-school-name">{{ $schoolTitle }}</div>
                                    </div>
                                    <div class="theme-accent-bg text-white text-center font-weight-bold" style="font-size: 5.5px; padding: 1px 0; letter-spacing: 1px;">
                                        OFFICIAL PASS
                                    </div>
                                </div>
                                <div style="padding: 0 6px; text-align: center;">
                                    <img class="mini-photo mt-1" src="{{ asset('assets/mock_student_boy.jpg') }}" width="44" height="44" style="border-radius: 6px; border: 2px solid var(--card-primary);" alt="Photo">
                                    <div class="mini-student-name theme-primary-color mt-1">LUCAS SCOTT</div>
                                    <span class="mini-badge theme-primary-bg text-white">STUDENT</span>
                                    <div style="background: rgba(241, 245, 249, 0.8); border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                        <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Class 8</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Guardian:</span><span class="mini-field-val">Dan Scott</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Phone:</span><span class="mini-field-val">555-0192</span></div>
                                    </div>
                                </div>
                                <div class="mini-card-ftr theme-primary-bg" style="border-top: 2px solid var(--card-accent);">
                                    <span>Ribbon Crest</span>
                                    <span>Valid 2026</span>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">5. Ribbon Crest</div>
                            <small class="text-muted" style="font-size: 10.5px;">Diagonal ribbon and school emblem</small>
                        </div>
                    </div>

                    <!-- 6. HEXAGON CREST -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'hexagon') active @endif" onclick="selectStudentDesign('hexagon', this, 'vertical')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-vertical" style="background: #F8FAFC;">
                                <div class="theme-primary-bg p-1 text-center">
                                    <div class="mini-school-name">{{ $schoolTitle }}</div>
                                </div>
                                <div style="padding: 0 6px; text-align: center; margin-top: 2px;">
                                    <div style="width: 46px; height: 46px; margin: 0 auto; clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%); background: var(--card-accent); padding: 2px;">
                                        <img src="{{ asset('assets/mock_student_girl.jpg') }}" width="42" height="42" style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%); object-fit: cover;" alt="Photo">
                                    </div>
                                    <div class="mini-student-name theme-primary-color mt-1">CHLOE DECKER</div>
                                    <span class="mini-badge theme-accent-bg text-white">STEM ACADEMY</span>
                                    <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                        <div class="mini-field-row"><span class="mini-field-label">Stream:</span><span class="mini-field-val">Robotics</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Roll:</span><span class="mini-field-val">#42</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Year:</span><span class="mini-field-val">2026</span></div>
                                    </div>
                                </div>
                                <div class="mini-card-ftr theme-primary-bg">
                                    <span>Hexagon</span>
                                    <span>Session 2026</span>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">6. Hexagon Polygon</div>
                            <small class="text-muted" style="font-size: 10.5px;">Futuristic polygon identity</small>
                        </div>
                    </div>

                    <!-- 7. DIAGONAL STRIPE -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'stripe') active @endif" onclick="selectStudentDesign('stripe', this, 'vertical')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-vertical" style="background: linear-gradient(135deg, #ffffff 82%, var(--card-accent) 82%, var(--card-accent) 88%, var(--card-primary) 88%);">
                                <div>
                                    <div class="p-1 text-center theme-primary-bg">
                                        <div class="mini-school-name">{{ $schoolTitle }}</div>
                                    </div>
                                    <div style="height: 3px; background: var(--card-accent);"></div>
                                </div>
                                <div style="padding: 0 6px; text-align: center;">
                                    <img class="mini-photo mt-1" src="{{ asset('assets/mock_student_boy.jpg') }}" width="44" height="44" style="border-radius: 50%; border: 2px solid var(--card-primary);" alt="Photo">
                                    <div class="mini-student-name theme-primary-color mt-1">JAMES HOLDEN</div>
                                    <span class="mini-badge theme-primary-bg text-white">STUDENT</span>
                                    <div style="background: rgba(241, 245, 249, 0.9); border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                        <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">10 - C</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Admission:</span><span class="mini-field-val">GR-5521</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">A+</span></div>
                                    </div>
                                </div>
                                <div class="mini-card-ftr theme-primary-bg">
                                    <span>Diagonal</span>
                                    <span>2026</span>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">7. Diagonal Stripe</div>
                            <small class="text-muted" style="font-size: 10.5px;">High energy diagonal slash theme</small>
                        </div>
                    </div>

                    <!-- 8. CLEAN MINIMALIST -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'clean') active @endif" onclick="selectStudentDesign('clean', this, 'vertical')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-vertical" style="border: 2px solid var(--card-primary);">
                                <div class="p-1 text-center border-bottom">
                                    <div class="mini-school-name theme-primary-color">{{ $schoolTitle }}</div>
                                    <div style="font-size: 5px; color: var(--card-accent); font-weight: 800; letter-spacing: 1px;">STUDENT CARD</div>
                                </div>
                                <div style="padding: 0 6px; text-align: center;">
                                    <img class="mini-photo mt-1" src="{{ asset('assets/mock_student_girl.jpg') }}" width="42" height="42" style="border-radius: 6px; border: 1px solid #CBD5E1;" alt="Photo">
                                    <div class="mini-student-name theme-primary-color mt-1">CLARA OSWALD</div>
                                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 4px; padding: 2px 4px; margin: 3px 0;">
                                        <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Grade 11</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Roll:</span><span class="mini-field-val">#19</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Emergency:</span><span class="mini-field-val">+1 234 567</span></div>
                                    </div>
                                </div>
                                <div class="mini-card-ftr theme-primary-bg">
                                    <span>Clean Classic</span>
                                    <span>2026</span>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">8. Clean Minimalist</div>
                            <small class="text-muted" style="font-size: 10.5px;">Modern typography & pill frame</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HORIZONTAL TEMPLATES (3 ARCHETYPES) -->
            <div class="tab-pane fade @if(($settings['layout_type'] ?? '') == 'horizontal') show active @endif" id="studentHorizontalTemplates" role="tabpanel">
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                    <!-- H1. SPLIT HORIZON -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'h_split') active @endif" onclick="selectStudentDesign('h_split', this, 'horizontal')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-horizontal">
                                <div class="theme-primary-bg p-1 px-2 d-flex align-items-center justify-content-between">
                                    <div class="mini-school-name">{{ $schoolTitle }}</div>
                                    <span class="mini-badge theme-accent-bg text-white">STUDENT</span>
                                </div>
                                <div class="d-flex align-items-center p-2 gap-2" style="flex: 1;">
                                    <img class="mini-photo" src="{{ asset('assets/mock_student_boy.jpg') }}" width="42" height="42" style="border-radius: 6px; border: 2px solid var(--card-accent);" alt="Photo">
                                    <div style="flex: 1;">
                                        <div class="mini-student-name theme-primary-color">MARCUS VANCE</div>
                                        <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Grade 10-A</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Roll:</span><span class="mini-field-val">#12 &bull; GR-402</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">DOB:</span><span class="mini-field-val">14 Jun 2010</span></div>
                                    </div>
                                </div>
                                <div class="mini-card-ftr theme-primary-bg">
                                    <span>Horizontal Split</span>
                                    <span>Session 2026</span>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">H1. Split Horizon Landscape</div>
                            <small class="text-muted" style="font-size: 10.5px;">Two-column: Photo left, Details right</small>
                        </div>
                    </div>

                    <!-- H2. EXECUTIVE CREST HORIZONTAL -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'h_executive') active @endif" onclick="selectStudentDesign('h_executive', this, 'horizontal')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-horizontal" style="border-top: 3px solid var(--card-accent); border-bottom: 3px solid var(--card-primary);">
                                <div class="p-1 px-2 d-flex align-items-center justify-content-between border-bottom">
                                    <div class="mini-school-name theme-primary-color">{{ $schoolTitle }}</div>
                                    <div style="font-size: 5px; color: #64748B; font-weight: 800;">VALID 2026</div>
                                </div>
                                <div class="d-flex align-items-center p-2 gap-2" style="flex: 1;">
                                    <img class="mini-photo" src="{{ asset('assets/mock_student_girl.jpg') }}" width="40" height="40" style="border-radius: 50%; border: 2px solid var(--card-primary);" alt="Photo">
                                    <div style="flex: 1;">
                                        <div class="mini-student-name theme-primary-color">ELENA ROSTOVA</div>
                                        <span class="mini-badge theme-primary-bg text-white mb-1">STUDENT</span>
                                        <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Grade 12-C</span></div>
                                        <div class="mini-field-row"><span class="mini-field-label">Emergency:</span><span class="mini-field-val">+1 888 234</span></div>
                                    </div>
                                </div>
                                <div class="mini-card-ftr theme-primary-bg">
                                    <span>Executive Crest</span>
                                    <span>Session 2026</span>
                                </div>
                            </div>
                            <div class="font-weight-bold small text-dark mt-1">H2. Executive Crest Horizontal</div>
                            <small class="text-muted" style="font-size: 10.5px;">Classic formal school credential</small>
                        </div>
                    </div>

                    <!-- H3. ANGLED HORIZONTAL -->
                    <div class="col mb-3">
                        <div class="template-card @if($currDesign == 'h_angled') active @endif" onclick="selectStudentDesign('h_angled', this, 'horizontal')">
                            <div class="template-check-badge"><i class="fa fa-check"></i></div>
                            <div class="mini-card-preview mini-card-horizontal" style="background: linear-gradient(115deg, var(--card-primary) 32%, #ffffff 32%);">
                                <div class="d-flex h-100">
                                    <div style="width: 30%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4px;">
                                        <img class="mini-photo" src="{{ asset('assets/mock_student_boy.jpg') }}" width="38" height="38" style="border-radius: 50%; border: 2px solid var(--card-accent);" alt="Photo">
                                        <div class="mini-school-name mt-1" style="font-size: 5px; text-align: center;">{{ $schoolTitle }}</div>
                                    </div>
                                    <div style="width: 70%; padding: 6px 8px; display: flex; flex-direction: column; justify-content: space-between;">
                                        <div>
                                            <div class="mini-student-name theme-primary-color">NATHAN DRAKE</div>
                                            <div style="font-size: 5px; color: var(--card-accent); font-weight: 800;">STUDENT PASS</div>
                                        </div>
                                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 3px; padding: 2px 4px;">
                                            <div class="mini-field-row"><span class="mini-field-label">Class:</span><span class="mini-field-val">Grade 10</span></div>
                                            <div class="mini-field-row"><span class="mini-field-label">Blood:</span><span class="mini-field-val text-danger">AB+</span></div>
                                        </div>
                                        <div class="mini-card-ftr theme-primary-bg p-1" style="border-radius: 3px;">
                                            <span>Angled Horizontal</span>
                                            <span>Session 2026</span>
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

<!-- 2. BRAND COLOR PALETTES & REAL-TIME INTERACTIVE CUSTOMIZER -->
<div class="card mb-4 border" style="border-radius: 12px; border-color: #E2E8F0 !important;">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="font-weight-bold text-dark mb-0">
            <i class="fa fa-paint-brush text-primary mr-2"></i>{{ __('Brand Theme Colors & Real-Time Palette') }}
        </h6>
        <small class="text-muted">{{ __('Select a harmonious curated color theme or fine-tune exact hex colors for your institution.') }}</small>
    </div>
    <div class="card-body p-3">
        <div class="row align-items-center">
            <!-- Palette Presets -->
            <div class="col-xl-7 col-lg-12 mb-3 mb-xl-0">
                <span class="font-weight-bold text-dark small d-block mb-2">
                    <i class="fa fa-magic text-primary mr-1"></i> {{ __('One-Click Recommended Palettes:') }}
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

            <!-- Custom HTML5 Color Inputs -->
            <div class="col-xl-5 col-lg-12 border-left-xl pl-xl-3">
                <span class="font-weight-bold text-dark small d-block mb-2">
                    <i class="fa fa-sliders text-primary mr-1"></i> {{ __('Custom Hex Color Pickers:') }}
                </span>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <!-- Primary Color (Header Color) -->
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" id="primaryColorPicker" class="color-picker-input"
                               value="{{ $settings['primary_color'] ?? $settings['header_color'] ?? '#1E293B' }}"
                               onchange="onCustomColorChange()" title="Primary Header Color">
                        <div>
                            <label class="small text-muted font-weight-bold d-block mb-0" style="font-size: 10px;">PRIMARY / HEADER</label>
                            <input type="text" name="header_color" id="header_color" class="form-control form-control-sm text-uppercase font-weight-bold px-2 py-0"
                                   style="width: 82px; height: 26px; font-size: 11px;"
                                   value="{{ $settings['primary_color'] ?? $settings['header_color'] ?? '#1E293B' }}"
                                   onchange="onHexInputChange('primary', this.value)" required>
                            <input type="hidden" name="primary_color" id="student_primary_color" value="{{ $settings['primary_color'] ?? $settings['header_color'] ?? '#1E293B' }}">
                        </div>
                    </div>

                    <!-- Accent Color (Footer Color) -->
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" id="accentColorPicker" class="color-picker-input"
                               value="{{ $settings['accent_color'] ?? $settings['footer_color'] ?? '#06B6D4' }}"
                               onchange="onCustomColorChange()" title="Accent Highlight Color">
                        <div>
                            <label class="small text-muted font-weight-bold d-block mb-0" style="font-size: 10px;">ACCENT / FOOTER</label>
                            <input type="text" name="footer_color" id="footer_color" class="form-control form-control-sm text-uppercase font-weight-bold px-2 py-0"
                                   style="width: 82px; height: 26px; font-size: 11px;"
                                   value="{{ $settings['accent_color'] ?? $settings['footer_color'] ?? '#06B6D4' }}"
                                   onchange="onHexInputChange('accent', this.value)" required>
                            <input type="hidden" name="accent_color" id="student_accent_color" value="{{ $settings['accent_color'] ?? $settings['footer_color'] ?? '#06B6D4' }}">
                        </div>
                    </div>

                    <!-- Header Footer Text Color -->
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" id="textColorPicker" class="color-picker-input"
                               value="{{ $settings['header_footer_text_color'] ?? '#FFFFFF' }}"
                               onchange="onTextColorChange(this.value)" title="Header & Footer Text Color">
                        <div>
                            <label class="small text-muted font-weight-bold d-block mb-0" style="font-size: 10px;">TEXT COLOR</label>
                            <input type="text" name="header_footer_text_color" id="header_footer_text_color"
                                   class="form-control form-control-sm text-uppercase font-weight-bold px-2 py-0"
                                   style="width: 82px; height: 26px; font-size: 11px;"
                                   value="{{ $settings['header_footer_text_color'] ?? '#FFFFFF' }}"
                                   onchange="onTextColorChange(this.value)" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. PHOTO STYLE & CARD PHYSICAL DIMENSIONS -->
<div class="card mb-4 border" style="border-radius: 12px; border-color: #E2E8F0 !important;">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="font-weight-bold text-dark mb-0">
            <i class="fa fa-sliders text-primary mr-2"></i>{{ __('Card Dimensions & Profile Photo Layout') }}
        </h6>
        <small class="text-muted">{{ __('Configure standard print size specifications and student portrait geometry.') }}</small>
    </div>
    <div class="card-body p-3">
        <div class="row">
            <!-- Profile Image Style (Round vs Square) -->
            <div class="col-md-4 mb-3">
                <label class="font-weight-bold text-dark small mb-2 d-block">
                    {{ __('Profile Photo Shape') }} <span class="text-danger">*</span>
                </label>
                <div class="d-flex gap-2">
                    <label class="selection-pill flex-fill @if(($settings['profile_image_style'] ?? 'round') == 'round') active @endif" style="cursor: pointer;">
                        <input type="radio" class="d-none" name="profile_image_style" value="round"
                               @if(($settings['profile_image_style'] ?? 'round') == 'round') checked @endif
                               onchange="setSelectionPill(this)">
                        <div class="text-center py-2 px-3 border rounded">
                            <i class="fa fa-circle-o fa-lg text-primary d-block mb-1"></i>
                            <span class="font-weight-bold small">{{ __('Round / Circular') }}</span>
                        </div>
                    </label>
                    <label class="selection-pill flex-fill @if(($settings['profile_image_style'] ?? '') == 'squre') active @endif" style="cursor: pointer;">
                        <input type="radio" class="d-none" name="profile_image_style" value="squre"
                               @if(($settings['profile_image_style'] ?? '') == 'squre') checked @endif
                               onchange="setSelectionPill(this)">
                        <div class="text-center py-2 px-3 border rounded">
                            <i class="fa fa-square-o fa-lg text-primary d-block mb-1"></i>
                            <span class="font-weight-bold small">{{ __('Square / Rect') }}</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Page Width & Height with Presets -->
            <div class="col-md-4 mb-3">
                <label class="font-weight-bold text-dark small mb-2 d-block">
                    {{ __('Card Dimensions (mm)') }} <span class="text-danger">*</span>
                </label>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="flex-fill">
                        <small class="text-muted font-weight-bold d-block" style="font-size: 10px;">WIDTH (MM)</small>
                        <input type="number" step="0.1" name="page_width" id="page_width" class="form-control form-control-sm font-weight-bold"
                               value="{{ $settings['page_width'] ?? 54 }}" required placeholder="54">
                    </div>
                    <span class="text-muted font-weight-bold mt-3">&times;</span>
                    <div class="flex-fill">
                        <small class="text-muted font-weight-bold d-block" style="font-size: 10px;">HEIGHT (MM)</small>
                        <input type="number" step="0.1" name="page_height" id="page_height" class="form-control form-control-sm font-weight-bold"
                               value="{{ $settings['page_height'] ?? 86 }}" required placeholder="86">
                    </div>
                </div>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 10px;" onclick="setDimensions(54, 86)">
                        {{ __('CR-80 PVC (54×86mm)') }}
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size: 10px;" onclick="setDimensions(70, 100)">
                        {{ __('Jumbo (70×100mm)') }}
                    </button>
                </div>
            </div>

            <!-- Background Image -->
            <div class="col-md-4 mb-3">
                <label class="font-weight-bold text-dark small mb-2 d-block">
                    {{ __('Background Image / Watermark') }} <small class="text-muted">({{ __('Optional') }})</small>
                </label>
                <input type="file" name="background_image" accept="image/jpg,image/png,image/jpeg,image/svg" class="file-upload-default" />
                <div class="input-group">
                    <input type="text" class="form-control file-upload-info form-control-sm" disabled placeholder="{{ __('Upload card background') }}" />
                    <span class="input-group-append">
                        <button class="file-upload-browse btn btn-sm btn-secondary" type="button">
                            <i class="fa fa-upload mr-1"></i>{{ __('Upload') }}
                        </button>
                    </span>
                </div>
                @if ($settings['background_image'] ?? '')
                    <div id="background" class="mt-2 d-flex align-items-center gap-2 p-1 border rounded bg-light">
                        <img src="{{ $settings['background_image'] }}" class="rounded" style="height: 34px; max-width: 60px; object-fit: contain;" alt="Background">
                        <span class="small text-muted flex-fill">{{ __('Background Active') }}</span>
                        <a href="" data-type="background" class="btn btn-xs btn-danger id-card-settings" title="Remove background image">
                            <i class="fa fa-times"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- 4. VISIBLE CARD FIELDS SELECTION -->
<div class="card mb-4 border" style="border-radius: 12px; border-color: #E2E8F0 !important;">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <div>
            <h6 class="font-weight-bold text-dark mb-0">
                <i class="fa fa-check-square-o text-primary mr-2"></i>{{ __('Visible Student Information Fields') }}
            </h6>
            <small class="text-muted">{{ __('Toggle which data attributes should be printed on the student identity card.') }}</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-xs btn-outline-primary" onclick="toggleAllCheckboxes('#studentFieldsContainer', true)">
                {{ __('Select All') }}
            </button>
            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleAllCheckboxes('#studentFieldsContainer', false)">
                {{ __('Deselect All') }}
            </button>
        </div>
    </div>
    <div class="card-body p-3" id="studentFieldsContainer">
        @php
            $activeFields = $settings['student_id_card_fields'] ?? [];
            if (!is_array($activeFields)) {
                $activeFields = explode(',', $activeFields);
            }
        @endphp

        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 g-2">
            <!-- Student Name -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('student_name', $activeFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="student_id_card_fields[]" value="student_name" class="d-none field-toggle-input"
                           @if(in_array('student_name', $activeFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-user mr-1 text-muted"></i> {{ __('Student Name') }}</span>
                </label>
            </div>

            <!-- Class & Section -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('class_section', $activeFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="student_id_card_fields[]" value="class_section" class="d-none field-toggle-input"
                           @if(in_array('class_section', $activeFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-graduation-cap mr-1 text-muted"></i> {{ __('Class & Section') }}</span>
                </label>
            </div>

            <!-- Roll Number -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('roll_no', $activeFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="student_id_card_fields[]" value="roll_no" class="d-none field-toggle-input"
                           @if(in_array('roll_no', $activeFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-hashtag mr-1 text-muted"></i> {{ __('Roll Number') }}</span>
                </label>
            </div>

            <!-- Date of Birth -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('dob', $activeFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="student_id_card_fields[]" value="dob" class="d-none field-toggle-input"
                           @if(in_array('dob', $activeFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-calendar mr-1 text-muted"></i> {{ __('Date of Birth') }}</span>
                </label>
            </div>

            <!-- Gender -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('gender', $activeFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="student_id_card_fields[]" value="gender" class="d-none field-toggle-input"
                           @if(in_array('gender', $activeFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-venus-mars mr-1 text-muted"></i> {{ __('Gender') }}</span>
                </label>
            </div>

            <!-- Session Year -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('session_year', $activeFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="student_id_card_fields[]" value="session_year" class="d-none field-toggle-input"
                           @if(in_array('session_year', $activeFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-clock-o mr-1 text-muted"></i> {{ __('Session Year') }}</span>
                </label>
            </div>

            <!-- Guardian Name -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('guardian_name', $activeFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="student_id_card_fields[]" value="guardian_name" class="d-none field-toggle-input"
                           @if(in_array('guardian_name', $activeFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-shield mr-1 text-muted"></i> {{ __('Guardian Name') }}</span>
                </label>
            </div>

            <!-- Guardian Contact -->
            <div class="col mb-2">
                <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if(in_array('guardian_contact', $activeFields)) active @endif" style="cursor: pointer;">
                    <input type="checkbox" name="student_id_card_fields[]" value="guardian_contact" class="d-none field-toggle-input"
                           @if(in_array('guardian_contact', $activeFields)) checked @endif onchange="toggleFieldCard(this)">
                    <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                    <span class="font-weight-bold small text-dark"><i class="fa fa-phone mr-1 text-muted"></i> {{ __('Guardian Contact') }}</span>
                </label>
            </div>

            <!-- Custom Form Fields (Student) -->
            @foreach ($formFields as $field)
                @if ($field->user_type == 1)
                    <div class="col mb-2">
                        <label class="field-toggle-card d-flex align-items-center p-2 border rounded @if($field->display_on_id) active @endif" style="cursor: pointer;">
                            <input type="checkbox" name="extra_form_fields[]" value="{{ $field->id }}" class="d-none field-toggle-input"
                                   @if($field->display_on_id) checked @endif onchange="toggleFieldCard(this)">
                            <span class="field-check-indicator mr-2"><i class="fa fa-check"></i></span>
                            <span class="font-weight-bold small text-dark"><i class="fa fa-tag mr-1 text-muted"></i> {{ $field->name }}</span>
                        </label>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>

<!-- 5. BACKSIDE ID CARD DESIGN & CONFIGURATION STUDIO -->
<div class="card mb-4 border" style="border-radius: 12px; border-color: #E2E8F0 !important;">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h6 class="font-weight-bold text-dark mb-0">
                <i class="fa fa-clone text-primary mr-2"></i>{{ __('Backside ID Card Design & Information Studio') }}
            </h6>
            <small class="text-muted">{{ __('Configure school declaration, terms & conditions, emergency details, return info, and signatures for duplex card printing.') }}</small>
        </div>
        <div class="custom-control custom-switch mt-2 mt-sm-0">
            <input type="checkbox" class="custom-control-input" id="toggleBacksideCard" name="student_id_card_enable_backside" value="1"
                   {{ ($settings['student_id_card_enable_backside'] ?? 1) ? 'checked' : '' }} onchange="toggleBacksideStudio(this.checked)">
            <label class="custom-control-label font-weight-bold text-dark cursor-pointer" for="toggleBacksideCard">
                <span class="badge badge-success font-weight-bold" id="backsideCardStatusBadge">{{ __('Backside Enabled') }}</span>
            </label>
        </div>
    </div>
    <div class="card-body p-4" id="backsideStudioBody">
        <div class="row">
            <!-- Left Side: Controls -->
            <div class="col-lg-7">
                <!-- Terms & Conditions / School Rules -->
                <div class="form-group mb-3">
                    <label class="font-weight-bold text-dark small mb-1 d-flex align-items-center justify-content-between">
                        <span><i class="fa fa-list-ol text-primary mr-1"></i> {{ __('School Rules & Terms of Card Use') }}</span>
                        <small class="text-muted">{{ __('1 rule per line') }}</small>
                    </label>
                    <textarea name="student_id_card_back_terms" id="student_id_card_back_terms" class="form-control" rows="4" style="font-size: 12px; line-height: 1.4; border-radius: 8px;" placeholder="{{ __('Enter card instructions or terms...') }}" onkeyup="updateBacksidePreview()">{{ $settings['student_id_card_back_terms'] ?? "1. This identity card is strictly non-transferable and remains the property of the school.\n2. The card must be carried and displayed by the student at all times during school hours.\n3. Loss of this card must be reported immediately to the school administration office.\n4. Misuse or alteration of this identity badge is subject to school disciplinary action." }}</textarea>
                </div>

                <!-- Return Information -->
                <div class="form-group mb-3">
                    <label class="font-weight-bold text-dark small mb-1">
                        <i class="fa fa-map-marker text-primary mr-1"></i> {{ __('Card Return Notice & Contact') }}
                    </label>
                    <input type="text" name="student_id_card_back_return_info" id="student_id_card_back_return_info" class="form-control form-control-sm" style="border-radius: 8px;"
                           value="{{ $settings['student_id_card_back_return_info'] ?? ($settings['school_address'] ?? 'Main Campus, Knowledge Boulevard • Tel: 9000000000') }}" placeholder="School address & contact number" onkeyup="updateBacksidePreview()">
                </div>

                <!-- Backside Toggle Elements -->
                <label class="font-weight-bold text-dark small mb-2 d-block">
                    <i class="fa fa-sliders text-primary mr-1"></i> {{ __('Backside Information Blocks') }}
                </label>
                <div class="row row-cols-1 row-cols-sm-2 g-2">
                    <div class="col mb-2">
                        <div class="custom-control custom-checkbox p-2 border rounded bg-light">
                            <input type="checkbox" class="custom-control-input" id="checkBackEmergency" name="student_id_card_back_emergency" value="1"
                                   {{ ($settings['student_id_card_back_emergency'] ?? 1) ? 'checked' : '' }} onchange="updateBacksidePreview()">
                            <label class="custom-control-label font-weight-bold small text-dark" for="checkBackEmergency">
                                <i class="fa fa-heartbeat text-danger mr-1"></i> {{ __('Blood Group & Emergency Contact') }}
                            </label>
                        </div>
                    </div>
                    <div class="col mb-2">
                        <div class="custom-control custom-checkbox p-2 border rounded bg-light">
                            <input type="checkbox" class="custom-control-input" id="checkBackBarcode" name="student_id_card_back_barcode" value="1"
                                   {{ ($settings['student_id_card_back_barcode'] ?? 1) ? 'checked' : '' }} onchange="updateBacksidePreview()">
                            <label class="custom-control-label font-weight-bold small text-dark" for="checkBackBarcode">
                                <i class="fa fa-barcode text-primary mr-1"></i> {{ __('Scannable Student Barcode') }}
                            </label>
                        </div>
                    </div>
                    <div class="col mb-2">
                        <div class="custom-control custom-checkbox p-2 border rounded bg-light">
                            <input type="checkbox" class="custom-control-input" id="checkBackSignatures" name="student_id_card_back_signatures" value="1"
                                   {{ ($settings['student_id_card_back_signatures'] ?? 1) ? 'checked' : '' }} onchange="updateBacksidePreview()">
                            <label class="custom-control-label font-weight-bold small text-dark" for="checkBackSignatures">
                                <i class="fa fa-pencil text-primary mr-1"></i> {{ __('Principal / Signatory Stamp') }}
                            </label>
                        </div>
                    </div>
                    <div class="col mb-2">
                        <div class="custom-control custom-checkbox p-2 border rounded bg-light">
                            <input type="checkbox" class="custom-control-input" id="checkBackParentSign" name="student_id_card_back_parent_sign" value="1"
                                   {{ ($settings['student_id_card_back_parent_sign'] ?? 1) ? 'checked' : '' }} onchange="updateBacksidePreview()">
                            <label class="custom-control-label font-weight-bold small text-dark" for="checkBackParentSign">
                                <i class="fa fa-pencil-square-o text-muted mr-1"></i> {{ __('Parent Signature Line') }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Live Visual Backside Preview -->
            <div class="col-lg-5 mt-4 mt-lg-0 text-center">
                <label class="font-weight-bold text-dark small mb-2 d-block">
                    <i class="fa fa-eye text-primary mr-1"></i> {{ __('Live Backside Preview') }}
                </label>
                <div class="border rounded shadow-sm mx-auto p-2" style="max-width: 230px; background: #FFFFFF; border-radius: 12px !important; text-align: left; font-size: 8px;">
                    <!-- Mini Back Header -->
                    <div class="p-1 text-center theme-primary-bg rounded-top text-white" style="background: var(--card-primary, #1E293B);">
                        <div class="font-weight-bold text-uppercase" style="font-size: 8px; letter-spacing: 0.5px;">{{ strtoupper($schoolTitle) }}</div>
                        <div class="text-uppercase" style="font-size: 6px; color: var(--card-accent, #06B6D4); letter-spacing: 0.5px;">TERMS & EMERGENCY DETAILS</div>
                    </div>
                    <div style="height: 2px; background: var(--card-accent, #06B6D4);"></div>

                    <!-- Mini Back Content -->
                    <div class="p-2" style="background: #FFFFFF;">
                        <!-- Emergency mini box -->
                        <div id="previewBackEmergencyBox" class="p-1 mb-1 rounded border" style="background: #FEF2F2; border-color: #FECACA !important; font-size: 7px;">
                            <div class="d-flex justify-content-between">
                                <span class="font-weight-bold text-danger">Blood Group:</span>
                                <span class="font-weight-bold text-danger">O+</span>
                            </div>
                            <div class="d-flex justify-content-between text-muted" style="font-size: 6.5px;">
                                <span>Emergency:</span>
                                <span class="font-weight-bold text-dark">9123456242</span>
                            </div>
                        </div>

                        <!-- Rules mini lines -->
                        <div class="mb-1" style="font-size: 6.5px; color: #475569; line-height: 1.3;" id="previewBackTermsBox">
                            <div class="font-weight-bold text-dark mb-1" style="font-size: 7px;">RULES & REGULATIONS:</div>
                            <div>• Card is property of school & non-transferable.</div>
                            <div>• Must be worn during school hours.</div>
                            <div>• Loss must be reported immediately.</div>
                        </div>

                        <!-- Return box -->
                        <div class="p-1 rounded bg-light border text-center mb-1 text-muted" style="font-size: 6px;" id="previewBackReturnBox">
                            <strong>IF FOUND, PLEASE RETURN TO:</strong><br>
                            <span id="previewBackReturnText">{{ $settings['school_address'] ?? 'School Admin Office' }}</span>
                        </div>

                        <!-- Dual signatures -->
                        <div class="row no-gutters text-center mb-1" id="previewBackSignBox" style="font-size: 6px;">
                            <div class="col-6 pr-1">
                                <div style="height: 10px; border-bottom: 1px dashed #94A3B8;"></div>
                                <span class="text-muted">Parent Sign</span>
                            </div>
                            <div class="col-6 pl-1">
                                <div style="height: 10px; border-bottom: 1px solid #1E293B;"></div>
                                <span class="font-weight-bold text-dark">Principal</span>
                            </div>
                        </div>

                        <!-- Barcode preview -->
                        <div class="text-center pt-1" id="previewBackBarcodeBox" style="font-size: 8px; font-family: monospace; letter-spacing: 2px;">
                            *DEMO2026486*
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleBacksideStudio(enabled) {
        if (enabled) {
            $('#backsideStudioBody').slideDown(200);
            $('#backsideCardStatusBadge').removeClass('badge-secondary').addClass('badge-success').text("{{ __('Backside Enabled') }}");
        } else {
            $('#backsideStudioBody').slideUp(200);
            $('#backsideCardStatusBadge').removeClass('badge-success').addClass('badge-secondary').text("{{ __('Backside Disabled') }}");
        }
    }

    function updateBacksidePreview() {
        var returnInfo = $('#student_id_card_back_return_info').val();
        $('#previewBackReturnText').text(returnInfo || 'School Admin Office');

        var emg = $('#checkBackEmergency').is(':checked');
        $('#previewBackEmergencyBox').toggle(emg);

        var barcode = $('#checkBackBarcode').is(':checked');
        $('#previewBackBarcodeBox').toggle(barcode);

        var signs = $('#checkBackSignatures').is(':checked');
        $('#previewBackSignBox').toggle(signs);
    }
</script>
