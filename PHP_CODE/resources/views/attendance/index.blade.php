@extends('layouts.master')

@section('title')
    {{ __('attendance') }}
@endsection

@section('content')
    <script>
        // Define formatters in window scope immediately so Bootstrap Table never falls back to literal string names
        window.attendanceRollFormatter = function(value, row) {
            let roll = value || row.roll_no || '-';
            return '<span class="badge badge-outline-primary font-weight-bold" style="font-size: 13px; border-radius: 6px; padding: 4px 10px;">#' + roll + '</span>';
        };

        window.attendanceStudentFormatter = function(value, row) {
            let name = row.student_name || row.name || 'Student';
            let initials = name.split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase() || 'ST';
            let adm = row.admission_no ? '<span class="text-muted ml-1" style="font-size: 11px;">(Adm: ' + row.admission_no + ')</span>' : '';

            let avatarHtml = '';
            if (row.image) {
                avatarHtml = '<img src="' + row.image + '" alt="' + name + '" class="zolo-att-avatar mr-2" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover;">';
            } else {
                avatarHtml = '<div class="zolo-att-avatar mr-2">' + initials + '</div>';
            }

            return '<div class="d-flex align-items-center">' +
                avatarHtml +
                '<div>' +
                '  <div style="font-weight: 600; font-size: 13.5px; color: #0F172A;">' + name + ' ' + adm + '</div>' +
                '</div>' +
            '</div>';
        };

        window.addRadioInputAttendance = function(value, row) {
            let currentVal = (row.type === 0 || row.type === '0') ? 0 : 1;
            let isPresent = (currentVal === 1);
            let isAbsent = (currentVal === 0);
            let rowIdVal = (row.id ? row.id : '');
            let studentIdVal = (row.student_id ? row.student_id : row.user_id);
            let rowNo = row.no;

            let html = '<div class="zolo-att-btn-group" data-row="' + rowNo + '" data-student-id="' + studentIdVal + '">';
            html += '  <input type="hidden" value="' + rowIdVal + '" name="attendance_data[' + rowNo + '][id]">';
            html += '  <input type="hidden" name="attendance_data[' + rowNo + '][student_id]" value="' + studentIdVal + '">';
            html += '  <input type="hidden" class="attendance-type-input" name="attendance_data[' + rowNo + '][type]" value="' + currentVal + '">';
            html += '  <button type="button" class="zolo-att-btn zolo-att-btn-present ' + (isPresent ? 'is-active' : '') + '" data-val="1" title="Mark Present">';
            html += '    <i class="fa fa-check-circle mr-1"></i> <span>Present</span>';
            html += '  </button>';
            html += '  <button type="button" class="zolo-att-btn zolo-att-btn-absent ' + (isAbsent ? 'is-active' : '') + '" data-val="0" title="Mark Absent">';
            html += '    <i class="fa fa-times-circle mr-1"></i> <span>Absent</span>';
            html += '  </button>';
            html += '</div>';

            return html;
        };

        window.addRemarkInputAttendance = function(value, row) {
            let remarkVal = (row.remark || '').replace(/"/g, '&quot;');
            let rowNo = row.no;
            return '<input type="text" class="form-control form-control-sm zolo-att-remark" name="attendance_data[' + rowNo + '][remark]" value="' + remarkVal + '" placeholder="Add note (e.g. sick, late)..." style="font-size: 12px; border-radius: 6px; min-width: 140px;">';
        };
    </script>

    <div class="content-wrapper">
        <div class="page-header d-flex align-items-center justify-content-between">
            <h3 class="page-title mb-0">
                <span class="page-title-icon bg-theme text-white mr-2">
                    <i class="fa fa-calendar-check-o"></i>
                </span>
                {{ __('attendance_marking') ?? __('manage') . ' ' . __('attendance') }}
            </h3>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('attendance.view') }}" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-eye mr-1"></i> {{ __('view_attendance') }}
                </a>
                <a href="{{ route('attendance.month') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-calendar mr-1"></i> {{ __('month_wise') }}
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        {{-- Class & Date Selection Toolbar --}}
                        <div class="zolo-att-hero mb-3">
                            <div class="row align-items-center" id="toolbar">
                                <div class="form-group col-sm-12 col-md-4 mb-2">
                                    <label class="font-weight-bold text-dark mb-1" for="timetable_class_section">
                                        <i class="fa fa-university text-primary mr-1"></i> {{ __('Class') . ' ' . __('section') }} <span class="text-danger">*</span>
                                    </label>
                                    <select required name="class_section_id" id="timetable_class_section" class="form-control select2" style="width:100%;" tabindex="-1" aria-hidden="true">
                                        <option value="">{{ __('select') . ' ' . __('Class') }}</option>
                                        @foreach ($classSections as $section)
                                            <option value="{{ $section->id }}" data-class="{{ $section->class->id }}">
                                                {{ $section->full_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-sm-12 col-md-4 mb-2">
                                    <label class="font-weight-bold text-dark mb-1" for="date">
                                        <i class="fa fa-calendar text-primary mr-1"></i> {{ __('date') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        {!! Form::text('date', date('d-m-Y'), ['required', 'placeholder' => __('date'), 'class' => 'datepicker-popup form-control', 'id' => 'date', 'data-date-end-date' => '0d']) !!}
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-set-today" title="{{ __('Today') }}">
                                                {{ __('today') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group col-sm-12 col-md-4 mb-2 holiday-div">
                                    <label class="font-weight-bold text-dark mb-1">{{ __('status_override') ?? 'Special Day' }}</label>
                                    <div class="form-check mt-1">
                                        <label class="form-check-label font-weight-bold text-warning" style="font-size: 13px;">
                                            <input type="checkbox" class="form-check-input" name="holiday" id="holiday" value="0">
                                            <i class="fa fa-sun-o mr-1"></i> {{ __('mark_as_holiday') ?? __('holiday') }}
                                            <i class="input-helper"></i>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-12 mt-1">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" name="absent_notification" class="custom-control-input" id="gridCheck" checked>
                                        <label class="custom-control-label cursor-pointer text-muted font-weight-500" for="gridCheck" style="font-size: 12.5px;">
                                            <i class="fa fa-bell-o mr-1 text-primary"></i>
                                            {{ __('send_a_notification_to_the_guardian_if_the_student_is_absent') }}
                                            <span class="badge badge-warning ml-1" id="absent-notification-badge" style="display: none;">0 guardians</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- Live Attendance Stats & Progress Strip --}}
                            <div id="attendance-stats-strip" style="display: none;">
                                <div class="zolo-att-stat-grid">
                                    <div class="zolo-att-stat-card">
                                        <div class="zolo-att-stat-icon total">
                                            <i class="fa fa-users"></i>
                                        </div>
                                        <div>
                                            <div class="zolo-att-stat-label">{{ __('total_students') }}</div>
                                            <div class="zolo-att-stat-value" id="stat-total-students">0</div>
                                        </div>
                                    </div>

                                    <div class="zolo-att-stat-card">
                                        <div class="zolo-att-stat-icon present">
                                            <i class="fa fa-check"></i>
                                        </div>
                                        <div>
                                            <div class="zolo-att-stat-label">{{ __('present') }}</div>
                                            <div class="zolo-att-stat-value text-success" id="stat-present-students">0</div>
                                        </div>
                                    </div>

                                    <div class="zolo-att-stat-card">
                                        <div class="zolo-att-stat-icon absent">
                                            <i class="fa fa-times"></i>
                                        </div>
                                        <div>
                                            <div class="zolo-att-stat-label">{{ __('absent') }}</div>
                                            <div class="zolo-att-stat-value text-danger" id="stat-absent-students">0</div>
                                        </div>
                                    </div>

                                    <div class="zolo-att-stat-card">
                                        <div class="zolo-att-stat-icon rate">
                                            <i class="fa fa-pie-chart"></i>
                                        </div>
                                        <div>
                                            <div class="zolo-att-stat-label">{{ __('attendance_rate') ?? 'Present Rate' }}</div>
                                            <div class="zolo-att-stat-value text-warning" id="stat-attendance-rate">0%</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="zolo-att-progress-bar">
                                    <div class="zolo-att-progress-present" id="stat-bar-present" style="width: 0%;"></div>
                                    <div class="zolo-att-progress-absent" id="stat-bar-absent" style="width: 0%;"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Attendance Form --}}
                        <form action="{{ route('attendance.store') }}" class="create-form attendance-table" id="formdata" method="POST">
                            @csrf
                            <input type="hidden" name="class_section_id" id="hidden_class_section_id">
                            <input type="hidden" name="date" id="hidden_date">

                            {{-- Bulk Action & Quick Filter Bar --}}
                            <div class="zolo-att-actions-bar" id="attendance-quick-actions" style="display: none;">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="text-muted font-weight-bold mr-1" style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;">
                                        <i class="fa fa-bolt mr-1 text-warning"></i> {{ __('quick_actions') ?? 'Quick Actions' }}:
                                    </span>
                                    <button type="button" class="zolo-att-btn-bulk btn-bulk-present" id="btn-mark-all-present">
                                        <i class="fa fa-check-circle"></i> {{ __('mark_all_present') ?? 'Mark All Present' }}
                                    </button>
                                    <button type="button" class="zolo-att-btn-bulk btn-bulk-absent" id="btn-mark-all-absent">
                                        <i class="fa fa-times-circle"></i> {{ __('mark_all_absent') ?? 'Mark All Absent' }}
                                    </button>
                                    <button type="button" class="zolo-att-btn-bulk btn-bulk-invert" id="btn-invert-attendance">
                                        <i class="fa fa-random"></i> {{ __('invert') ?? 'Invert' }}
                                    </button>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <div class="zolo-att-filters" role="tablist">
                                        <div class="zolo-att-filter-item active" data-filter="all" id="filter-tab-all">
                                            {{ __('all') }} (<span id="filter-count-all">0</span>)
                                        </div>
                                        <div class="zolo-att-filter-item" data-filter="present" id="filter-tab-present">
                                            <span class="text-success"><i class="fa fa-circle mr-1" style="font-size: 8px;"></i>{{ __('present') }}</span> (<span id="filter-count-present">0</span>)
                                        </div>
                                        <div class="zolo-att-filter-item" data-filter="absent" id="filter-tab-absent">
                                            <span class="text-danger"><i class="fa fa-circle mr-1" style="font-size: 8px;"></i>{{ __('absent') }}</span> (<span id="filter-count-absent">0</span>)
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Student Table Container --}}
                            <div class="show_student_list">
                                <div id="attendance-empty-placeholder" class="text-center py-5 text-muted">
                                    <i class="fa fa-calendar-check-o fa-3x mb-3 text-primary opacity-50"></i>
                                    <h5>{{ __('select_class_and_date_to_load_students') ?? 'Select Class Section and Date to Begin Attendance' }}</h5>
                                    <p class="text-muted small">{{ __('attendance_marking_prompt') ?? 'Once selected, the student roster will appear with live one-click bulk controls.' }}</p>
                                </div>

                                <div id="table-container" style="display: none;">
                                    <table aria-describedby="attendance-desc" class="table student_table" id="table_list"
                                           data-toggle="table" data-url="{{ route('attendance.show', [1]) }}" data-click-to-select="false"
                                           data-side-pagination="server" data-pagination="false"
                                           data-search="true" data-show-refresh="true"
                                           data-trim-on-search="false" data-mobile-responsive="true" data-sort-name="roll_no"
                                           data-sort-order="asc" data-maintain-selected="true" data-export-data-type="all" data-show-export="true"
                                           data-export-options='{ "fileName": "attendance-<?= date('d-m-y') ?>" ,"ignoreColumn": ["operate"]}'
                                           data-query-params="attendanceQueryParams" data-escape="false">
                                        <thead>
                                        <tr>
                                            <th scope="col" data-field="id" data-sortable="false" data-visible="false">{{ __('id') }}</th>
                                            <th scope="col" data-field="no" data-width="50">{{ __('no.') }}</th>
                                            <th scope="col" data-field="roll_no" data-sortable="true" data-width="90" data-formatter="attendanceRollFormatter">
                                                <span class="badge badge-info">{{ __('roll_no') }}</span>
                                            </th>
                                            <th scope="col" data-field="admission_no" data-sortable="false" data-visible="false">{{ __('admission_no') }}</th>
                                            <th scope="col" data-field="student_name" data-formatter="attendanceStudentFormatter" data-escape="false">{{ __('student') }}</th>
                                            <th scope="col" data-field="type" data-formatter="addRadioInputAttendance" data-align="center" data-width="240" data-escape="false">{{ __('attendance_status') ?? __('type') }}</th>
                                            <th scope="col" data-field="remark" data-formatter="addRemarkInputAttendance" data-width="220" data-escape="false">{{ __('note') ?? 'Remark' }}</th>
                                        </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>

                            {{-- Sticky Action Bar --}}
                            <div class="zolo-att-sticky-footer" id="attendance-sticky-footer" style="display: none;">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge badge-success px-3 py-2" id="footer-present-pill" style="font-size: 13px;">
                                        <i class="fa fa-check mr-1"></i> <span id="footer-present-count">0</span> {{ __('present') }}
                                    </span>
                                    <span class="badge badge-danger px-3 py-2" id="footer-absent-pill" style="font-size: 13px;">
                                        <i class="fa fa-times mr-1"></i> <span id="footer-absent-count">0</span> {{ __('absent') }}
                                    </span>
                                    <span class="text-muted small ml-2 d-none d-md-inline">
                                        <i class="fa fa-info-circle mr-1"></i> {{ __('all_changes_ready_to_save') ?? 'Ready to save class attendance.' }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button class="btn btn-theme btn-lg px-4" id="create-btn" type="submit">
                                        <i class="fa fa-cloud-upload mr-1"></i> {{ __('save_attendance') ?? __('submit') }}
                                    </button>
                                </div>
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
        let attendanceState = {};
        let activeFilter = 'all';

        function updateAttendanceMetrics() {
            let total = 0;
            let present = 0;
            let absent = 0;

            $('#table_list tbody tr').each(function () {
                let group = $(this).find('.zolo-att-btn-group');
                if (group.length) {
                    total++;
                    let val = group.find('.attendance-type-input').val();
                    let isPresBtn = group.find('.zolo-att-btn-present').hasClass('is-active');
                    let isAbsBtn = group.find('.zolo-att-btn-absent').hasClass('is-active');

                    if (val === '0' || val === 0 || isAbsBtn) {
                        absent++;
                    } else if (val === '1' || val === 1 || isPresBtn) {
                        present++;
                    } else {
                        present++; // Default is present
                    }
                }
            });

            let rate = total > 0 ? Math.round((present / total) * 100) : 0;
            let presentPct = total > 0 ? (present / total) * 100 : 0;
            let absentPct = total > 0 ? (absent / total) * 100 : 0;

            $('#stat-total-students').text(total);
            $('#stat-present-students').text(present);
            $('#stat-absent-students').text(absent);
            $('#stat-attendance-rate').text(rate + '%');

            $('#stat-bar-present').css('width', presentPct + '%');
            $('#stat-bar-absent').css('width', absentPct + '%');

            $('#footer-present-count').text(present);
            $('#footer-absent-count').text(absent);

            $('#filter-count-all').text(total);
            $('#filter-count-present').text(present);
            $('#filter-count-absent').text(absent);

            if (absent > 0) {
                $('#absent-notification-badge').text(absent + ' guardians').show();
            } else {
                $('#absent-notification-badge').hide();
            }

            applyAttendanceFilter();
        }

        function applyAttendanceFilter() {
            if (activeFilter === 'all') {
                $('#table_list tbody tr').show();
            } else if (activeFilter === 'present') {
                $('#table_list tbody tr').each(function () {
                    let group = $(this).find('.zolo-att-btn-group');
                    let val = group.find('.attendance-type-input').val();
                    let isAbs = (val === '0' || val === 0 || group.find('.zolo-att-btn-absent').hasClass('is-active'));
                    $(this).toggle(!isAbs);
                });
            } else if (activeFilter === 'absent') {
                $('#table_list tbody tr').each(function () {
                    let group = $(this).find('.zolo-att-btn-group');
                    let val = group.find('.attendance-type-input').val();
                    let isAbs = (val === '0' || val === 0 || group.find('.zolo-att-btn-absent').hasClass('is-active'));
                    $(this).toggle(isAbs);
                });
            }
        }

        // Quick filter tabs
        $('.zolo-att-filter-item').on('click', function () {
            $('.zolo-att-filter-item').removeClass('active');
            $(this).addClass('active');
            activeFilter = $(this).data('filter');
            applyAttendanceFilter();
        });

        // Set Today button
        $('#btn-set-today').on('click', function () {
            let today = new Date();
            let dd = String(today.getDate()).padStart(2, '0');
            let mm = String(today.getMonth() + 1).padStart(2, '0');
            let yyyy = today.getFullYear();
            $('#date').val(dd + '-' + mm + '-' + yyyy).trigger('change');
        });

        // Sync hidden fields with form
        function syncHiddenInputs() {
            $('#hidden_class_section_id').val($('#timetable_class_section').val());
            $('#hidden_date').val($('#date').val());
        }

        function checkFormReady() {
            let classSection = $('#timetable_class_section').val();
            let date = $('#date').val();

            syncHiddenInputs();

            if (classSection && date) {
                $('#attendance-empty-placeholder').hide();
                $('#table-container').show();
                $('#attendance-stats-strip').slideDown(200);
                $('#attendance-quick-actions').slideDown(200);
                $('#attendance-sticky-footer').fadeIn(200);
                $('.holiday-div').show();
            } else {
                $('#attendance-empty-placeholder').show();
                $('#table-container').hide();
                $('#attendance-stats-strip').hide();
                $('#attendance-quick-actions').hide();
                $('#attendance-sticky-footer').hide();
                $('.holiday-div').hide();
            }
        }

        $('#timetable_class_section').on('change', function () {
            checkFormReady();
            if ($(this).val()) {
                $('.student_table').bootstrapTable('refresh');
            }
        });

        $('#date').on('input change', function () {
            checkFormReady();
            if ($('#timetable_class_section').val()) {
                $('.student_table').bootstrapTable('refresh');
            }
        });

        // Table Loaded & Post Body Events
        $('#table_list').on('load-success.bs.table post-body.bs.table', function () {
            restoreAttendanceState();
            updateAttendanceMetrics();
            setTimeout(updateAttendanceMetrics, 60);
        });

        // Interactive Pill Toggle Click
        $(document).on('click', '.zolo-att-btn', function (e) {
            e.preventDefault();
            let group = $(this).closest('.zolo-att-btn-group');
            let val = String($(this).data('val'));
            let studentId = group.data('student-id');

            group.find('.zolo-att-btn').removeClass('is-active');
            $(this).addClass('is-active');

            group.find('.attendance-type-input').val(val);

            if (studentId) {
                attendanceState[studentId] = val;
            }

            updateAttendanceMetrics();
        });

        function restoreAttendanceState() {
            for (let studentId in attendanceState) {
                let val = String(attendanceState[studentId]);
                let group = $('.zolo-att-btn-group[data-student-id="' + studentId + '"]');
                if (group.length) {
                    group.find('.attendance-type-input').val(val);
                    group.find('.zolo-att-btn').removeClass('is-active');
                    if (val === '1') {
                        group.find('.zolo-att-btn-present').addClass('is-active');
                    } else if (val === '0') {
                        group.find('.zolo-att-btn-absent').addClass('is-active');
                    }
                }
            }
        }

        // Bulk Actions
        $('#btn-mark-all-present').on('click', function () {
            $('#table_list tbody tr').each(function () {
                let group = $(this).find('.zolo-att-btn-group');
                group.find('.zolo-att-btn').removeClass('is-active');
                group.find('.zolo-att-btn-present').addClass('is-active');
                group.find('.attendance-type-input').val('1');
                let sid = group.data('student-id');
                if (sid) attendanceState[sid] = '1';
            });
            updateAttendanceMetrics();
            $.toast({
                text: '{{ __("all_students_marked_present") ?? "All students marked Present" }}',
                icon: 'success',
                loaderBg: '#10B981',
                position: 'top-right'
            });
        });

        $('#btn-mark-all-absent').on('click', function () {
            $('#table_list tbody tr').each(function () {
                let group = $(this).find('.zolo-att-btn-group');
                group.find('.zolo-att-btn').removeClass('is-active');
                group.find('.zolo-att-btn-absent').addClass('is-active');
                group.find('.attendance-type-input').val('0');
                let sid = group.data('student-id');
                if (sid) attendanceState[sid] = '0';
            });
            updateAttendanceMetrics();
            $.toast({
                text: '{{ __("all_students_marked_absent") ?? "All students marked Absent" }}',
                icon: 'info',
                loaderBg: '#EF4444',
                position: 'top-right'
            });
        });

        $('#btn-invert-attendance').on('click', function () {
            $('#table_list tbody tr').each(function () {
                let group = $(this).find('.zolo-att-btn-group');
                let current = group.find('.attendance-type-input').val();
                let targetVal = (current === '1' || current === 1) ? '0' : '1';

                group.find('.zolo-att-btn').removeClass('is-active');
                if (targetVal === '1') {
                    group.find('.zolo-att-btn-present').addClass('is-active');
                } else {
                    group.find('.zolo-att-btn-absent').addClass('is-active');
                }
                group.find('.attendance-type-input').val(targetVal);

                let sid = group.data('student-id');
                if (sid) attendanceState[sid] = targetVal;
            });
            updateAttendanceMetrics();
        });

        // Holiday Checkbox
        $('input[name="holiday"]').click(function () {
            let class_section_id = $('#timetable_class_section').val();
            let date = $('#date').val();
            let checkBox = document.getElementById('holiday');
            if (class_section_id && date) {
                Swal.fire({
                    title: "{{ __('are_you_sure') }}",
                    text: "{{ __('holiday_attendance_warning') ?? 'Marking as holiday will set the entire day as non-instructional.' }}",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: "{{ __('Yes') }}",
                    cancelButtonText: window.trans ? window.trans["Cancel"] : "Cancel"
                }).then((result) => {
                    if (checkBox.checked) {
                        if (result.isConfirmed) {
                            $("#holiday").val(3);
                            $('input[name="holiday"]').prop('checked', true);
                            $('.type').prop('required', false);
                            $('#table-container').slideUp(300);
                            $('#attendance-stats-strip').slideUp(300);
                            $('#attendance-quick-actions').slideUp(300);
                        } else {
                            checkBox.checked = false;
                        }
                    } else {
                        if (result.isConfirmed) {
                            $("#holiday").val(0);
                            $('#table-container').slideDown(300);
                            $('#attendance-stats-strip').slideDown(300);
                            $('#attendance-quick-actions').slideDown(300);
                            $('.type').prop('required', true);
                        } else {
                            checkBox.checked = true;
                        }
                    }
                });
            }
        });

        // Auto-check if existing date is holiday
        $('#timetable_class_section, #date').on('change input', function () {
            let date = $('#date').val();
            let class_section_id = $('#timetable_class_section').val();
            if (date && class_section_id) {
                $.ajax({
                    url: "{{ url('attendance/getAttendanceData') }}",
                    type: "GET",
                    data: {
                        date: date,
                        class_section_id: class_section_id
                    },
                    success: function (response) {
                        if (response == 3) {
                            $('input[name="holiday"]').prop('checked', true);
                            $("#holiday").val(3);
                            $('.type').prop('required', false);
                            $('#table-container').slideUp(300);
                            $('#attendance-stats-strip').slideUp(300);
                            $('#attendance-quick-actions').slideUp(300);
                        } else {
                            $('input[name="holiday"]').prop('checked', false);
                            $("#holiday").val(0);
                            $('.type').prop('required', true);
                        }
                    }
                });
            }
        });

        $(document).ready(function () {
            checkFormReady();
        });
    </script>
@endsection
