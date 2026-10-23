@php
    $isLandscape = str_starts_with($design ?? '', 'h') || !empty($isLandscape);

    // Dynamic Theme Colors
    $primary = $theme['primary'] ?? ($primary_color ?? '#1E293B');
    $accent = $theme['accent'] ?? ($accent_color ?? '#06B6D4');
    $bodyBg = $theme['body_bg'] ?? '#FFFFFF';
    $cardBorder = $theme['border'] ?? '#CBD5E1';
    $textColor = $theme['text'] ?? '#FFFFFF';

    // Signature path
    $sigPath = null;
    if (!empty($settings['signature'])) {
        $sigCandidates = [
            storage_path('app/public/' . $settings['signature']),
            public_path('storage/' . $settings['signature']),
            public_path($settings['signature']),
        ];
        foreach ($sigCandidates as $sp) {
            if (file_exists($sp)) {
                $sigPath = $sp;
                break;
            }
        }
    }

    // Logo path
    $logoPath = null;
    if (!empty($settings['horizontal_logo']) && file_exists(public_path('storage/' . $settings['horizontal_logo']))) {
        $logoPath = public_path('storage/' . $settings['horizontal_logo']);
    } elseif (file_exists(public_path('assets/horizontal-logo2.svg'))) {
        $logoPath = public_path('assets/horizontal-logo2.svg');
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
    $schoolAddress = $settings['school_address'] ?? 'Main Campus, Knowledge Boulevard';
    $schoolPhone = $settings['school_phone'] ?? $settings['school_mobile'] ?? '9000000000';
    $schoolEmail = $settings['school_email'] ?? 'info@school.edu';
    $sessionName = $sessionYear->name ?? '2025-2026';
    $studentFullName = strtoupper($student->full_name);
    $className = $student->student->class_section->full_name ?? '-';
    $rollNo = $student->student->roll_number ?? '-';
    $admNo = $student->student->admission_no ?? ($student->id ?? '-');
    $guardianName = $student->student->guardian->full_name ?? '-';
    $guardianPhone = $student->student->guardian->mobile ?? '-';
    $studentAddress = $student->permanent_address ?? ($student->current_address ?? $schoolAddress);

    // Terms text lines
    $rawTerms = $settings['student_id_card_back_terms'] ?? "1. This card is non-transferable and remains school property.\n2. Card must be carried and displayed during school hours.\n3. Loss of card must be reported immediately to school office.\n4. Misuse or alteration is strictly prohibited.";
    $termLines = array_filter(array_map('trim', explode("\n", $rawTerms)));
@endphp

<div class="id-card-wrapper {{ $isLandscape ? 'card-horizontal' : 'card-vertical' }}" style="background-color: {{ $bodyBg }}; border: 1px solid {{ $cardBorder }}; position: relative; overflow: hidden; box-sizing: border-box;">

    @if ($isLandscape)
        {{-- =========================================================================
             HORIZONTAL BACKSIDE (85.6mm x 54mm)
             ========================================================================= --}}
        <!-- Header -->
        <table style="width: 100%; border-collapse: collapse; background: {{ $primary }};">
            <tr>
                <td style="padding: 3px 6px; width: 45%; color: #ffffff; font-size: 7pt; font-weight: bold; text-transform: uppercase;">
                    {{ $schoolName }}
                </td>
                <td style="padding: 3px 6px; text-align: right; color: {{ $accent }}; font-size: 5.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">
                    {{ __('TERMS & EMERGENCY DETAILS') }}
                </td>
            </tr>
        </table>
        <div style="height: 2px; background: {{ $accent }}; width: 100%;"></div>

        <!-- Body: 2 Columns (Terms on Left, Emergency & Signatures on Right) -->
        <table style="width: 100%; border-collapse: collapse; font-size: 5.5pt; margin-top: 3px;">
            <tr>
                <!-- Left Column: School Rules / Terms -->
                <td style="width: 55%; vertical-align: top; padding: 2px 5px; border-right: 1px dashed #CBD5E1;">
                    <div style="font-weight: bold; color: {{ $primary }}; font-size: 6pt; margin-bottom: 2px;">
                        <span style="border-bottom: 1px solid {{ $accent }};">{{ __('INSTRUCTIONS & RULES') }}</span>
                    </div>
                    <ul style="margin: 0; padding-left: 10px; line-height: 1.25; color: #334155;">
                        @foreach ($termLines as $line)
                            <li style="margin-bottom: 1.5px;">{{ $line }}</li>
                        @endforeach
                    </ul>

                    <!-- Return Notice -->
                    <div style="margin-top: 4px; padding: 2px 4px; background: #F1F5F9; border-radius: 2px; border-left: 2px solid {{ $accent }}; font-size: 4.8pt; color: #475569;">
                        <strong>{{ __('If found, please return to:') }}</strong><br>
                        {{ $schoolAddress }} &bull; Tel: {{ $schoolPhone }}
                    </div>
                </td>

                <!-- Right Column: Emergency Info & Signatures -->
                <td style="width: 45%; vertical-align: top; padding: 2px 6px;">
                    <!-- Emergency Box -->
                    <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 3px; padding: 2px 4px; margin-bottom: 4px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 5.2pt;">
                            <tr>
                                <td style="color: #991B1B; font-weight: bold;">{{ __('Blood Group:') }}</td>
                                <td style="text-align: right; color: #DC2626; font-weight: bold; font-size: 6.5pt;">{{ $bloodGroup }}</td>
                            </tr>
                            <tr>
                                <td style="color: #64748B;">{{ __('Emergency:') }}</td>
                                <td style="text-align: right; font-weight: bold; color: #0F172A;">{{ $guardianPhone }}</td>
                            </tr>
                            <tr>
                                <td style="color: #64748B;">{{ __('Guardian:') }}</td>
                                <td style="text-align: right; color: #0F172A;">{{ $guardianName }}</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Signatures -->
                    <table style="width: 100%; border-collapse: collapse; margin-top: 6px; text-align: center;">
                        <tr>
                            <td style="width: 50%; vertical-align: bottom; padding: 0 2px;">
                                <div style="height: 18px; border-bottom: 1px dashed #94A3B8;"></div>
                                <div style="font-size: 4.5pt; color: #64748B; margin-top: 1px;">{{ __('Parent Signature') }}</div>
                            </td>
                            <td style="width: 50%; vertical-align: bottom; padding: 0 2px;">
                                @if ($sigPath)
                                    <img height="18" src="{{ $sigPath }}" alt="Sig" style="display:block; margin: 0 auto;" />
                                @else
                                    <div style="height: 18px; border-bottom: 1px solid #0F172A;"></div>
                                @endif
                                <div style="font-size: 4.5pt; font-weight: bold; color: {{ $primary }}; margin-top: 1px;">{{ __('Principal') }}</div>
                            </td>
                        </tr>
                    </table>

                    <!-- Barcode Strip -->
                    <div style="text-align: center; margin-top: 4px;">
                        <div style="font-family: 'Courier', monospace; font-size: 7.5pt; letter-spacing: 2.5px; font-weight: bold; color: #0F172A;">
                            *{{ $admNo }}*
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <div style="position: absolute; bottom: 0; left: 0; width: 100%; background: {{ $primary }}; color: #ffffff; text-align: center; font-size: 4.5pt; padding: 2px 4px; border-top: 1px solid {{ $accent }};">
            {{ $schoolEmail }} &bull; {{ $schoolPhone }} &bull; {{ __('Valid Academic Year:') }} {{ $sessionName }}
        </div>

    @else
        {{-- =========================================================================
             VERTICAL BACKSIDE (54mm x 85.6mm)
             ========================================================================= --}}
        <!-- Header -->
        <div style="background: {{ $primary }}; color: #ffffff; text-align: center; padding: 4px 6px;">
            <div style="font-size: 7.5pt; font-weight: bold; text-transform: uppercase; line-height: 1.1;">
                {{ $schoolName }}
            </div>
            <div style="font-size: 5pt; color: {{ $accent }}; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 1px;">
                {{ __('STUDENT IDENTITY BADGE &bull; TERMS') }}
            </div>
        </div>
        <div style="height: 2px; background: {{ $accent }}; width: 100%;"></div>

        <!-- Inner Content Container -->
        <div style="padding: 4px 7px 0 7px; font-size: 5.5pt; color: #1E293B;">

            <!-- Emergency & Medical Box -->
            <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 3px; padding: 3px 5px; margin-bottom: 4px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 5.2pt;">
                    <tr>
                        <td style="color: #991B1B; font-weight: bold;">{{ __('Blood Group:') }}</td>
                        <td style="text-align: right; color: #DC2626; font-weight: bold; font-size: 7pt;">{{ $bloodGroup }}</td>
                    </tr>
                    <tr>
                        <td style="color: #64748B;">{{ __('Emergency Phone:') }}</td>
                        <td style="text-align: right; font-weight: bold; color: #0F172A;">{{ $guardianPhone }}</td>
                    </tr>
                    <tr>
                        <td style="color: #64748B;">{{ __('Guardian Name:') }}</td>
                        <td style="text-align: right; color: #0F172A;">{{ $guardianName }}</td>
                    </tr>
                </table>
            </div>

            <!-- Rules & Terms List -->
            <div style="margin-bottom: 4px;">
                <div style="font-weight: bold; color: {{ $primary }}; font-size: 5.8pt; margin-bottom: 2px; border-bottom: 1px solid #E2E8F0; padding-bottom: 1px;">
                    <i class="fa fa-info-circle"></i> {{ __('RULES & REGULATIONS:') }}
                </div>
                <ul style="margin: 0; padding-left: 9px; line-height: 1.3; color: #334155; font-size: 5pt;">
                    @foreach ($termLines as $line)
                        <li style="margin-bottom: 1.5px;">{{ $line }}</li>
                    @endforeach
                </ul>
            </div>

            <!-- Return Instructions -->
            <div style="background: #F8FAFC; border: 1px dashed #CBD5E1; border-radius: 3px; padding: 3px 5px; font-size: 4.8pt; color: #475569; margin-bottom: 5px; text-align: center;">
                <strong>{{ __('IF FOUND, PLEASE RETURN TO:') }}</strong><br>
                {{ $schoolAddress }}<br>
                Tel: {{ $schoolPhone }}
            </div>

            <!-- Signatures Section -->
            <table style="width: 100%; border-collapse: collapse; text-align: center; margin-top: 2px;">
                <tr>
                    <td style="width: 50%; vertical-align: bottom; padding: 0 4px;">
                        <div style="height: 16px; border-bottom: 1px dashed #94A3B8;"></div>
                        <div style="font-size: 4.5pt; color: #64748B; margin-top: 1px;">{{ __('Parent Signature') }}</div>
                    </td>
                    <td style="width: 50%; vertical-align: bottom; padding: 0 4px;">
                        @if ($sigPath)
                            <img height="16" src="{{ $sigPath }}" alt="Signature" style="display:block; margin: 0 auto;" />
                        @else
                            <div style="height: 16px; border-bottom: 1px solid #0F172A;"></div>
                        @endif
                        <div style="font-size: 4.5pt; font-weight: bold; color: {{ $primary }}; margin-top: 1px;">{{ __('Principal') }}</div>
                    </td>
                </tr>
            </table>

            <!-- Barcode & ID Strip -->
            <div style="text-align: center; margin-top: 5px;">
                <div style="font-family: 'Courier', monospace; font-size: 7.5pt; letter-spacing: 2px; font-weight: bold; color: #0F172A; line-height: 1;">
                    *{{ $admNo }}*
                </div>
                <div style="font-size: 4.5pt; color: #94A3B8; margin-top: 1px;">
                    STUDENT ID: {{ $admNo }} &bull; ROLL: #{{ $rollNo }}
                </div>
            </div>
        </div>

        <!-- Card Footer -->
        <div style="position: absolute; bottom: 0; left: 0; width: 100%; background: {{ $primary }}; color: #ffffff; text-align: center; font-size: 4.8pt; padding: 2.5px 4px; border-top: 1px solid {{ $accent }}; line-height: 1.1;">
            {{ $schoolEmail }} &bull; {{ __('Academic Session:') }} {{ $sessionName }}
        </div>
    @endif

</div>
