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
        $cardSide = $card_side ?? ($settings['student_id_card_card_side'] ?? 'both'); // 'both', 'front', 'back'
    @endphp

    <style>
        @page {
            margin: 0 !important;
            padding: 0 !important;
        }
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
            height: 0px !important;
            line-height: 0px !important;
            font-size: 0px !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Base Card Styling */
        .id-card-wrapper {
            border: 1px solid {{ $theme['border'] }};
            position: relative;
            overflow: hidden;
            border-radius: 4px;
            box-sizing: border-box !important;
        }

        /* Dimension for Single PVC mode - Sized to fit precisely inside CR80 page without overflowing */
        .single-card-mode .id-card-wrapper {
            width: 53.5mm !important;
            max-width: 53.5mm !important;
            height: 85mm !important;
            max-height: 85mm !important;
            margin: 0 auto !important;
            overflow: hidden !important;
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
        }
        .single-card-mode .card-horizontal {
            width: 85mm !important;
            max-width: 85mm !important;
            height: 53.5mm !important;
            max-height: 53.5mm !important;
        }

        /* Physical Dimensions for A4 / A3 sheets (CR80 standard 85.6mm x 54mm) */
        .card-horizontal {
            width: 85mm;
            height: 53.5mm;
        }
        .card-vertical {
            width: 53.5mm;
            height: 85mm;
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
             Supports Front, Back, or Both (consecutive pages for duplex printing)
             ========================================================================= --}}
        @php
            $cardsToRender = [];
            foreach ($students as $student) {
                if ($cardSide === 'front' || $cardSide === 'both') {
                    $cardsToRender[] = ['type' => 'front', 'student' => $student];
                }
                if ($cardSide === 'back' || $cardSide === 'both') {
                    $cardsToRender[] = ['type' => 'back', 'student' => $student];
                }
            }
            $totalCards = count($cardsToRender);
        @endphp

        @foreach ($cardsToRender as $idx => $item)
            @if ($item['type'] === 'front')
                @include('students.id_card_item', [
                    'student' => $item['student'],
                    'settings' => $settings,
                    'sessionYear' => $sessionYear,
                    'valid_until' => $valid_until,
                    'theme' => $theme,
                    'isLandscape' => $isLandscape,
                    'design' => $selectedDesign
                ])
            @else
                @include('students.id_card_back', [
                    'student' => $item['student'],
                    'settings' => $settings,
                    'sessionYear' => $sessionYear,
                    'valid_until' => $valid_until,
                    'theme' => $theme,
                    'isLandscape' => $isLandscape,
                    'design' => $selectedDesign
                ])
            @endif

            @if ($idx < $totalCards - 1)
                <div class="page-break"></div>
            @endif
        @endforeach

    @elseif ($printFormat === 'a4' || $printFormat === 'a3')
        {{-- =========================================================================
             FORMAT 2 & 3: COMBINED SHEETS (A4 or A3)
             ========================================================================= --}}
        @php
            if ($printFormat === 'a4') {
                $cols = $isLandscape ? 2 : 3;
                $rows = $isLandscape ? 4 : 3;
                $sheetWidth = '190mm';
                $sheetMargin = '4mm auto';
            } else {
                $cols = $isLandscape ? 3 : 4;
                $rows = $isLandscape ? 6 : 4;
                $sheetWidth = '275mm';
                $sheetMargin = '5mm auto';
            }
            $perPage = $cols * $rows;
            $chunks = $students->chunk($perPage);
            $totalChunks = $chunks->count();
        @endphp

        @foreach ($chunks as $chunkIdx => $chunk)
            @php $chunkItems = $chunk->values(); @endphp

            {{-- 1. Front Side Sheet --}}
            @if ($cardSide === 'front' || $cardSide === 'both')
                <table class="sheet-table" style="width: {{ $sheetWidth }}; margin: {{ $sheetMargin }};">
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

                @if ($cardSide === 'both' || $chunkIdx < $totalChunks - 1)
                    <div class="page-break"></div>
                @endif
            @endif

            {{-- 2. Back Side Sheet (Mirrored horizontally so front and back align when printed on duplex paper) --}}
            @if ($cardSide === 'back' || $cardSide === 'both')
                <table class="sheet-table" style="width: {{ $sheetWidth }}; margin: {{ $sheetMargin }};">
                    @for ($r = 0; $r < $rows; $r++)
                        <tr>
                            @for ($c = 0; $c < $cols; $c++)
                                {{-- For duplex printing alignment, mirror columns left-to-right --}}
                                @php 
                                    $mirrorC = ($cardSide === 'both') ? ($cols - 1 - $c) : $c;
                                    $idx = $r * $cols + $mirrorC; 
                                @endphp
                                <td class="cut-cell {{ $showGuides ? 'cut-cell-guided' : '' }}" style="width: {{ 100 / $cols }}%;">
                                    @if (isset($chunkItems[$idx]))
                                        @include('students.id_card_back', [
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

                @if ($chunkIdx < $totalChunks - 1)
                    <div class="page-break"></div>
                @endif
            @endif
        @endforeach
    @endif

</body>
</html>