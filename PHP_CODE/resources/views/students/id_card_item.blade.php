@php
    // Determine structural design
    $design = $design ?? ($template_design ?? ($template_id ?? 'wave'));
    // Normalize legacy v1..v10 / h1..h10 names to structural names if passed
    $legacyMap = [
        'v1' => 'wave', 'v2' => 'classic', 'v3' => 'diamond', 'v4' => 'diagonal',
        'v5' => 'hexagon', 'v6' => 'minimal', 'v7' => 'swoosh', 'v8' => 'chevron',
        'v9' => 'wave', 'v10' => 'classic',
        'h1' => 'h_split', 'h2' => 'h_wave', 'h3' => 'h_diagonal', 'h4' => 'h_classic',
        'h5' => 'h_split', 'h6' => 'h_minimal', 'h7' => 'h_wave', 'h8' => 'h_diagonal',
        'h9' => 'h_classic', 'h10' => 'h_minimal',
    ];
    if (isset($legacyMap[$design])) {
        $design = $legacyMap[$design];
    }

    $isLandscape = str_starts_with($design, 'h') || !empty($isLandscape);

    // Dynamic Theme Colors
    $primary = $theme['primary'] ?? '#1E293B';
    $accent = $theme['accent'] ?? '#06B6D4';
    $bodyBg = $theme['body_bg'] ?? '#FFFFFF';
    $cardBorder = $theme['border'] ?? '#CBD5E1';
    $textColor = $theme['text'] ?? '#FFFFFF';

    // Student Photo resolution (Check tenant storage, public storage, direct path)
    $photoPath = null;
    $rawImage = $student->getRawOriginal('image');
    if (!empty($rawImage)) {
        $candidates = [
            storage_path('app/public/' . $rawImage),
            public_path('storage/' . $rawImage),
            public_path($rawImage),
            storage_path('app/' . $rawImage),
        ];
        if (!empty($student->school_id)) {
            $candidates[] = storage_path('app/public/' . $student->school_id . '/' . ltrim($rawImage, '/'));
            $candidates[] = public_path('storage/' . $student->school_id . '/' . ltrim($rawImage, '/'));
        }
        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                $photoPath = $cand;
                break;
            }
        }
    }
    // If no custom photo uploaded, fall back to neutral silhouette placeholder (never mock demo student)
    if (!$photoPath || !file_exists($photoPath)) {
        $photoPath = public_path('assets/dummy_logo.jpg');
    }

    // Logo path
    $logoPath = null;
    if (!empty($settings['horizontal_logo']) && file_exists(public_path('storage/' . $settings['horizontal_logo']))) {
        $logoPath = public_path('storage/' . $settings['horizontal_logo']);
    } elseif (file_exists(public_path('assets/horizontal-logo2.svg'))) {
        $logoPath = public_path('assets/horizontal-logo2.svg');
    } elseif (file_exists(public_path('assets/favicon.svg'))) {
        $logoPath = public_path('assets/favicon.svg');
    }

    // Signature path
    $sigPath = null;
    if (!empty($settings['signature']) && file_exists(public_path('storage/' . $settings['signature']))) {
        $sigPath = public_path('storage/' . $settings['signature']);
    }

    // Background watermark
    $bgPath = null;
    if (!empty($settings['background_image']) && file_exists(public_path('storage/' . $settings['background_image']))) {
        $bgPath = public_path('storage/' . $settings['background_image']);
    }

    // Blood group lookup
    $bloodGroup = null;
    if (!empty($student->extra_student_details)) {
        foreach ($student->extra_student_details as $extra) {
            if ($extra->form_field && stripos($extra->form_field->name, 'blood') !== false) {
                $bloodGroup = $extra->data;
                break;
            }
        }
    }
    $bloodGroup = $bloodGroup ?: 'O+';
    $schoolName = $settings['school_name'] ?? 'ZOLO SMART SCHOOL';
    $sessionName = $sessionYear->name ?? '2025-2026';
    $studentFullName = strtoupper($student->full_name);
    $className = $student->student->class_section->full_name ?? '-';
    $rollNo = $student->student->roll_number ?? '-';
    $admNo = $student->student->admission_no ?? ($student->id ?? '-');
    $dobFormatted = date($settings['date_format'] ?? 'd-m-Y', strtotime($student->dob));
    $guardianName = $student->student->guardian->full_name ?? '-';
    $guardianPhone = $student->student->guardian->mobile ?? '-';
@endphp

