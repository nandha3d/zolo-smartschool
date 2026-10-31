@extends('layouts.master')

@section('title')
    {{ __('Teacher attendance') }}
@endsection

@section('content')
    <div class="content-wrapper">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="page-title">{{ __('Teacher attendance') }}</h3>
            <div class="small" id="ta-summary"></div>
        </div>

        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="row align-items-end">
                    <div class="col-md-3 form-group mb-2">
                        <label for="ta-date" class="font-weight-bold">{{ __('Date') }}</label>
                        <input type="date" id="ta-date" class="form-control" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-3 form-group mb-2">
                        <label for="ta-subject" class="font-weight-bold">{{ __('Subject') }}</label>
                        <select id="ta-subject" class="form-control">
                            <option value="">{{ __('All subjects') }}</option>
                            @foreach ($subjects as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group mb-2">
                        <label for="ta-filter" class="font-weight-bold">{{ __('Show') }}</label>
                        <select id="ta-filter" class="form-control">
                            <option value="">{{ __('Everyone') }}</option>
                            <option value="unmarked">{{ __('Not marked') }}</option>
                            <option value="away">{{ __('Away') }}</option>
                            <option value="present">{{ __('Present') }}</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group mb-2 text-md-right">
                        @canany(['staff-attendance-create', 'staff-attendance-edit'])
                            <button type="button" class="btn btn-outline-primary btn-sm" id="ta-all-present"><i class="fa fa-check mr-1"></i>{{ __('Mark unmarked as present') }}</button>
                            <button type="button" class="btn btn-theme btn-sm" id="ta-save"><i class="fa fa-save mr-1"></i>{{ __('Save attendance') }}</button>
                        @endcanany
                    </div>
                </div>
                <small class="text-muted">
                    P {{ __('present') }} · HD {{ __('half day') }} · OD {{ __('on duty') }} · A {{ __('absent') }} · CL {{ __('casual leave') }} · ML {{ __('medical leave') }}.
                    {{ __('Teachers marked A, OD, CL or ML need a proxy for their periods.') }}
                </small>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>{{ __('Teacher') }}</th>
                            <th>{{ __('Periods that day') }}</th>
                            <th style="width:120px;">{{ __('Status') }}</th>
                            <th>{{ __('Remark') }}</th>
                            <th style="width:120px;">{{ __('Proxy cover') }}</th>
                        </tr>
                        </thead>
                        <tbody id="ta-body">
                        <tr><td colspan="6" class="text-center text-muted p-4">{{ __('Loading...') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="proxyModal" tabindex="-1" role="dialog" aria-labelledby="proxyModalTitle">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="proxyModalTitle">{{ __('Proxy cover') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">&times;</button>
                </div>
                <div class="modal-body" id="proxy-body"></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            var urls = {
                data: @json(route('teacher-attendance.data')),
                save: @json(route('teacher-attendance.store')),
                plan: @json(route('teacher-attendance.proxy-plan')),
                assign: @json(route('teacher-attendance.proxy.assign')),
                remove: @json(route('teacher-attendance.proxy.remove'))
            };
            var CODES = ['P', 'HD', 'OD', 'A', 'CL', 'ML'];
            var AWAY = ['A', 'OD', 'CL', 'ML'];
            var canEdit = @json(Auth::user()->canany(['staff-attendance-create', 'staff-attendance-edit']));
            var rows = [], proxyTeacher = null;

            function esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
                });
            }
            function token() { return $('meta[name="csrf-token"]').attr('content'); }
            function toast(ok, msg) {
                if (ok && typeof showSuccessToast === 'function') { showSuccessToast(msg); }
                else if (!ok && typeof showErrorToast === 'function') { showErrorToast(msg); }
                else { alert(msg); }
            }

            function load() {
                $('#ta-body').html('<tr><td colspan="6" class="text-center text-muted p-4">' + @json(__('Loading...')) + '</td></tr>');
                $.get(urls.data, {date: $('#ta-date').val(), subject_id: $('#ta-subject').val()}).done(function (res) {
                    rows = res.rows;
                    var s = res.summary;
                    $('#ta-summary').html(esc(res.day) + ' · ' + @json(__('Total')) + ': <b>' + s.total + '</b> · ' + @json(__('Present')) +
                        ': <b class="text-success">' + s.present + '</b> · ' + @json(__('Away')) + ': <b class="text-danger">' + s.away +
                        '</b> · ' + @json(__('Not marked')) + ': <b>' + s.unmarked + '</b>');
                    render();
                });
            }

            function visible(r) {
                var f = $('#ta-filter').val();
                if (f === 'unmarked') { return !r.code; }
                if (f === 'away') { return AWAY.indexOf(r.code) !== -1; }
                if (f === 'present') { return r.code === 'P' || r.code === 'HD'; }
                return true;
            }

            function render() {
                var html = rows.map(function (r, i) {
                    if (!visible(r)) { return ''; }
                    var periods = r.periods.length ? r.periods.map(function (p) {
                        return '<span class="badge badge-light border mr-1 mb-1" title="' + esc(p.subject) + '">' + esc(p.label) + ' ' + esc(p.class) +
                            (p.proxy ? ' <span class="text-success">→ ' + esc(p.proxy) + '</span>' : '') + '</span>';
                    }).join('') : '<span class="text-muted small">' + @json(__('No periods')) + '</span>';
                    var select = '<select class="form-control form-control-sm ta-code" data-i="' + i + '"' + (canEdit ? '' : ' disabled') + '>' +
                        '<option value="">-</option>' + CODES.map(function (c) {
                            return '<option value="' + c + '"' + (r.code === c ? ' selected' : '') + '>' + c + '</option>';
                        }).join('') + (r.code === 'H' ? '<option value="H" selected disabled>' + @json(__('Holiday')) + '</option>' : '') + '</select>';
                    var uncovered = r.periods.filter(function (p) { return !p.proxy; }).length;
                    var proxyBtn = (AWAY.indexOf(r.code) !== -1 && r.periods.length)
                        ? '<button type="button" class="btn btn-xs ' + (uncovered ? 'btn-danger' : 'btn-outline-success') + ' ta-proxy" data-i="' + i + '">' +
                            (uncovered ? uncovered + ' ' + @json(__('to cover')) : @json(__('Covered'))) + '</button>'
                        : '';
                    return '<tr><td>' + (i + 1) + '</td>' +
                        '<td><b>' + esc(r.name) + '</b><div class="small text-muted">' + esc(r.subjects) + '</div></td>' +
                        '<td>' + periods + '</td><td>' + select + '</td>' +
                        '<td><input class="form-control form-control-sm ta-remark" data-i="' + i + '" maxlength="255" value="' + esc(r.remark) + '"' + (canEdit ? '' : ' disabled') + '></td>' +
                        '<td>' + proxyBtn + '</td></tr>';
                }).join('');
                $('#ta-body').html(html || '<tr><td colspan="6" class="text-center text-muted p-4">' + @json(__('No teachers')) + '</td></tr>');
            }

            $(document).on('change', '.ta-code', function () { rows[$(this).data('i')].code = this.value || null; rows[$(this).data('i')].dirty = true; });
            $(document).on('input', '.ta-remark', function () { rows[$(this).data('i')].remark = this.value; rows[$(this).data('i')].dirty = true; });
            $(document).on('click', '.ta-proxy', function () { openProxy(rows[$(this).data('i')]); });
            $('#ta-date, #ta-subject').on('change', load);
            $('#ta-filter').on('change', render);

            $('#ta-all-present').on('click', function () {
                rows.forEach(function (r) { if (!r.code) { r.code = 'P'; r.dirty = true; } });
                render();
            });

            $('#ta-save').on('click', function () {
                var payload = rows.filter(function (r) { return r.code && r.code !== 'H'; })
                    .map(function (r) { return {teacher_id: r.teacher_id, code: r.code, remark: r.remark || ''}; });
                if (!payload.length) { toast(false, @json(__('Mark at least one teacher.'))); return; }
                var btn = $(this).prop('disabled', true);
                $.ajax({
                    url: urls.save, method: 'POST', contentType: 'application/json',
                    headers: {'X-CSRF-TOKEN': token()},
                    data: JSON.stringify({date: $('#ta-date').val(), rows: payload})
                }).done(function (res) {
                    if (res.error) { toast(false, res.message); return; }
                    toast(true, res.message);
                    var need = (res.data && res.data.needs_cover) || [];
                    load();
                    if (need.length) {
                        var first = rows.find(function (r) { return r.teacher_id === need[0].teacher_id; });
                        if (first) { setTimeout(function () { openProxy(first); }, 400); }
                    }
                }).fail(function (xhr) {
                    toast(false, (xhr.responseJSON && xhr.responseJSON.message) || @json(__('Could not save')));
                }).always(function () { btn.prop('disabled', false); });
            });

            function openProxy(teacher) {
                proxyTeacher = teacher;
                $('#proxyModalTitle').text(@json(__('Proxy cover for')) + ' ' + teacher.name);
                $('#proxy-body').html('<div class="text-center text-muted p-3">' + @json(__('Loading...')) + '</div>');
                $('#proxyModal').modal('show');
                $.get(urls.plan, {teacher_id: teacher.teacher_id, date: $('#ta-date').val()}).done(function (res) {
                    if (!res.lessons.length) { $('#proxy-body').html('<p class="text-muted">' + @json(__('No periods to cover.')) + '</p>'); return; }
                    $('#proxy-body').html(res.lessons.map(function (l) {
                        var options = l.candidates.map(function (c) {
                            return '<option value="' + c.teacher_id + '">' + esc(c.name) + ' — ' + esc(c.reasons.join(', ')) + '</option>';
                        }).join('');
                        return '<div class="border rounded p-2 mb-2">' +
                            '<div class="d-flex justify-content-between"><b>' + (l.period_number ? 'P' + l.period_number + ' · ' : '') +
                            esc(String(l.start_time).substring(0, 5)) + '-' + esc(String(l.end_time).substring(0, 5)) + ' · ' + esc(l.class_name) + '</b>' +
                            '<span class="text-muted">' + esc(l.subject_name) + '</span></div>' +
                            (l.proxy ? '<div class="text-success small my-1"><i class="fa fa-check"></i> ' + @json(__('Covered by')) + ' ' + esc(l.proxy.name) +
                                (canEdit ? ' <a href="#" class="text-danger ml-2 px-remove" data-id="' + l.timetable_id + '">' + @json(__('remove')) + '</a>' : '') + '</div>' : '') +
                            (canEdit ? '<div class="input-group input-group-sm mt-1"><select class="form-control px-candidate" data-id="' + l.timetable_id + '">' +
                                (options || '<option value="">' + @json(__('Nobody is free at this time')) + '</option>') + '</select>' +
                                '<div class="input-group-append"><button class="btn btn-theme px-assign" type="button" data-id="' + l.timetable_id + '"' + (options ? '' : ' disabled') + '>' +
                                (l.proxy ? @json(__('Change')) : @json(__('Assign'))) + '</button></div></div>' : '') +
                            '</div>';
                    }).join(''));
                });
            }

            $(document).on('click', '.px-assign', function () {
                var id = $(this).data('id');
                var proxy = $('.px-candidate[data-id="' + id + '"]').val();
                if (!proxy) { return; }
                $.post(urls.assign, {_token: token(), date: $('#ta-date').val(), timetable_id: id, proxy_teacher_id: proxy})
                    .done(function (res) { toast(!res.error, res.message); if (!res.error) { openProxy(proxyTeacher); load(); } })
                    .fail(function (xhr) { toast(false, (xhr.responseJSON && xhr.responseJSON.message) || 'Error'); });
            });
            $(document).on('click', '.px-remove', function (e) {
                e.preventDefault();
                $.post(urls.remove, {_token: token(), date: $('#ta-date').val(), timetable_id: $(this).data('id')})
                    .done(function (res) { toast(!res.error, res.message); openProxy(proxyTeacher); load(); });
            });

            $(load);
        })();
    </script>
@endsection
