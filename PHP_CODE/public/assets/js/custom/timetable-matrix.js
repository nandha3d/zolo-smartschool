/*
 * School-wide teacher matrix: rows are teachers, columns are day x period. Shows who
 * teaches where, flags double bookings, and lets an editor drag a lesson to another
 * period of the same teacher. The drop target is checked client-side against the
 * teacher's and the class's other lessons (green = free, yellow = swap with the
 * class's lesson there, red = clash); the server re-validates every move.
 */
(function ($) {
    'use strict';

    var cfg = window.ZoloTeacherMatrix;
    var state = { days: [], slots: [], teachers: [], entries: [], byId: {}, clashIds: {} };
    var dragId = null;

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function overlaps(aStart, aEnd, bStart, bEnd) {
        return aStart < bEnd && aEnd > bStart;
    }

    function minutes(start, end) {
        var s = start.split(':'), e = end.split(':');
        return (e[0] * 60 + +e[1]) - (s[0] * 60 + +s[1]);
    }

    function load() {
        $.getJSON(cfg.dataUrl).done(function (res) {
            state.days = res.days || [];
            state.slots = res.slots || [];
            state.teachers = res.teachers || [];
            state.entries = res.entries || [];
            state.byId = {};
            state.entries.forEach(function (e) { state.byId[e.id] = e; });
            findClashes();
            fillFilters(res);
            render();
        }).fail(function () {
            $('#tm-table tbody').html('<tr><td class="p-4 text-center text-danger">Could not load the timetable.</td></tr>');
        });
    }

    function findClashes() {
        state.clashIds = {};
        var groups = {};
        state.entries.forEach(function (e) {
            if (!e.teacher_id) return;
            var key = e.teacher_id + '|' + e.day;
            (groups[key] = groups[key] || []).push(e);
        });
        Object.keys(groups).forEach(function (key) {
            var list = groups[key];
            for (var i = 0; i < list.length; i++) {
                for (var j = i + 1; j < list.length; j++) {
                    if (overlaps(list[i].start_time, list[i].end_time, list[j].start_time, list[j].end_time)) {
                        state.clashIds[list[i].id] = true;
                        state.clashIds[list[j].id] = true;
                    }
                }
            }
        });
    }

    function fillFilters(res) {
        var daySel = $('#tm-day-filter');
        var current = daySel.val();
        daySel.find('option:not(:first)').remove();
        state.days.forEach(function (d) { daySel.append($('<option>').val(d).text(d)); });
        daySel.val(current);

        var subSel = $('#tm-sub-teacher').empty();
        state.teachers.forEach(function (t) { subSel.append($('<option>').val(t.id).text(t.name)); });

        var clashCount = Object.keys(state.clashIds).length;
        $('#tm-summary').html(
            esc(state.teachers.length) + ' teachers · ' + esc(state.entries.length) + ' lessons' +
            (res.unassigned ? ' · <span class="text-warning">' + esc(res.unassigned) + ' lessons without a teacher</span>' : '') +
            (clashCount ? ' · <span class="text-danger font-weight-bold">' + esc(clashCount) + ' double-booked lessons</span>' : ' · <span class="text-success">no clashes</span>')
        );
    }

    function visibleDays() {
        var d = $('#tm-day-filter').val();
        return d ? [d] : state.days;
    }

    function render() {
        var days = visibleDays();
        var head1 = '<tr><th class="tm-teacher" rowspan="2">Teacher</th>';
        var head2 = '<tr>';
        days.forEach(function (day) {
            head1 += '<th class="tm-day-start" colspan="' + state.slots.length + '">' + esc(day) + '</th>';
            state.slots.forEach(function (slot, i) {
                head2 += '<th class="' + (i === 0 ? 'tm-day-start' : '') + '" title="' + esc(slot.start_time + ' - ' + slot.end_time) + '">' +
                    esc(slot.label) + '<br><small>' + esc(slot.start_time.substr(0, 5)) + '</small></th>';
            });
        });
        $('#tm-table thead').html(head1 + '</tr>' + head2 + '</tr>');

        var search = ($('#tm-search').val() || '').toLowerCase();
        var clashOnly = $('#tm-clash-only').is(':checked');
        var lessonsByTeacher = {};
        state.entries.forEach(function (e) {
            if (e.teacher_id) (lessonsByTeacher[e.teacher_id] = lessonsByTeacher[e.teacher_id] || []).push(e);
        });

        var body = '';
        state.teachers.forEach(function (t) {
            var lessons = lessonsByTeacher[t.id] || [];
            if (search && t.name.toLowerCase().indexOf(search) === -1) return;
            if (clashOnly && !lessons.some(function (l) { return state.clashIds[l.id]; })) return;

            body += '<tr><td class="tm-teacher">' + esc(t.name) + ' <span class="tm-load">(' + lessons.length + ')</span></td>';
            days.forEach(function (day) {
                state.slots.forEach(function (slot, i) {
                    var here = lessons.filter(function (l) { return l.day === day && l.start_time === slot.start_time && l.end_time === slot.end_time; });
                    var cls = 'tm-cell' + (i === 0 ? ' tm-day-start' : '') + (slot.is_break ? ' tm-break' : '');
                    body += '<td class="' + cls + '" data-teacher="' + t.id + '" data-day="' + esc(day) + '" data-slot="' + i + '">';
                    here.forEach(function (l) { body += lessonHtml(l); });
                    body += '</td>';
                });
            });
            body += '</tr>';
        });
        $('#tm-table tbody').html(body || '<tr><td class="p-4 text-center text-muted">No teachers match.</td></tr>');
    }

    function lessonHtml(l) {
        var draggable = cfg.canEdit && !l.is_locked;
        var cls = 'tm-lesson' + (l.is_locked ? ' tm-locked' : '') + (state.clashIds[l.id] ? ' tm-clash' : '');
        var title = (l.class_name || '') + ' · ' + (l.subject_name || '') + (l.resource_name ? ' · ' + l.resource_name : '') + (l.is_locked ? ' (locked)' : '');
        return '<div class="' + cls + '" draggable="' + draggable + '" data-id="' + l.id + '" style="background:' + esc(l.bg_color) + '" title="' + esc(title) + '">' +
            (l.is_locked ? '<i class="fa fa-lock"></i> ' : '') + esc(l.class_name) +
            '<small>' + esc(l.subject_name) + (l.resource_name ? ' · ' + esc(l.resource_name) : '') + '</small></div>';
    }

    /** 'ok' | 'swap' | 'bad' for dropping lesson `id` into teacher cell (day, slot). */
    function dropStatus(id, day, slot) {
        var lesson = state.byId[id];
        if (!lesson || slot.is_break) return 'bad';
        if (lesson.day === day && lesson.start_time === slot.start_time && lesson.end_time === slot.end_time) return 'bad';

        var teacherBusy = state.entries.some(function (e) {
            return e.id !== lesson.id && e.teacher_id === lesson.teacher_id && e.day === day &&
                overlaps(e.start_time, e.end_time, slot.start_time, slot.end_time);
        });
        if (teacherBusy) return 'bad';

        var classLessons = state.entries.filter(function (e) {
            return e.id !== lesson.id && e.class_section_id === lesson.class_section_id && e.day === day &&
                overlaps(e.start_time, e.end_time, slot.start_time, slot.end_time);
        });
        if (classLessons.length === 0) return 'ok';
        if (classLessons.length > 1) return 'bad';

        var other = classLessons[0];
        if (other.is_locked || other.start_time !== slot.start_time || other.end_time !== slot.end_time ||
            minutes(lesson.start_time, lesson.end_time) !== minutes(slot.start_time, slot.end_time)) {
            return 'bad';
        }
        // The other lesson moves to this lesson's old time: its teacher must be free then.
        var otherTeacherBusy = other.teacher_id && state.entries.some(function (e) {
            return e.id !== other.id && e.id !== lesson.id && e.teacher_id === other.teacher_id && e.day === lesson.day &&
                overlaps(e.start_time, e.end_time, lesson.start_time, lesson.end_time);
        });
        return otherTeacherBusy ? 'bad' : 'swap';
    }

    function clearDropMarks() {
        $('.tm-drop-ok, .tm-drop-swap, .tm-drop-bad').removeClass('tm-drop-ok tm-drop-swap tm-drop-bad');
    }

    $(document).on('dragstart', '.tm-lesson[draggable="true"]', function (ev) {
        dragId = +$(this).data('id');
        ev.originalEvent.dataTransfer.effectAllowed = 'move';
        ev.originalEvent.dataTransfer.setData('text/plain', String(dragId));
    });

    $(document).on('dragend', '.tm-lesson', function () {
        dragId = null;
        clearDropMarks();
    });

    $(document).on('dragover', '.tm-cell', function (ev) {
        if (!dragId) return;
        var cell = $(this);
        var lesson = state.byId[dragId];
        if (!lesson || +cell.data('teacher') !== +lesson.teacher_id) return;
        var status = dropStatus(dragId, cell.data('day'), state.slots[+cell.data('slot')]);
        clearDropMarks();
        cell.addClass('tm-drop-' + status);
        if (status !== 'bad') ev.preventDefault();
    });

    $(document).on('drop', '.tm-cell', function (ev) {
        ev.preventDefault();
        var cell = $(this);
        var id = dragId;
        var slot = state.slots[+cell.data('slot')];
        clearDropMarks();
        dragId = null;
        if (!id || !slot) return;

        $.ajax({
            url: cfg.moveUrl,
            type: 'POST',
            data: {
                _token: cfg.csrf,
                id: id,
                day: cell.data('day'),
                start_time: slot.start_time,
                end_time: slot.end_time,
                period_id: slot.period_id || ''
            }
        }).done(function (res) {
            if (res.error) {
                showErrorToast(res.message);
            } else {
                showSuccessToast(res.message);
            }
            load();
        }).fail(function (xhr) {
            showErrorToast((xhr.responseJSON && xhr.responseJSON.message) || 'Move failed.');
            load();
        });
    });

    $('#tm-search').on('input', render);
    $('#tm-day-filter, #tm-clash-only').on('change', render);
    $('#tm-reload').on('click', load);

    $('#tm-sub-find').on('click', function () {
        var out = $('#tm-sub-result').html('<span class="text-muted">Searching…</span>');
        $.getJSON(cfg.substitutesUrl, { teacher_id: $('#tm-sub-teacher').val(), date: $('#tm-sub-date').val() })
            .done(function (res) {
                if (!res.lessons || !res.lessons.length) {
                    out.html('<span class="text-muted">No lessons for this teacher on ' + esc(res.day) + '.</span>');
                    return;
                }
                var html = '';
                res.lessons.forEach(function (lesson) {
                    html += '<div class="border rounded p-2 mb-2"><div class="font-weight-bold">' +
                        esc(lesson.start_time.substr(0, 5) + '–' + lesson.end_time.substr(0, 5)) + ' · ' +
                        esc(lesson.class_name) + ' · ' + esc(lesson.subject_name) + '</div>';
                    if (!lesson.candidates.length) {
                        html += '<div class="text-danger">No free teacher at this time.</div>';
                    } else {
                        html += '<ol class="mb-0 pl-3">';
                        lesson.candidates.forEach(function (c) {
                            html += '<li><strong>' + esc(c.name) + '</strong> <span class="text-muted">— ' + esc(c.reasons.join(', ')) + '</span></li>';
                        });
                        html += '</ol>';
                    }
                    html += '</div>';
                });
                out.html('<div class="text-muted mb-2">' + esc(res.day + ', ' + res.date) + '</div>' + html);
            })
            .fail(function (xhr) {
                out.html('<span class="text-danger">' + esc((xhr.responseJSON && xhr.responseJSON.message) || 'Search failed.') + '</span>');
            });
    });

    load();
})(jQuery);
