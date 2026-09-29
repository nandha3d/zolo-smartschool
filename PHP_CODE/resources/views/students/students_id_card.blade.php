<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student ID Cards</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    @php
        $selectedDesign = $template_design ?? ($template_id ?? 'wave');
        // Map legacy template IDs if provided
        $legacyMap = [
            'v1' => 'wave', 'v2' => 'classic', 'v3' => 'diamond', 'v4' => 'diagonal',
            'v5' => 'hexagon', 'v6' => 'minimal', 'v7' => 'swoosh', 'v8' => 'chevron',
            'v9' => 'wave', 'v10' => 'classic',
            'h1' => 'h_split', 'h2' => 'h_wave', 'h3' => 'h_diagonal', 'h4' => 'h_classic',
            'h5' => 'h_split', 'h6' => 'h_minimal', 'h7' => 'h_wave', 'h8' => 'h_diagonal',
            'h9' => 'h_classic', 'h10' => 'h_minimal',
        ];
        if (isset($legacyMap[$selectedDesign])) {
            $selectedDesign = $legacyMap[$selectedDesign];
        }

        $primary = $primary_color ?? '#1E293B';
        $accent = $accent_color ?? '#06B6D4';

        $theme = [
            'primary'      => $primary,
            'accent'       => $accent,
            'text'         => '#FFFFFF',
            'body_bg'      => '#FFFFFF',
            'border'       => '#CBD5E1',
            'photo_border' => $accent,
        ];

        $isLandscape = str_starts_with($selectedDesign, 'h');
        $printFormat = $print_format ?? 'single'; // 'single', 'a4', 'a3'
        $showGuides = !empty($show_cut_guides);
    @endphp

    <style>
        * {
            box-sizing: border-box;
            font-family: 'DejaVu Sans', sans-serif;
        }
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff;
        }
        .page-break {
            page-break-after: always;
        }

        /* Base Card Styling */
        .id-card-wrapper {
            border: 1px solid {{ $theme['border'] }};
            position: relative;
            overflow: hidden;
            border-radius: 4px;
        }

        /* Dimension for Single PVC mode */
        .single-card-mode .id-card-wrapper {
            width: 100%;
            height: 100%;
        }

        /* Physical Dimensions for A4 / A3 sheets (CR80 standard 85.6mm x 54mm) */
        .card-horizontal {
            width: 85.6mm;
            height: 54mm;
        }
        .card-vertical {
            width: 54mm;
            height: 85.6mm;
        }

        /* Sheet Table Grid */
        .sheet-table {
            border-collapse: collapse;
            margin: 0 auto;
        }
        .cut-cell {
            vertical-align: middle;
            text-align: center;
            padding: 2.5mm;
        }
        .cut-cell-guided {
            border: 1px dashed #94A3B8;
        }

        /* Card Header */
        .card-header-table {
            width: 100%;
            border-collapse: collapse;
            background-color: {{ $theme['primary'] }};
            border-bottom: 2px solid {{ $theme['accent'] }};
        }
        .header-logo-cell {
            padding: 4px 6px;
            vertical-align: middle;
            width: 30%;
        }
        .header-school-cell {
            padding: 4px 6px;
            text-align: right;
            vertical-align: middle;
            color: {{ $theme['text'] }};
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .card-title-bar {
            background: {{ $theme['accent'] }};
            color: #ffffff;
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 2px 5px;
            text-align: center;
        }

        /* Card Footer */
        .card-footer-table {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: {{ $theme['primary'] }};
            border-top: 1.5px solid {{ $theme['accent'] }};
            color: {{ $theme['text'] }};
            font-size: 5.5pt;
            padding: 2.5px 6px;
        }

        /* Photo Styles */
        .student-photo-frame {
            border: 1.5px solid {{ $theme['photo_border'] }};
            border-radius: {{ (isset($settings['profile_image_style']) && $settings['profile_image_style'] == 'round') ? '50%' : '4px' }};
            object-fit: cover;
        }

        /* Data Fields Table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5pt;
            line-height: 1.25;
        }
        .info-label {
            width: 35%;
            font-weight: bold;
            color: {{ !empty($theme['dark']) ? '#94A3B8' : '#475569' }};
            padding: 1px 2px;
            text-align: left;
        }
        .info-value {
            width: 65%;
            color: {{ !empty($theme['dark']) ? '#F8FAFC' : '#0F172A' }};
            padding: 1px 2px;
            text-align: left;
        }

        .barcode-table {
            border-collapse: collapse;
            margin: 0;
        }

        .watermark-bg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.08;
            width: 120px;
            z-index: 0;
        }
    </style>
</head>
<body class="{{ $printFormat == 'single' ? 'single-card-mode' : '' }}">

    @if ($printFormat === 'single')
        {{-- =========================================================================
             FORMAT 1: SINGLE CARD PER PAGE (CR80 / Dedicated PVC Card Printer)
             ========================================================================= --}}
        @foreach ($students as $key => $student)
            @include('students.id_card_item', [
                'student' => $student,
                'settings' => $settings,
                'sessionYear' => $sessionYear,
                'valid_until' => $valid_until,
                'theme' => $theme,
                'isLandscape' => $isLandscape,
                'design' => $selectedDesign
            ])

            @if (!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach

    @elseif ($printFormat === 'a4')
        {{-- =========================================================================
             FORMAT 2: COMBINED A4 SHEET (8–9 Cards Per Page with Cutting Guides)
             ========================================================================= --}}
        @php
            // A4 Layout:
            // Horizontal cards: 2 columns x 4 rows = 8 cards / page
            // Vertical cards: 3 columns x 3 rows = 9 cards / page
            $cols = $isLandscape ? 2 : 3;
            $rows = $isLandscape ? 4 : 3;
            $perPage = $cols * $rows;
            $chunks = $students->chunk($perPage);
        @endphp

        @foreach ($chunks as $chunk)
            @php $chunkItems = $chunk->values(); @endphp
            <table class="sheet-table" style="width: 190mm; margin: 4mm auto;">
                @for ($r = 0; $r < $rows; $r++)
                    <tr>
                        @for ($c = 0; $c < $cols; $c++)
                            @php $idx = $r * $cols + $c; @endphp
                            <td class="cut-cell {{ $showGuides ? 'cut-cell-guided' : '' }}" style="width: {{ 100 / $cols }}%;">
                                @if (isset($chunkItems[$idx]))
                                    @include('students.id_card_item', [
                                        'student' => $chunkItems[$idx],
                                        'settings' => $settings,
                                        'sessionYear' => $sessionYear,
                                        'valid_until' => $valid_until,
                                        'theme' => $theme,
                                        'isLandscape' => $isLandscape,
                                        'design' => $selectedDesign
                                    ])
                                @else
                                    <div style="height: {{ $isLandscape ? '54mm' : '85.6mm' }};"></div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endfor
            </table>

            @if (!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach

    @elseif ($printFormat === 'a3')
        {{-- =========================================================================
             FORMAT 3: COMBINED A3 SHEET (16–18 Cards Per Page for Press Printing)
             ========================================================================= --}}
        @php
            // A3 Layout:
            // Horizontal cards: 3 columns x 6 rows = 18 cards / page
            // Vertical cards: 4 columns x 4 rows = 16 cards / page
            $cols = $isLandscape ? 3 : 4;
            $rows = $isLandscape ? 6 : 4;
            $perPage = $cols * $rows;
            $chunks = $students->chunk($perPage);
        @endphp

        @foreach ($chunks as $chunk)
            @php $chunkItems = $chunk->values(); @endphp
            <table class="sheet-table" style="width: 275mm; margin: 5mm auto;">
                @for ($r = 0; $r < $rows; $r++)
                    <tr>
                        @for ($c = 0; $c < $cols; $c++)
                            @php $idx = $r * $cols + $c; @endphp
                            <td class="cut-cell {{ $showGuides ? 'cut-cell-guided' : '' }}" style="width: {{ 100 / $cols }}%;">
                                @if (isset($chunkItems[$idx]))
                                    @include('students.id_card_item', [
                                        'student' => $chunkItems[$idx],
                                        'settings' => $settings,
                                        'sessionYear' => $sessionYear,
                                        'valid_until' => $valid_until,
                                        'theme' => $theme,
                                        'isLandscape' => $isLandscape,
                                        'design' => $selectedDesign
                                    ])
                                @else
                                    <div style="height: {{ $isLandscape ? '54mm' : '85.6mm' }};"></div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endfor
            </table>

            @if (!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach
    @endif

</body>
</html>