<div class="id-card-wrapper {{ $isLandscape ? 'card-horizontal' : 'card-vertical' }}" style="background-color: {{ $bodyBg }}; border: 1px solid {{ $cardBorder }};">
    @if ($bgPath)
        <img class="watermark-bg" src="{{ $bgPath }}" alt="Watermark" />
    @endif

    @if ($isLandscape)
        {{-- =========================================================================
             HORIZONTAL ARCHETYPES
             ========================================================================= --}}
        @if ($design === 'h_wave')
            <!-- DESIGN H-WAVE: Flowing Wave Header & Footer -->
            <table style="width: 100%; border-collapse: collapse; background: {{ $primary }};">
                <tr>
                    <td style="padding: 3px 6px; width: 25%;">
                        @if ($logoPath) <img height="20" src="{{ $logoPath }}" alt="Logo" /> @endif
                    </td>
                    <td style="padding: 3px 8px; text-align: right; color: #ffffff; font-size: 8pt; font-weight: bold; text-transform: uppercase;">
                        {{ $schoolName }}
                    </td>
                </tr>
            </table>
            <svg viewBox="0 0 300 16" style="display:block; width:100%; height:14px; margin-top:-1px;">
                <path d="M0,0 L300,0 L300,6 Q150,16 0,6 Z" fill="{{ $primary }}" />
                <path d="M0,6 Q150,16 300,6 L300,9 Q150,19 0,9 Z" fill="{{ $accent }}" />
            </svg>
            <table style="width: 100%; border-collapse: collapse; margin-top: 2px;">
                <tr>
                    <td style="width: 30%; text-align: center; vertical-align: top; padding: 2px 4px;">
                        <img style="width: 58px; height: 68px; border-radius: 50%; border: 2.5px solid {{ $accent }}; object-fit: cover;" src="{{ $photoPath }}" alt="Photo" />
                        <div style="font-size: 6pt; font-weight: bold; color: {{ $primary }}; margin-top: 2px;">GR: {{ $admNo }}</div>
                    </td>
                    <td style="width: 70%; vertical-align: top; padding: 0 6px 0 2px;">
                        <div style="font-size: 9.5pt; font-weight: bold; color: {{ $primary }}; line-height: 1.1;">{{ $studentFullName }}</div>
                        <div style="display: inline-block; background: {{ $accent }}; color: #ffffff; font-size: 5.5pt; font-weight: bold; padding: 1px 4px; border-radius: 2px; margin: 1px 0 3px 0;">
                            STUDENT &bull; {{ $sessionName }}
                        </div>
                        <table class="info-table" style="font-size: 6.5pt;">
                            <tr><td class="info-label">Class & Sec:</td><td class="info-value"><strong>{{ $className }}</strong></td></tr>
                            <tr><td class="info-label">Roll / Blood:</td><td class="info-value">#{{ $rollNo }} &bull; <span style="color:#DC2626; font-weight:bold;">{{ $bloodGroup }}</span></td></tr>
                            <tr><td class="info-label">DOB / Phone:</td><td class="info-value">{{ $dobFormatted }} &bull; {{ $guardianPhone }}</td></tr>
                            <tr><td class="info-label">Guardian:</td><td class="info-value">{{ $guardianName }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
            <!-- Footer Wave -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%;">
                <svg viewBox="0 0 300 12" style="display:block; width:100%; height:10px;">
                    <path d="M0,8 Q150,0 300,8 L300,12 L0,12 Z" fill="{{ $primary }}" />
                </svg>
                <div style="background: {{ $primary }}; text-align: center; color: #ffffff; font-size: 5pt; padding: 2px 4px; border-top: 1px solid {{ $accent }};">
                    {{ $settings['school_address'] ?? 'Official Student Identity Card' }}
                </div>
            </div>

        @elseif ($design === 'h_diagonal')
            <!-- DESIGN H-DIAGONAL: Dynamic Angle Split -->
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 32%; vertical-align: top; background: {{ $primary }}; text-align: center; padding: 6px 4px; color: #ffffff;">
                        @if ($logoPath) <img height="18" src="{{ $logoPath }}" alt="Logo" style="margin-bottom: 3px;" /> @endif
                        <img style="width: 56px; height: 66px; border-radius: 4px; border: 2px solid {{ $accent }}; object-fit: cover; display:block; margin: 0 auto;" src="{{ $photoPath }}" alt="Photo" />
                        <div style="font-size: 6pt; font-weight: bold; color: {{ $accent }}; margin-top: 3px;">ID: {{ $admNo }}</div>
                        <div style="font-size: 5pt; color: #CBD5E1;">{{ $bloodGroup }}</div>
                    </td>
                    <td style="width: 68%; vertical-align: top; padding: 6px 8px 4px 8px;">
                        <div style="font-size: 8pt; font-weight: bold; color: {{ $primary }}; text-transform: uppercase;">{{ $schoolName }}</div>
                        <div style="font-size: 5.5pt; color: #64748B; border-bottom: 1.5px solid {{ $accent }}; padding-bottom: 2px; margin-bottom: 3px;">
                            {{ __('STUDENT IDENTITY CARD') }} &bull; {{ $sessionName }}
                        </div>
                        <div style="font-size: 10pt; font-weight: bold; color: {{ $primary }}; margin-bottom: 3px;">{{ $studentFullName }}</div>
                        <table class="info-table" style="font-size: 6.5pt;">
                            <tr><td class="info-label">Class & Sec:</td><td class="info-value"><strong>{{ $className }}</strong></td></tr>
                            <tr><td class="info-label">Roll No:</td><td class="info-value">#{{ $rollNo }}</td></tr>
                            <tr><td class="info-label">Date of Birth:</td><td class="info-value">{{ $dobFormatted }}</td></tr>
                            <tr><td class="info-label">Emergency:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                        </table>
                        <div style="margin-top: 4px; display: flex; justify-content: space-between; align-items: flex-end;">
                            <div style="font-size: 5pt; font-family: monospace; color: #64748B;">*{{ $admNo }}*</div>
                            @if ($sigPath)
                                <img height="14" src="{{ $sigPath }}" alt="Sign" style="float: right;" />
                            @else
                                <div style="font-size: 5pt; color: #64748B; border-top: 1px solid #CBD5E1; float: right; padding-top: 1px;">Principal</div>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>

        @else
            <!-- DESIGN H-SPLIT (Default Horizontal): Executive Banner -->
            <table class="card-header-table" style="background-color: {{ $primary }}; border-bottom: 2px solid {{ $accent }};">
                <tr>
                    <td class="header-logo-cell">
                        @if ($logoPath)
                            <img height="22" src="{{ $logoPath }}" alt="Logo" />
                        @else
                            <span style="font-size: 11px; font-weight: bold; color: #ffffff;">&#9733;</span>
                        @endif
                    </td>
                    <td class="header-school-cell" style="color: #ffffff;">
                        {{ $schoolName }}
                    </td>
                </tr>
            </table>
            <div class="card-title-bar" style="background: {{ $accent }}; color: #ffffff;">
                {{ __('Student Identity Card') }} &bull; {{ $sessionName }}
            </div>
            <table class="card-body-table" style="width: 100%; border-collapse: collapse; margin-top: 3px;">
                <tr>
                    <td style="width: 32%; text-align: center; vertical-align: top; padding: 2px 4px;">
                        <img class="student-photo-frame" width="62" height="74" src="{{ $photoPath }}" style="border-color: {{ $accent }};" alt="Photo" />
                        <div style="font-size: 7px; color: #64748B; margin-top: 2px; font-weight: bold;">
                            ID: {{ $admNo }}
                        </div>
                    </td>
                    <td style="width: 68%; vertical-align: top; padding: 0 6px 0 2px;">
                        <div style="font-size: 10px; font-weight: bold; color: {{ $primary }}; margin-bottom: 2px;">
                            {{ $studentFullName }}
                        </div>
                        <table class="info-table">
                            <tr><td class="info-label">{{ __('Class & Sec') }}:</td><td class="info-value"><strong>{{ $className }}</strong></td></tr>
                            <tr><td class="info-label">{{ __('Roll / GR') }}:</td><td class="info-value">#{{ $rollNo }} &bull; GR: {{ $admNo }}</td></tr>
                            <tr><td class="info-label">{{ __('DOB & Blood') }}:</td><td class="info-value">{{ $dobFormatted }} &bull; <span style="color:#DC2626; font-weight:bold;">{{ $bloodGroup }}</span></td></tr>
                            <tr><td class="info-label">{{ __('Guardian') }}:</td><td class="info-value">{{ $guardianName }}</td></tr>
                            <tr><td class="info-label">{{ __('Emergency') }}:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                        </table>
                    </td>
                </tr>
            </table>
            <div class="card-footer-table" style="background-color: {{ $primary }}; border-top: 1.5px solid {{ $accent }}; color: #ffffff;">
                <table style="width: 100%;">
                    <tr>
                        <td style="font-size: 5.5pt; text-align: left; vertical-align: middle;">{{ $settings['school_address'] ?? 'School Campus' }}</td>
                        <td style="font-size: 5.5pt; text-align: right; vertical-align: middle;">Auth. Signatory</td>
                    </tr>
                </table>
            </div>
        @endif

    @else
        {{-- =========================================================================
             VERTICAL ARCHETYPES (8 DISTINCT STRUCTURAL DESIGNS)
             ========================================================================= --}}

        @if ($design === 'wave')
            <!-- ================= ARCHETYPE 1: CURVED WAVE ARC ================= -->
            <div style="background: {{ $primary }}; padding: 6px 6px 4px 6px; text-align: center; color: #ffffff;">
                @if ($logoPath) <img height="22" src="{{ $logoPath }}" alt="Logo" style="margin-bottom: 2px;" /> @endif
                <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; line-height: 1.1;">{{ $schoolName }}</div>
            </div>
            <!-- Wave Graphic Divider -->
            <svg viewBox="0 0 200 24" style="display:block; width:100%; height:20px; margin-top:-1px;">
                <path d="M0,0 L200,0 L200,8 Q100,24 0,8 Z" fill="{{ $primary }}" />
                <path d="M0,8 Q100,24 200,8 L200,11 Q100,27 0,11 Z" fill="{{ $accent }}" />
            </svg>

            <!-- Overlapping Circle Portrait -->
            <div style="text-align: center; margin-top: -18px;">
                <div style="display: inline-block; padding: 2px; background: #ffffff; border-radius: 50%; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
                    <img style="width: 66px; height: 66px; border-radius: 50%; border: 2.5px solid {{ $accent }}; object-fit: cover; display:block;" src="{{ $photoPath }}" alt="Photo" />
                </div>
            </div>

            <!-- Student Title & Badge -->
            <div style="text-align: center; margin-top: 3px; padding: 0 6px;">
                <div style="font-size: 9pt; font-weight: bold; color: {{ $primary }}; line-height: 1.15;">{{ $studentFullName }}</div>
                <div style="display: inline-block; background: {{ $accent }}; color: #ffffff; font-size: 5.5pt; font-weight: bold; padding: 1px 6px; border-radius: 10px; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px;">
                    STUDENT &bull; {{ $sessionName }}
                </div>
            </div>

            <!-- Data Table -->
            <div style="padding: 5px 8px 2px 8px;">
                <table class="info-table">
                    <tr><td class="info-label">Class & Sec:</td><td class="info-value"><strong>{{ $className }}</strong></td></tr>
                    <tr><td class="info-label">Roll / GR No:</td><td class="info-value">#{{ $rollNo }} &bull; GR: {{ $admNo }}</td></tr>
                    <tr><td class="info-label">DOB & Blood:</td><td class="info-value">{{ $dobFormatted }} &bull; <span style="color:#DC2626; font-weight:bold;">{{ $bloodGroup }}</span></td></tr>
                    <tr><td class="info-label">Guardian:</td><td class="info-value">{{ $guardianName }}</td></tr>
                    <tr><td class="info-label">Emergency:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                </table>
            </div>

            <!-- Bottom Wave Footer -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%;">
                <svg viewBox="0 0 200 14" style="display:block; width:100%; height:12px;">
                    <path d="M0,8 Q100,0 200,8 L200,14 L0,14 Z" fill="{{ $primary }}" />
                </svg>
                <div style="background: {{ $primary }}; text-align: center; color: #ffffff; font-size: 5pt; padding: 2px 4px 3px 4px; border-top: 1px solid {{ $accent }};">
                    {{ $settings['school_phone'] ?? 'Ph: 9000000000' }} &bull; {{ $settings['school_address'] ?? 'Official Student Card' }}
                </div>
            </div>

        @elseif ($design === 'diamond')
            <!-- ================= ARCHETYPE 2: DIAMOND / RHOMBUS BADGE ================= -->
            <div style="background: {{ $primary }}; padding: 6px 4px 10px 4px; text-align: center; color: #ffffff; position: relative;">
                @if ($logoPath) <img height="20" src="{{ $logoPath }}" alt="Logo" style="margin-bottom: 2px;" /> @endif
                <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase;">{{ $schoolName }}</div>
                <div style="font-size: 5.5pt; color: {{ $accent }}; letter-spacing: 0.5px;">ACADEMIC SESSION {{ $sessionName }}</div>
            </div>
            <!-- Angled Banner Chevron -->
            <div style="width: 0; height: 0; border-left: 100px solid transparent; border-right: 100px solid transparent; border-top: 10px solid {{ $primary }}; margin: 0 auto;"></div>

            <!-- Diamond Framed Portrait Container -->
            <div style="text-align: center; margin-top: 4px;">
                <div style="display: inline-block; padding: 3px; background: #ffffff; border: 2px solid {{ $accent }}; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.15);">
                    <img style="width: 60px; height: 70px; border-radius: 6px; object-fit: cover; display: block;" src="{{ $photoPath }}" alt="Photo" />
                </div>
            </div>

            <!-- Name Ribbon with Cut Ends -->
            <div style="text-align: center; margin-top: 4px;">
                <div style="display: inline-block; background: {{ $primary }}; border: 1px solid {{ $accent }}; color: #ffffff; font-size: 8pt; font-weight: bold; padding: 2px 10px; border-radius: 4px;">
                    {{ $studentFullName }}
                </div>
                <div style="font-size: 6pt; color: #DC2626; font-weight: bold; margin-top: 2px;">BLOOD GROUP: {{ $bloodGroup }}</div>
            </div>

            <!-- Details Table in Framed Box -->
            <div style="margin: 4px 8px; padding: 4px 6px; border: 1px solid #E2E8F0; border-radius: 6px; background: #FAFBFD;">
                <table class="info-table" style="font-size: 6pt;">
                    <tr><td class="info-label">Class:</td><td class="info-value"><strong>{{ $className }}</strong></td></tr>
                    <tr><td class="info-label">Roll No:</td><td class="info-value">#{{ $rollNo }}</td></tr>
                    <tr><td class="info-label">Admission:</td><td class="info-value">{{ $admNo }}</td></tr>
                    <tr><td class="info-label">Birth Date:</td><td class="info-value">{{ $dobFormatted }}</td></tr>
                    <tr><td class="info-label">Contact:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                </table>
            </div>

            <!-- Footer Bar -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%; background: {{ $accent }}; color: #ffffff; text-align: center; font-size: 5pt; padding: 3px 4px; font-weight: bold; text-transform: uppercase;">
                {{ __('Student Identity Card') }} &bull; {{ $schoolName }}
            </div>

        @elseif ($design === 'diagonal')
            <!-- ================= ARCHETYPE 3: DIAGONAL SPLIT DYNAMIC ================= -->
            <div style="background: {{ $primary }}; color: #ffffff; padding: 8px 6px 14px 6px; text-align: left;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 30%;">@if ($logoPath) <img height="22" src="{{ $logoPath }}" alt="Logo" /> @endif</td>
                        <td style="width: 70%; text-align: right;">
                            <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase; color: #ffffff;">{{ $schoolName }}</div>
                            <div style="font-size: 5pt; color: {{ $accent }};">STUDENT PASS</div>
                        </td>
                    </tr>
                </table>
            </div>
            <!-- Diagonal Slash Divider -->
            <svg viewBox="0 0 200 18" style="display:block; width:100%; height:16px; margin-top:-1px;">
                <polygon points="0,0 200,0 200,4 0,18" fill="{{ $primary }}" />
                <polygon points="0,14 200,0 200,3 0,17" fill="{{ $accent }}" />
            </svg>

            <!-- Offset Portrait -->
            <div style="padding: 0 10px; margin-top: -12px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 45%; vertical-align: middle;">
                            <img style="width: 62px; height: 62px; border-radius: 50%; border: 3px solid {{ $accent }}; object-fit: cover; box-shadow: 0 2px 6px rgba(0,0,0,0.15);" src="{{ $photoPath }}" alt="Photo" />
                        </td>
                        <td style="width: 55%; vertical-align: middle; padding-left: 4px;">
                            <div style="font-size: 9pt; font-weight: bold; color: {{ $primary }}; line-height: 1.1;">{{ $studentFullName }}</div>
                            <div style="font-size: 6.5pt; font-weight: bold; color: {{ $accent }}; margin-top: 2px;">GRADE {{ $className }}</div>
                            <div style="font-size: 5.5pt; color: #64748B;">ID: {{ $admNo }}</div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Details Section -->
            <div style="padding: 8px 10px 4px 10px;">
                <table class="info-table" style="font-size: 6.5pt;">
                    <tr><td class="info-label">Roll Number:</td><td class="info-value">#{{ $rollNo }}</td></tr>
                    <tr><td class="info-label">Date of Birth:</td><td class="info-value">{{ $dobFormatted }}</td></tr>
                    <tr><td class="info-label">Blood Group:</td><td class="info-value"><strong style="color:#DC2626;">{{ $bloodGroup }}</strong></td></tr>
                    <tr><td class="info-label">Guardian:</td><td class="info-value">{{ $guardianName }}</td></tr>
                    <tr><td class="info-label">Emergency Tel:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                </table>
            </div>

            <!-- Signature & Barcode -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%; border-top: 1.5px solid {{ $primary }}; background: #FAFBFD; padding: 4px 8px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 60%; font-size: 5pt; color: #64748B; vertical-align: middle;">
                            <div style="font-family: monospace; font-size: 5.5pt;">*STU{{ $student->id }}*</div>
                            <div>Valid: {{ $sessionName }}</div>
                        </td>
                        <td style="width: 40%; text-align: right; vertical-align: middle;">
                            @if ($sigPath)
                                <img height="15" src="{{ $sigPath }}" alt="Signature" />
                            @else
                                <div style="font-size: 5pt; border-top: 1px solid #94A3B8; display:inline-block; padding-top:1px;">Principal</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>

        @elseif ($design === 'hexagon')
            <!-- ================= ARCHETYPE 4: HEXAGON TECH POLYGON ================= -->
            <div style="background: {{ $primary }}; padding: 6px 4px; text-align: center; color: #ffffff; border-bottom: 2px solid {{ $accent }};">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 25%; text-align: center;">@if ($logoPath) <img height="18" src="{{ $logoPath }}" alt="Logo" /> @endif</td>
                        <td style="width: 75%; text-align: left; padding-left: 2px;">
                            <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase;">{{ $schoolName }}</div>
                            <div style="font-size: 5pt; color: {{ $accent }};">ACADEMIC TECH PASS</div>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Hexagon Frame Styling -->
            <div style="text-align: center; margin-top: 8px;">
                <div style="display: inline-block; position: relative; padding: 4px;">
                    <!-- Chevrons flanking photo -->
                    <span style="display:inline-block; vertical-align:middle; color:{{ $accent }}; font-size:14px; font-weight:bold; margin-right:4px;">&gt;</span>
                    <img style="width: 64px; height: 72px; border: 2.5px solid {{ $primary }}; border-radius: 8px; object-fit: cover; vertical-align: middle;" src="{{ $photoPath }}" alt="Photo" />
                    <span style="display:inline-block; vertical-align:middle; color:{{ $accent }}; font-size:14px; font-weight:bold; margin-left:4px;">&lt;</span>
                </div>
            </div>

            <!-- Name and ID Badge -->
            <div style="text-align: center; margin-top: 2px;">
                <div style="font-size: 9pt; font-weight: bold; color: {{ $primary }};">{{ $studentFullName }}</div>
                <div style="display: inline-block; background: {{ $primary }}; color: {{ $accent }}; font-size: 5.5pt; font-weight: bold; padding: 1.5px 8px; border-radius: 2px; margin-top: 1px;">
                    CLASS: {{ $className }} &bull; ROLL: {{ $rollNo }}
                </div>
            </div>

            <!-- Structured Data Grid -->
            <div style="padding: 6px 10px;">
                <table class="info-table" style="font-size: 6pt;">
                    <tr><td class="info-label">GR / Admission:</td><td class="info-value"><strong>{{ $admNo }}</strong></td></tr>
                    <tr><td class="info-label">Date of Birth:</td><td class="info-value">{{ $dobFormatted }}</td></tr>
                    <tr><td class="info-label">Blood Group:</td><td class="info-value"><span style="color:#DC2626; font-weight:bold;">{{ $bloodGroup }}</span></td></tr>
                    <tr><td class="info-label">Guardian Name:</td><td class="info-value">{{ $guardianName }}</td></tr>
                    <tr><td class="info-label">Contact No:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                </table>
            </div>

            <!-- Tech Barcode Footer -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%; background: {{ $primary }}; color: #ffffff; padding: 3px 6px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="font-size: 5pt; color: {{ $accent }}; font-family: monospace;">*{{ $admNo }}*</td>
                        <td style="font-size: 5pt; text-align: right; color: #CBD5E1;">{{ $sessionName }}</td>
                    </tr>
                </table>
            </div>

        @elseif ($design === 'classic')
            <!-- ================= ARCHETYPE 5: CLASSIC ACADEMIC CREST ================= -->
            <div style="border-top: 3px solid {{ $accent }}; background: {{ $primary }}; padding: 6px 4px; text-align: center; color: #ffffff;">
                @if ($logoPath) <img height="22" src="{{ $logoPath }}" alt="Logo" style="margin-bottom: 2px;" /> @endif
                <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase;">{{ $schoolName }}</div>
                <div style="font-size: 5pt; color: {{ $accent }}; text-transform: uppercase;">Recognized &amp; Affiliated Education Center</div>
            </div>
            <div style="background: {{ $accent }}; color: #ffffff; text-align: center; font-size: 5.5pt; font-weight: bold; padding: 1.5px 0; text-transform: uppercase; letter-spacing: 0.5px;">
                Identity Card &bull; Session {{ $sessionName }}
            </div>

            <!-- Photo with Double Crest Border -->
            <div style="text-align: center; margin-top: 5px;">
                <img style="width: 62px; height: 74px; border: 2px solid {{ $primary }}; outline: 2px solid {{ $accent }}; border-radius: 4px; object-fit: cover;" src="{{ $photoPath }}" alt="Photo" />
            </div>

            <!-- Student Name -->
            <div style="text-align: center; margin-top: 4px;">
                <div style="font-size: 9.5pt; font-weight: bold; color: {{ $primary }};">{{ $studentFullName }}</div>
                <div style="font-size: 6.5pt; font-weight: bold; color: #475569;">Class: {{ $className }} (Roll: {{ $rollNo }})</div>
            </div>

            <!-- Standard Indian School Field Layout -->
            <div style="padding: 4px 8px;">
                <table class="info-table" style="font-size: 6pt; line-height: 1.2;">
                    <tr><td class="info-label">Admission No:</td><td class="info-value">{{ $admNo }}</td></tr>
                    <tr><td class="info-label">Father's Name:</td><td class="info-value">{{ $guardianName }}</td></tr>
                    <tr><td class="info-label">Date of Birth:</td><td class="info-value">{{ $dobFormatted }}</td></tr>
                    <tr><td class="info-label">Blood Group:</td><td class="info-value"><span style="color:#DC2626; font-weight:bold;">{{ $bloodGroup }}</span></td></tr>
                    <tr><td class="info-label">Contact No:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                </table>
            </div>

            <!-- Bottom School Address Strip -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%; background: {{ $primary }}; color: #ffffff; text-align: center; font-size: 5pt; padding: 3px 4px;">
                School Contact: {{ $settings['school_phone'] ?? '9000000000' }} &bull; Principal Signature
            </div>

        @elseif ($design === 'minimal')
            <!-- ================= ARCHETYPE 6: EXECUTIVE MINIMALIST / LANYARD ================= -->
            <!-- Top Lanyard Punch Slot Graphic -->
            <div style="padding-top: 4px; text-align: center;">
                <div style="width: 28px; height: 5px; border-radius: 3px; background: #CBD5E1; margin: 0 auto;"></div>
            </div>

            <!-- Header with Colored Left Accent Spine -->
            <div style="padding: 4px 8px 2px 8px; border-left: 4px solid {{ $primary }}; margin: 4px 0 6px 6px;">
                <div style="font-size: 7.5pt; font-weight: bold; color: {{ $primary }}; text-transform: uppercase;">{{ $schoolName }}</div>
                <div style="font-size: 5pt; color: {{ $accent }}; font-weight: bold;">STUDENT IDENTITY CARD</div>
            </div>

            <!-- Center Photo -->
            <div style="text-align: center; margin-top: 4px;">
                <img style="width: 64px; height: 74px; border-radius: 8px; border: 1.5px solid #CBD5E1; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.08);" src="{{ $photoPath }}" alt="Photo" />
            </div>

            <!-- Student Name & Pill -->
            <div style="text-align: center; margin-top: 3px;">
                <div style="font-size: 9pt; font-weight: bold; color: #0F172A;">{{ $studentFullName }}</div>
                <div style="font-size: 6pt; color: #64748B;">ID: {{ $admNo }} &bull; {{ $className }}</div>
            </div>

            <!-- Clean Minimal Details -->
            <div style="padding: 4px 10px;">
                <table class="info-table" style="font-size: 6.5pt;">
                    <tr><td class="info-label">Roll No:</td><td class="info-value">#{{ $rollNo }}</td></tr>
                    <tr><td class="info-label">DOB:</td><td class="info-value">{{ $dobFormatted }}</td></tr>
                    <tr><td class="info-label">Blood:</td><td class="info-value">{{ $bloodGroup }}</td></tr>
                    <tr><td class="info-label">Emergency:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                </table>
            </div>

            <!-- Bottom Stripe -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%; height: 5px; background: {{ $primary }}; border-top: 2px solid {{ $accent }};"></div>

        @elseif ($design === 'chevron')
            <!-- ================= ARCHETYPE 7: CHEVRON V-RIBBON ================= -->
            <div style="background: {{ $primary }}; padding: 6px 4px 2px 4px; text-align: center; color: #ffffff;">
                <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase;">{{ $schoolName }}</div>
                <div style="font-size: 5pt; color: {{ $accent }};">{{ $sessionName }}</div>
            </div>
            <!-- Downward Pointing Chevron V -->
            <svg viewBox="0 0 200 20" style="display:block; width:100%; height:16px;">
                <polygon points="0,0 200,0 100,20" fill="{{ $primary }}" />
                <polygon points="0,0 100,16 200,0 200,3 100,19 0,3" fill="{{ $accent }}" />
            </svg>

            <!-- Circle Portrait Nested Under Chevron -->
            <div style="text-align: center; margin-top: -6px;">
                <img style="width: 66px; height: 66px; border-radius: 50%; border: 3px solid {{ $accent }}; object-fit: cover; box-shadow: 0 2px 6px rgba(0,0,0,0.15);" src="{{ $photoPath }}" alt="Photo" />
            </div>

            <!-- Student Name -->
            <div style="text-align: center; margin-top: 3px;">
                <div style="font-size: 9.5pt; font-weight: bold; color: {{ $primary }};">{{ $studentFullName }}</div>
                <div style="display: inline-block; background: {{ $accent }}; color: #ffffff; font-size: 5.5pt; font-weight: bold; padding: 1px 6px; border-radius: 10px;">
                    {{ $className }} &bull; #{{ $rollNo }}
                </div>
            </div>

            <!-- Details Table -->
            <div style="padding: 6px 10px;">
                <table class="info-table" style="font-size: 6.5pt;">
                    <tr><td class="info-label">Admission No:</td><td class="info-value"><strong>{{ $admNo }}</strong></td></tr>
                    <tr><td class="info-label">Birth Date:</td><td class="info-value">{{ $dobFormatted }}</td></tr>
                    <tr><td class="info-label">Blood Group:</td><td class="info-value"><span style="color:#DC2626; font-weight:bold;">{{ $bloodGroup }}</span></td></tr>
                    <tr><td class="info-label">Guardian:</td><td class="info-value">{{ $guardianName }}</td></tr>
                    <tr><td class="info-label">Emergency Tel:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                </table>
            </div>

            <!-- Bottom Minimal Bar -->
            <div style="position: absolute; bottom: 0; left: 0; width: 100%; background: {{ $primary }}; color: #ffffff; text-align: center; font-size: 5pt; padding: 2.5px 4px; border-top: 2px solid {{ $accent }};">
                Student Identity Card &bull; {{ $schoolName }}
            </div>

        @else
            <!-- ================= ARCHETYPE 8: DUAL-WAVE SWOOSH (Default Fallback) ================= -->
            <div style="background: {{ $primary }}; padding: 6px 4px 2px 4px; text-align: center; color: #ffffff;">
                @if ($logoPath) <img height="20" src="{{ $logoPath }}" alt="Logo" style="margin-bottom: 2px;" /> @endif
                <div style="font-size: 8pt; font-weight: bold; text-transform: uppercase;">{{ $schoolName }}</div>
            </div>
            <svg viewBox="0 0 200 16" style="display:block; width:100%; height:14px; margin-top:-1px;">
                <path d="M0,0 L200,0 C150,14 50,0 0,14 Z" fill="{{ $primary }}" />
                <path d="M0,14 C50,0 150,14 200,0 L200,3 C150,17 50,3 0,17 Z" fill="{{ $accent }}" />
            </svg>

            <!-- Portrait -->
            <div style="text-align: center; margin-top: -6px;">
                <img style="width: 64px; height: 64px; border-radius: 50%; border: 3px solid {{ $accent }}; object-fit: cover; box-shadow: 0 2px 6px rgba(0,0,0,0.12);" src="{{ $photoPath }}" alt="Photo" />
            </div>

            <div style="text-align: center; margin-top: 3px;">
                <div style="font-size: 9pt; font-weight: bold; color: {{ $primary }};">{{ $studentFullName }}</div>
                <div style="font-size: 6pt; color: #64748B;">CLASS {{ $className }} &bull; ROLL #{{ $rollNo }}</div>
            </div>

            <div style="padding: 6px 10px;">
                <table class="info-table" style="font-size: 6.5pt;">
                    <tr><td class="info-label">Student ID:</td><td class="info-value"><strong>{{ $admNo }}</strong></td></tr>
                    <tr><td class="info-label">Date of Birth:</td><td class="info-value">{{ $dobFormatted }}</td></tr>
                    <tr><td class="info-label">Blood Group:</td><td class="info-value"><span style="color:#DC2626; font-weight:bold;">{{ $bloodGroup }}</span></td></tr>
                    <tr><td class="info-label">Guardian:</td><td class="info-value">{{ $guardianName }}</td></tr>
                    <tr><td class="info-label">Emergency Tel:</td><td class="info-value">{{ $guardianPhone }}</td></tr>
                </table>
            </div>

            <div style="position: absolute; bottom: 0; left: 0; width: 100%;">
                <svg viewBox="0 0 200 12" style="display:block; width:100%; height:10px;">
                    <path d="M0,8 C50,0 150,12 200,4 L200,12 L0,12 Z" fill="{{ $primary }}" />
                </svg>
                <div style="background: {{ $primary }}; text-align: center; color: #ffffff; font-size: 5pt; padding: 2px 4px 3px 4px; border-top: 1px solid {{ $accent }};">
                    {{ $settings['school_address'] ?? 'Official Student Card' }} &bull; {{ $sessionName }}
                </div>
            </div>
        @endif

    @endif
</div>
