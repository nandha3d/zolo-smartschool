@extends('layouts.master')

@section('title')
    {{ __('View Teacher Timetable') }}
@endsection

@section('css')
    <style>
        .tt-grid { table-layout: fixed; min-width: 760px; }
        .tt-grid th, .tt-grid td { vertical-align: top; padding: 6px; }
        .tt-grid .tt-time { width: 120px; background: #f8fafc; }
        .tt-grid .tt-time .small { color: #64748b; }
        .tt-break td { background: repeating-linear-gradient(45deg, #f1f5f9, #f1f5f9 8px, #e2e8f0 8px, #e2e8f0 16px); text-align: center; color: #475569; font-size: 12px; padding: 3px; }
        .tt-card { display: block; width: 100%; text-align: left; border: 0; border-left: 4px solid rgba(0,0,0,.25); border-radius: 6px; padding: 6px 8px; color: #fff; margin-bottom: 4px; cursor: pointer; line-height: 1.25; }
        .tt-card:hover, .tt-card:focus { filter: brightness(1.08); outline: 2px solid #1e293b; }
        .tt-card .tt-sub { font-weight: 600; font-size: 13px; }
        .tt-card .tt-meta { font-size: 11px; opacity: .92; }
        .tt-today { background: #eff6ff !important; }
    </style>
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">{{ __('View Teacher Timetable') }}</h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <h3 class="mb-1">{{ $teacher->full_name }}</h3>
                            <small class="text-muted">{{ __('Click a period for attendance, the class diary, the class roster or a gate pass.') }}</small>
                        </div>

                        @if (empty($grid['rows']))
                            <p class="text-center text-muted p-4">{{ __('No periods are scheduled for this teacher.') }}</p>
                        @else
                            @php
                                $todayName = now()->format('l');
                                $palette = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#65a30d', '#dc2626'];
                            @endphp
                            <div class="table-responsive">
                                <table class="table table-bordered tt-grid">
                                    <thead>
                                    <tr>
                                        <th class="tt-time">{{ __('Period') }}</th>
                                        @foreach ($grid['days'] as $day)
                                            <th class="text-center {{ $day === $todayName ? 'tt-today' : '' }}">{{ __($day) }}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($grid['rows'] as $i => $row)
                                        @if ($row['is_break'])
                                            <tr class="tt-break">
                                                <td class="tt-time"><b>{{ $row['label'] }}</b><div class="small">{{ $row['start'] }} - {{ $row['end'] }}</div></td>
                                                <td colspan="{{ count($grid['days']) }}">{{ $row['label'] }} · {{ $row['start'] }} - {{ $row['end'] }}</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td class="tt-time"><b>{{ $row['label'] }}</b><div class="small">{{ $row['start'] }} - {{ $row['end'] }}</div></td>
                                                @foreach ($grid['days'] as $day)
                                                    <td class="{{ $day === $todayName ? 'tt-today' : '' }}">
                                                        @foreach ($grid['cells'][$i][$day] ?? [] as $lesson)
                                                            @php
                                                                $color = $lesson->subject?->bg_color ?: $palette[$lesson->subject_id % count($palette)];
                                                            @endphp
                                                            <button type="button" class="tt-card" style="background: {{ $color }};"
                                                                    data-id="{{ $lesson->id }}" aria-label="{{ $lesson->subject?->name }} {{ $lesson->class_section?->full_name }}">
                                                                <div class="tt-sub">{{ $lesson->subject?->name ?? __('Lecture') }}</div>
                                                                <div class="tt-meta">{{ $lesson->class_section?->full_name }}</div>
                                                                <div class="tt-meta">{{ substr($lesson->start_time, 0, 5) }}-{{ substr($lesson->end_time, 0, 5) }}@if ($lesson->resource) · {{ $lesson->resource->name }}@endif</div>
                                                            </button>
                                                        @endforeach
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endif
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($proxyCover->isNotEmpty())
                            <h5 class="mt-4">{{ __('Proxy periods this week') }}</h5>
                            <div class="d-flex flex-wrap" style="gap:8px;">
                                @foreach ($proxyCover as $p)
                                    <button type="button" class="tt-card" style="background:#475569; width:auto; max-width:260px;"
                                            data-id="{{ $p->timetable_id }}" data-date="{{ $p->date->toDateString() }}">
                                        <div class="tt-sub">{{ $p->date->format('D d M') }} · {{ $p->subject?->name }}</div>
                                        <div class="tt-meta">{{ $p->classSection?->full_name }} · {{ substr((string) $p->timetable?->start_time, 0, 5) }}-{{ substr((string) $p->timetable?->end_time, 0, 5) }}</div>
                                        <div class="tt-meta">{{ __('for') }} {{ $p->absentTeacher?->full_name }}</div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="periodActionModal" tabindex="-1" role="dialog" aria-labelledby="periodModalTitle">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-theme text-white py-2">
                    <h5 class="modal-title" id="periodModalTitle">{{ __('Period') }}</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="{{ __('Close') }}">&times;</button>
                </div>
                <div class="modal-body p-3">
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="font-weight-bold mb-0" id="pm-subject"></h6>
                                <span class="badge badge-primary" id="pm-time"></span>
                            </div>
                            <div class="text-muted small" id="pm-meta"></div>
                            <div class="small mt-1" id="pm-status"></div>
                        </div>
                    </div>
                    <div class="list-group" id="pm-actions"></div>
                    <form id="pm-gate" class="mt-3 border rounded p-2" style="display:none;">
                        <div class="form-group mb-2">
                            <label class="small mb-0" for="gp-student">{{ __('Student') }}</label>
                            <select id="gp-student" class="form-control form-control-sm" required></select>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-7 mb-2">
                                <label class="small mb-0" for="gp-type">{{ __('Pass') }}</label>
                                <select id="gp-type" class="form-control form-control-sm">
                                    <option value="infirmary_visit">{{ __('Infirmary') }}</option>
                                    <option value="library_pass">{{ __('Library') }}</option>
                                    <option value="principal_office">{{ __('Principal office') }}</option>
                                    <option value="sports_duty">{{ __('Sports duty') }}</option>
                                    <option value="gate_pass_exit">{{ __('Leaving campus (gate pass)') }}</option>
                                </select>
                            </div>
                            <div class="form-group col-5 mb-2">
                                <label class="small mb-0" for="gp-return">{{ __('Back by') }}</label>
                                <input type="time" id="gp-return" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="form-row gp-pickup" style="display:none;">
                            <div class="form-group col-5 mb-2"><input id="gp-person" class="form-control form-control-sm" maxlength="191" placeholder="{{ __('Picked up by') }}"></div>
                            <div class="form-group col-3 mb-2"><input id="gp-relation" class="form-control form-control-sm" maxlength="50" placeholder="{{ __('Relation') }}"></div>
                            <div class="form-group col-4 mb-2"><input id="gp-phone" class="form-control form-control-sm" maxlength="30" placeholder="{{ __('Phone') }}"></div>
                        </div>
                        <div class="form-group mb-2">
                            <textarea id="gp-reason" class="form-control form-control-sm" rows="2" maxlength="1000" placeholder="{{ __('Reason') }}" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm">{{ __('Record pass') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        (function () {
            var slotUrl = @json(route('period-attendance.slot'));
            var studentsUrl = @json(route('period-attendance.students'));
            var current = null;

            function action(icon, color, title, hint, href, id) {
                return '<a href="' + (href || '#') + '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2"' +
                    (id ? ' id="' + id + '"' : '') + '><div><i class="fa ' + icon + ' text-' + color + ' mr-2"></i><strong></strong>' +
                    '<div class="text-muted small"></div></div><i class="fa fa-chevron-right text-muted"></i></a>';
            }

            $(document).on('click', '.tt-card', function () {
                var params = {timetable_id: $(this).data('id')};
                if ($(this).data('date')) { params.date = $(this).data('date'); }
                $('#pm-gate').hide();
                $('#pm-actions').html('<div class="text-muted p-2">' + @json(__('Loading...')) + '</div>');
                $('#periodActionModal').modal('show');

                $.get(slotUrl, params).done(function (s) {
                    if (s.error) { $('#pm-actions').html('<div class="text-danger p-2"></div>').find('div').text(s.message); return; }
                    current = s;
                    $('#periodModalTitle').text(s.date_label + (s.period ? ' · P' + s.period : ''));
                    $('#pm-subject').text(s.subject);
                    $('#pm-time').text(s.time);
                    $('#pm-meta').text([s.class, s.room ? @json(__('Room')) + ': ' + s.room : null, s.teacher].filter(Boolean).join(' · '));
                    $('#pm-status').text(s.can_mark ? (s.marked ? s.marked + ' ' + @json(__('students marked for this period')) : @json(__('Attendance not taken yet')))
                        : @json(__('You are not assigned to this period, so you can only view it.')));

                    var items = [];
                    if (s.links.attendance) { items.push([action('fa-check-square-o', 'success', '', '', s.links.attendance), @json(__('Mark / edit period attendance')), @json(__('Present, absent or late for this period'))]); }
                    if (s.links.diary) { items.push([action('fa-book', 'primary', '', '', s.links.diary), @json(__('Class diary / lesson log')), @json(__('Record what was taught and homework'))]); }
                    if (s.links.roster) { items.push([action('fa-users', 'info', '', '', s.links.roster), @json(__('Class roster & student leaders')), @json(__('Monitors, bench leaders and groups'))]); }
                    if (s.links.gate_pass) { items.push([action('fa-sign-out', 'warning', '', '', '#', 'pm-gate-link'), @json(__('Issue gate pass / infirmary slip')), @json(__('Log a student leaving class'))]); }

                    var list = $('#pm-actions').empty();
                    if (!items.length) { list.html('<div class="text-muted small p-2">' + @json(__('No actions available.')) + '</div>'); }
                    items.forEach(function (it) {
                        var el = $(it[0]);
                        el.find('strong').text(it[1]);
                        el.find('.small').text(it[2]);
                        list.append(el);
                    });
                });
            });

            $(document).on('click', '#pm-gate-link', function (e) {
                e.preventDefault();
                if (!current) { return; }
                $('#gp-student').html('<option value="">' + @json(__('Loading...')) + '</option>');
                $('#pm-gate').show();
                $.get(studentsUrl, {timetable_id: current.id}).done(function (res) {
                    var sel = $('#gp-student').empty().append($('<option value="">').text(@json(__('Select student'))));
                    (res.students || []).forEach(function (st) { sel.append($('<option>').val(st.id).text(st.name)); });
                }).fail(function (xhr) {
                    $('#gp-student').html('<option value="">' + @json(__('Only for today\'s periods')) + '</option>');
                });
            });

            $('#gp-type').on('change', function () { $('.gp-pickup').toggle(this.value === 'gate_pass_exit'); });

            $('#pm-gate').on('submit', function (e) {
                e.preventDefault();
                var btn = $(this).find('button[type=submit]').prop('disabled', true);
                $.post(@json(route('period-attendance.gate-pass')), {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    timetable_id: current.id,
                    student_id: $('#gp-student').val(),
                    movement_type: $('#gp-type').val(),
                    expected_return_time: $('#gp-return').val(),
                    pickup_person_name: $('#gp-person').val(),
                    pickup_person_relationship: $('#gp-relation').val(),
                    pickup_person_phone: $('#gp-phone').val(),
                    reason: $('#gp-reason').val()
                }).done(function (res) {
                    (res.error ? showErrorToast : showSuccessToast)(res.message);
                    if (!res.error) { $('#pm-gate')[0].reset(); $('#pm-gate').hide(); }
                }).fail(function (xhr) {
                    showErrorToast((xhr.responseJSON && xhr.responseJSON.message) || 'Error');
                }).always(function () { btn.prop('disabled', false); });
            });
        })();
    </script>
@endsection
