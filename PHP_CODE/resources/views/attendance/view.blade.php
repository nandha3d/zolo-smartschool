@extends('layouts.master')

@section('title')
    {{ __('attendance') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header">
            <h3 class="page-title">
                {{ __('manage').' '.__('attendance') }}
            </h3>
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">
                            {{ __('view').' '.__('attendance') }}
                        </h4>
                        <div class="row" id="toolbar">
                            <div class="form-group col-sm-12 col-md-4">
                                <select required name="class_section_id" id="timetable_class_section" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    <option value="">{{__('select')}}</option>
                                    @foreach($class_sections as $section)
                                        <option value="{{$section->id}}" data-class="{{$section->class->id}}">{{$section->full_name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-sm-12 col-md-4">
                                {!! Form::text('date', date('d-m-Y'), ['required', 'placeholder' => __('date'), 'class' => 'datepicker-popup form-control','id'=>'date','data-date-end-date'=>"0d",'autocomplete'=>'off']) !!}
                                <span class="input-group-addon input-group-append">
                            </span>
                            </div>
                            <div class="form-group col-sm-12 col-md-4">
                                <select required name="attendance_type" id="attendance_type" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                    <option value="">{{__('select')}}</option>
                                    <option value="1">{{__('present')}}</option>
                                    <option value="0">{{__('absent')}}</option>
                                    <option value="3">{{__('holiday')}}</option>

                                </select>
                            </div>
                        </div>

                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-daily" role="tab">{{ __('Daily attendance') }}</a></li>
                            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-periods" role="tab" id="tab-periods-link">{{ __('Period-wise') }}</a></li>
                        </ul>

                        <div class="tab-content border-0 p-0">
                            <div class="tab-pane fade show active show_attendance_student_list" id="tab-daily" role="tabpanel">
                                <table aria-describedby="mydesc" class='table student_table' id='table_list'
                                       data-toggle="table" data-url="{{ route('attendance.list.show',1) }}" data-click-to-select="true"
                                       data-side-pagination="server" data-pagination="true"
                                       data-page-list="[5, 10, 20, 50, 100, 200,All]" data-search="true" data-toolbar="#toolbar"
                                       data-show-columns="true" data-show-refresh="true" data-fixed-columns="false"
                                       data-fixed-number="2" data-fixed-right-number="1" data-trim-on-search="false"
                                       data-mobile-responsive="true" data-sort-name="id" data-sort-order="desc"
                                       data-maintain-selected="true" data-export-data-type='all' data-show-export="true"
                                       data-export-options='{ "fileName": "view-attendance-list-<?= date('d-m-y') ?>" ,"ignoreColumn": ["operate"]}'
                                       data-query-params="queryParams" data-escape="true">
                                    <thead>
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true" data-visible="false">{{__('id')}}</th>
                                        <th scope="col" data-field="no">{{__('no.')}}</th>
                                        <th scope="col" data-field="student_id" data-sortable="true" data-visible="false">{{__('user_id')}}</th>
                                        <th scope="col" data-field="user.student.id" data-sortable="true" data-visible="false">{{__('student_id')}}</th>
                                        <th scope="col" data-field="user.student.admission_no" data-sortable="true">{{__('admission_no')}}</th>
                                        <th scope="col" data-field="user.student.roll_number" data-sortable="true">{{__('roll_no')}}</th>
                                        <th scope="col" data-field="user.full_name">{{__('name')}}</th>
                                        <th scope="col" data-field="type" data-formatter="attendanceTypeFormatter" data-escape="false">{{__('type')}}</th>
                                    </tr>
                                    </thead>
                                </table>
                            </div>

                            <div class="tab-pane fade" id="tab-periods" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
                                    <small class="text-muted">
                                        <span class="badge badge-success">P</span> {{ __('present') }}
                                        <span class="badge badge-danger ml-2">A</span> {{ __('absent') }}
                                        <span class="badge badge-warning ml-2">L</span> {{ __('late') }}
                                        <span class="badge badge-info ml-2">E</span> {{ __('excused / activity / infirmary') }}
                                        <span class="badge badge-secondary ml-2">-</span> {{ __('not marked') }}
                                    </small>
                                    <small class="text-muted" id="matrix-hint"></small>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-0" id="period-matrix">
                                        <thead class="bg-light" id="matrix-head"></thead>
                                        <tbody id="matrix-body">
                                        <tr><td class="text-center text-muted p-4">{{ __('Select a class section and date.') }}</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        function queryParams(p) {
            return {
                limit: p.limit,
                sort: p.sort,
                order: p.order,
                offset: p.offset,
                search: p.search,
                'class_section_id': $('#timetable_class_section').val(),
                'date': $('#date').val(),
                'attendance_type': $('#attendance_type').val(),
            };
        }
    </script>

    <script>
        (function () {
            var STORE_KEY = 'zolo_view_attendance_class_section';

            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
                });
            }

            function refreshDaily() {
                if (!$('#timetable_class_section').val() || !$('#date').val()) { return; }
                $('.student_table').bootstrapTable('refresh');
            }

            function loadMatrix() {
                var section = $('#timetable_class_section').val(), date = $('#date').val();
                if (!section || !date) { return; }
                $('#matrix-body').html('<tr><td class="text-center text-muted p-4">' + @json(__('Loading...')) + '</td></tr>');
                $.get(@json(route('attendance.period-matrix')), {class_section_id: section, date: date}).done(function (res) {
                    if (res.error) { $('#matrix-body').html('<tr><td class="text-center text-danger p-3">' + esc(res.message) + '</td></tr>'); return; }
                    renderMatrix(res.periods, res.rows);
                });
            }

            var BADGE = {
                present: '<span class="badge badge-success">P</span>',
                absent: '<span class="badge badge-danger">A</span>',
                late: '<span class="badge badge-warning">L</span>',
                left_early: '<span class="badge badge-warning" title="Left early">LE</span>',
                excused: '<span class="badge badge-info" title="Excused">E</span>',
                infirmary: '<span class="badge badge-info" title="Infirmary">E</span>',
                school_activity: '<span class="badge badge-info" title="School activity">E</span>',
                unmarked: '<span class="badge badge-secondary">-</span>'
            };

            function renderMatrix(periods, rows) {
                var type = $('#attendance_type').val();
                var head = '<tr><th style="width:90px;">' + @json(__('roll_no')) + '</th><th>' + @json(__('name')) + '</th>' +
                    periods.map(function (p) {
                        return '<th class="text-center" style="min-width:80px;"><div>' + esc(p.name) + '</div><div class="text-muted font-weight-normal" style="font-size:10px;">' + esc(p.time_label) + '</div></th>';
                    }).join('') + '<th class="text-center">' + @json(__('Summary')) + '</th></tr>';
                $('#matrix-head').html(head);

                if (!periods.length) {
                    $('#matrix-body').html('<tr><td colspan="3" class="text-center text-muted p-4">' + @json(__('No periods are set up for this day.')) + '</td></tr>');
                    return;
                }
                // The present / absent filter narrows the matrix to students with any such period.
                var shown = rows.filter(function (r) {
                    if (type === '1') { return r.present_count > 0; }
                    if (type === '0') { return Object.keys(r.periods).some(function (k) { return r.periods[k].status === 'absent'; }); }
                    return true;
                });
                if (!shown.length) {
                    $('#matrix-body').html('<tr><td colspan="' + (periods.length + 3) + '" class="text-center text-muted p-4">' + @json(__('No students match.')) + '</td></tr>');
                    return;
                }
                var anyMarked = rows.some(function (r) { return r.marked_count > 0; });
                $('#matrix-hint').text(anyMarked ? '' : @json(__('No period attendance has been taken for this date yet.')));

                $('#matrix-body').html(shown.map(function (r) {
                    var cells = periods.map(function (p) {
                        var c = r.periods['p_' + p.period_number] || {status: 'unmarked'};
                        return '<td class="text-center"' + (c.remarks ? ' title="' + esc(c.remarks) + '"' : '') + '>' + (BADGE[c.status] || esc(c.status)) + '</td>';
                    }).join('');
                    var summary;
                    if (!r.marked_count) { summary = '<span class="text-muted">-</span>'; }
                    else if (r.present_count === r.total_periods) { summary = '<span class="badge badge-success">' + @json(__('Full day')) + '</span>'; }
                    else if (r.present_count === 0) { summary = '<span class="badge badge-danger">' + @json(__('Absent')) + '</span>'; }
                    else { summary = '<span class="badge badge-warning">' + r.present_count + '/' + r.total_periods + '</span>'; }
                    return '<tr><td>' + esc(r.roll_number) + '</td><td>' + esc(r.student_name) + '</td>' + cells + '<td class="text-center">' + summary + '</td></tr>';
                }).join(''));
            }

            function refreshAll() {
                refreshDaily();
                if ($('#tab-periods').hasClass('active')) { loadMatrix(); }
            }

            $(function () {
                // Start from the last class section used here, else the first one, so the
                // page shows data straight away instead of an empty table.
                var select = $('#timetable_class_section');
                var last = null;
                try { last = localStorage.getItem(STORE_KEY); } catch (e) {}
                if (last && select.find('option[value="' + last + '"]').length) {
                    select.val(last);
                } else if (select.find('option').length > 1) {
                    select.prop('selectedIndex', 1);
                }
                select.trigger('change.select2');

                // The class section used to have no listener, so changing it did nothing.
                select.on('change', function () {
                    try { if (this.value) { localStorage.setItem(STORE_KEY, this.value); } } catch (e) {}
                    refreshAll();
                });
                $('#date').on('input change changeDate', refreshAll);
                $('#attendance_type').on('change', refreshAll);
                $('#tab-periods-link').on('shown.bs.tab', loadMatrix);

                refreshDaily();
            });
        })();
    </script>
@endsection
