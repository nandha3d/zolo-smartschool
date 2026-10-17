/*
 * Hostel addon pages. Shared helpers (AJAX forms, deletes, student picker, list
 * filters) plus one block per page, each gated on that page's config object.
 */
(function ($) {
    'use strict';

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function msg(xhr) {
        return (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.';
    }

    function refreshTable() {
        if ($('#table_list').length) $('#table_list').bootstrapTable('refresh');
    }

    // Forms posted as AJAX; data-after = reload | table | rooms.
    $(document).on('submit', 'form[data-hostel-form]', function (e) {
        e.preventDefault();
        var form = $(this), button = form.find('[type=submit]').prop('disabled', true);
        $.ajax({ url: form.attr('action'), type: form.attr('method') || 'POST', data: new FormData(this), processData: false, contentType: false })
            .done(function (res) {
                showSuccessToast(res.message);
                var after = form.data('after');
                if (after === 'reload') return location.reload();
                if (after === 'rooms') $(document).trigger('hostel:rooms');
                refreshTable();
                form[0].reset();
                form.find('.hostel-student-id').val('');
            })
            .fail(function (xhr) { showErrorToast(msg(xhr)); })
            .always(function () { button.prop('disabled', false); });
    });

    $(document).on('click', '[data-hostel-delete]', function () {
        if (!confirm('Delete this item?')) return;
        $.ajax({ url: $(this).data('hostel-delete'), type: 'DELETE' })
            .done(function (res) { showSuccessToast(res.message); location.reload(); })
            .fail(function (xhr) { showErrorToast(msg(xhr)); });
    });

    window.hostelQueryParams = function (p) {
        return {
            limit: p.limit, offset: p.offset, order: p.order, sort: p.sort, search: p.search,
            status: $('#filter_status').length ? $('#filter_status').val() : undefined
        };
    };
    $('#filter_status').on('change', refreshTable);

    // Student picker: <input .hostel-student-search> + <input .hostel-student-id> + <ul .hostel-student-results>
    function studentPicker(studentsUrl, onPick, onlyResidents) {
        var timer = null;
        $(document).on('input', '.hostel-student-search', function () {
            var input = $(this), list = input.siblings('.hostel-student-results');
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (!input.val()) { list.empty(); return; }
                $.getJSON(studentsUrl, { q: input.val() }).done(function (res) {
                    list.html(res.students.map(function (s) {
                        var disabled = onlyResidents && !s.allocation_id;
                        return '<li class="list-group-item list-group-item-action px-2 py-1' + (disabled ? ' text-muted' : '') + '" data-id="' + s.id + '"' + (disabled ? ' data-disabled="1"' : '') + ' data-name="' + esc(s.name) + '" style="cursor:pointer">' +
                            esc(s.name) + ' <span class="text-muted">' + esc(s.admission_no) + ' · ' + esc(s.class) + '</span>' +
                            (s.bed ? ' <span class="badge badge-info">' + esc(s.bed) + '</span>' : (onlyResidents ? ' <span class="badge badge-light">not a resident</span>' : '')) + '</li>';
                    }).join(''));
                });
            }, 250);
        });
        $(document).on('click', '.hostel-student-results li', function () {
            if ($(this).data('disabled')) return;
            var list = $(this).parent(), input = list.siblings('.hostel-student-search');
            input.val($(this).data('name'));
            list.siblings('.hostel-student-id').val($(this).data('id'));
            list.empty();
            if (onPick) onPick($(this).data('id'), $(this).data('name'));
        });
    }

    // ---------------------------------------------------------------- Setup: rooms per block
    var setup = window.HostelSetup;
    if (setup) {
        var loadRooms = function () {
            var block = $('#setup-block').val();
            $('#setup-room-block').val(block);
            if (!block) return;
            $.getJSON(setup.roomsUrl, { block_id: block }).done(function (res) {
                $('#setup-rooms').html(res.rooms.map(function (r) {
                    var taken = r.beds.filter(function (b) { return b.allocation_id; }).length;
                    return '<div class="col-md-3 col-sm-6 mb-3"><div class="border rounded p-2 h-100" data-room="' + r.id + '">' +
                        '<div class="d-flex justify-content-between"><strong>' + esc(r.room_number) + '</strong><span class="small text-muted">Floor ' + esc(r.floor) + '</span></div>' +
                        '<div class="small">' + esc(r.room_type || 'No type') + ' · ' + taken + '/' + r.beds.length + ' beds</div>' +
                        (setup.canEdit ? '<div class="form-inline mt-1" style="gap:4px"><input type="number" class="form-control form-control-sm setup-capacity" style="width:60px" min="1" max="30" value="' + r.bed_capacity + '" data-floor="' + r.floor + '" data-type="' + (r.room_type_id || '') + '">' +
                            '<select class="form-control form-control-sm setup-status"><option' + (r.status === 'Available' ? ' selected' : '') + '>Available</option><option' + (r.status === 'Maintenance' ? ' selected' : '') + '>Maintenance</option></select></div>' : '') +
                        (setup.canDelete ? '<button type="button" class="btn btn-xs btn-link text-danger p-0 mt-1 setup-room-delete">delete room</button>' : '') +
                        '</div></div>';
                }).join('') || '<div class="col-12 text-muted small">No rooms in this block yet.</div>');
            });
        };
        $('#setup-block').on('change', loadRooms);
        $(document).on('hostel:rooms', loadRooms);
        $(document).on('change', '.setup-capacity, .setup-status', function () {
            var box = $(this).closest('[data-room]'), cap = box.find('.setup-capacity');
            $.ajax({
                url: setup.roomUrl.replace(':id', box.data('room')), type: 'PUT',
                data: { bed_capacity: cap.val(), floor: cap.data('floor'), room_type_id: cap.data('type'), status: box.find('.setup-status').val() }
            }).done(function (res) { showSuccessToast(res.message); loadRooms(); }).fail(function (xhr) { showErrorToast(msg(xhr)); loadRooms(); });
        });
        $(document).on('click', '.setup-room-delete', function () {
            if (!confirm('Delete this room and its beds?')) return;
            $.ajax({ url: setup.roomUrl.replace(':id', $(this).closest('[data-room]').data('room')), type: 'DELETE' })
                .done(function (res) { showSuccessToast(res.message); loadRooms(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
        loadRooms();
    }

    // ---------------------------------------------------------------- Bed board
    var board = window.HostelBoard;
    if (board) {
        var selectedBed = null, selectedAllocation = null, moving = false;

        var loadBoard = function () {
            $.getJSON(board.roomsUrl, { block_id: $('#board-block').val() }).done(function (res) {
                var beds = 0, taken = 0;
                $('#board').html(res.rooms.map(function (r) {
                    var bedsHtml = r.beds.map(function (b) {
                        beds++; if (b.allocation_id) taken++;
                        return '<button type="button" class="hb-bed' + (b.allocation_id ? ' hb-taken' : '') + '" data-bed="' + b.id + '" data-allocation="' + (b.allocation_id || '') + '" data-student="' + esc(b.student || '') + '" data-admission="' + esc(b.admission_no || '') + '" data-label="' + esc(b.bed_number) + '"' + (r.status !== 'Available' && !b.allocation_id ? ' disabled' : '') + '>' +
                            '<strong>' + esc(b.bed_number) + '</strong> ' + (b.student ? esc(b.student) : '<span class="text-success">free</span>') + '</button>';
                    }).join('');
                    return '<div class="col-md-3 col-sm-6 mb-3"><div class="hb-room' + (r.status !== 'Available' ? ' hb-maint' : '') + '"><div class="d-flex justify-content-between"><strong>Room ' + esc(r.room_number) + '</strong><span class="small text-muted">' + esc(r.room_type || '') + (r.status !== 'Available' ? ' · maintenance' : '') + '</span></div>' + bedsHtml + '</div></div>';
                }).join('') || '<div class="col-12 text-muted small">No rooms in this block. Add them under Blocks &amp; Rooms.</div>');
                $('#board-summary').text(taken + ' / ' + beds + ' beds occupied');
            });
        };

        var showPanel = function (btn) {
            $('.hb-bed').removeClass('hb-selected');
            btn.addClass('hb-selected');
            selectedBed = btn.data('bed');
            selectedAllocation = btn.data('allocation') || null;
            $('#board-panel-title').text('Bed ' + btn.data('label'));
            $('#board-panel-free').toggleClass('d-none', !!selectedAllocation || !board.canCreate);
            $('#board-panel-taken').toggleClass('d-none', !selectedAllocation);
            $('#board-resident').text(btn.data('student'));
            $('#board-resident-meta').text(btn.data('admission'));
            $('#board-move-hint').addClass('d-none');
        };

        $(document).on('click', '.hb-bed', function () {
            var btn = $(this);
            if (moving && !btn.data('allocation')) {
                moving = false;
                $.post(board.transferUrl.replace(':id', selectedAllocation), { bed_id: btn.data('bed') })
                    .done(function (res) { showSuccessToast(res.message); loadBoard(); refreshTable(); })
                    .fail(function (xhr) { showErrorToast(msg(xhr)); });
                return;
            }
            moving = false;
            showPanel(btn);
        });

        studentPicker(board.studentsUrl, null, false);
        $('#board-student-search').addClass('hostel-student-search').after('<input type="hidden" class="hostel-student-id">');
        $('#board-student-results').addClass('hostel-student-results');
        $(document).on('click', '#board-student-results li', function () {
            var studentId = $(this).data('id');
            if (!selectedBed || !confirm('Allocate bed to ' + $(this).data('name') + '?')) return;
            $.post(board.allocateUrl, { student_id: studentId, bed_id: selectedBed, allocation_date: $('#board-date').val(), fee_amount: $('#board-fee').val() })
                .done(function (res) { showSuccessToast(res.message); loadBoard(); refreshTable(); $('#board-panel-free').addClass('d-none'); })
                .fail(function (xhr) { showErrorToast(msg(xhr)); });
        });

        $('#board-vacate').on('click', function () {
            if (!selectedAllocation || !confirm('Vacate this bed today?')) return;
            $.post(board.vacateUrl.replace(':id', selectedAllocation))
                .done(function (res) { showSuccessToast(res.message); loadBoard(); refreshTable(); $('#board-panel-taken').addClass('d-none'); })
                .fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
        $('#board-move').on('click', function () { moving = true; $('#board-move-hint').removeClass('d-none'); });
        $('#board-block').on('change', loadBoard);
        loadBoard();
    }

    // ---------------------------------------------------------------- Roll call
    var roll = window.HostelRoll;
    if (roll) {
        var statuses = ['Present', 'Absent', 'Late', 'On_Leave'];
        var loadRoll = function () {
            $.getJSON(roll.rollUrl, { block_id: $('#roll-block').val(), date: $('#roll-date').val(), roll_call: $('#roll-type').val() }).done(function (res) {
                $('#roll-info').text(res.residents.length + ' residents' + (res.saved ? ' · already taken, editing' : ''));
                $('#roll-body').html(res.residents.map(function (r) {
                    var radios = statuses.map(function (s) {
                        var id = 'r' + r.student_id + s;
                        return '<div class="custom-control custom-radio custom-control-inline"><input type="radio" class="custom-control-input" id="' + id + '" name="s' + r.student_id + '" value="' + s + '"' + (r.status === s ? ' checked' : '') + '><label class="custom-control-label small" for="' + id + '">' + s.replace('_', ' ') + '</label></div>';
                    }).join('');
                    return '<tr data-student="' + r.student_id + '"><td>' + esc(r.room) + ' <span class="text-muted small">' + esc(r.bed) + '</span></td><td>' + esc(r.name) + ' <span class="text-muted small">' + esc(r.admission_no) + '</span>' + (r.on_pass ? ' <span class="badge badge-info">on pass</span>' : '') + '</td><td>' + radios + '</td><td><input class="form-control form-control-sm roll-remarks" value="' + esc(r.remarks || '') + '" maxlength="191"></td></tr>';
                }).join('') || '<tr><td colspan="4" class="text-muted">No residents in this block.</td></tr>');
            }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        };
        $('#roll-block, #roll-date, #roll-type').on('change', loadRoll);
        $('#roll-all-present').on('click', function () {
            $('#roll-body tr').each(function () {
                if (!$(this).find('input[type=radio]:checked').length) $(this).find('input[value=Present]').prop('checked', true);
            });
        });
        $('#roll-save').on('click', function () {
            var marks = {}, missing = 0;
            $('#roll-body tr[data-student]').each(function () {
                var checked = $(this).find('input[type=radio]:checked').val();
                if (!checked) { missing++; return; }
                marks[$(this).data('student')] = { status: checked, remarks: $(this).find('.roll-remarks').val() };
            });
            if (missing && !confirm(missing + ' student(s) have no mark and will be skipped. Save anyway?')) return;
            $.post(roll.saveUrl, { block_id: $('#roll-block').val(), date: $('#roll-date').val(), roll_call: $('#roll-type').val(), marks: marks })
                .done(function (res) { showSuccessToast(res.message); loadRoll(); })
                .fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
        loadRoll();
    }

    // ---------------------------------------------------------------- Gate passes
    var passes = window.HostelPasses;
    if (passes) {
        studentPicker(passes.studentsUrl, null, true);
        window.hostelCodeFormatter = function (value, row) {
            return '<span class="text-monospace">' + esc(value) + '</span>';
        };
        window.hostelPassStatusFormatter = function (value, row) {
            var tone = { Requested: 'warning', Approved: 'success', Rejected: 'danger', Out: 'info', Returned: 'secondary', Cancelled: 'light' }[value] || 'light';
            return '<span class="badge badge-' + tone + '">' + esc(value) + '</span>' + (row.late ? ' <span class="badge badge-danger">late</span>' : '');
        };
        window.hostelPassActions = function (value, row) {
            var html = '';
            if (row.status === 'Requested' && row.warden_status === 'Pending') {
                html += '<button type="button" class="btn btn-xs btn-success pass-decide" data-id="' + row.id + '" data-decision="approve">Approve</button> ' +
                    '<button type="button" class="btn btn-xs btn-outline-danger pass-decide" data-id="' + row.id + '" data-decision="reject">Reject</button> ';
            }
            if (row.status === 'Requested' || row.status === 'Approved') {
                html += '<button type="button" class="btn btn-xs btn-link text-muted pass-cancel" data-id="' + row.id + '">cancel</button>';
            }
            return html;
        };
        $(document).on('click', '.pass-decide', function () {
            $.post(passes.decideUrl.replace(':id', $(this).data('id')), { decision: $(this).data('decision') })
                .done(function (res) { showSuccessToast(res.message); refreshTable(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
        $(document).on('click', '.pass-cancel', function () {
            if (!confirm('Cancel this pass?')) return;
            $.post(passes.cancelUrl.replace(':id', $(this).data('id')))
                .done(function (res) { showSuccessToast(res.message); refreshTable(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
        $('#gate-scan-form').on('submit', function (e) {
            e.preventDefault();
            var out = $('#gate-scan-result');
            $.post(passes.scanUrl, { code: $('#gate-code').val() })
                .done(function (res) { out.removeClass().addClass('alert mt-3 mb-0 alert-' + (res.status === 'Out' ? 'info' : 'success')).text(res.message); refreshTable(); })
                .fail(function (xhr) { out.removeClass().addClass('alert mt-3 mb-0 alert-danger').text(msg(xhr)); })
                .always(function () { $('#gate-code').val('').trigger('focus'); });
        });
        $('#gate-code').trigger('focus');
    }

    // ---------------------------------------------------------------- Visitors
    var visitors = window.HostelVisitors;
    if (visitors) {
        studentPicker(visitors.studentsUrl, null, true);
        window.hostelVisitorOut = function (value, row) {
            return value ? esc(value) : '<button type="button" class="btn btn-xs btn-outline-primary visitor-out" data-id="' + row.id + '">Check out</button>';
        };
        $(document).on('click', '.visitor-out', function () {
            $.post(visitors.checkoutUrl.replace(':id', $(this).data('id')))
                .done(function (res) { showSuccessToast(res.message); refreshTable(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
    }

    // ---------------------------------------------------------------- Fees
    var fees = window.HostelFees;
    if (fees) {
        window.hostelDueFormatter = function (value, row) {
            return esc(value) + (row.overdue ? ' <span class="badge badge-danger">overdue</span>' : '');
        };
        window.hostelFeeActions = function (value, row) {
            if (row.status !== 'Unpaid') return '';
            return '<button type="button" class="btn btn-xs btn-success fee-pay" data-id="' + row.id + '">Collect</button> ' +
                '<button type="button" class="btn btn-xs btn-outline-secondary fee-waive" data-id="' + row.id + '">Waive</button>';
        };
        $(document).on('click', '.fee-pay, .fee-waive', function () {
            var pay = $(this).hasClass('fee-pay'), data = { action: pay ? 'pay' : 'waive' };
            if (pay) {
                var mode = prompt('Payment mode: Cash, Cheque, Online or Bank', 'Cash');
                if (!mode) return;
                data.payment_mode = ['Cash', 'Cheque', 'Online', 'Bank'].indexOf(mode) >= 0 ? mode : 'Cash';
            } else if (!confirm('Waive this due?')) {
                return;
            }
            $.post(fees.settleUrl.replace(':id', $(this).data('id')), data)
                .done(function (res) { showSuccessToast(res.message); refreshTable(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
    }
})(jQuery);
