/*
 * Library addon pages: catalogue (copies + barcode labels), the issue / return desk,
 * and the loans / fines / reservations registers. Each block only runs when its page
 * has set its config object.
 */
(function ($) {
    'use strict';

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function fail(xhr) {
        showErrorToast((xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.');
    }

    function url(template, id) {
        return template.replace(':id', encodeURIComponent(id));
    }

    // ---------------------------------------------------------------- Code 39
    // Barcodes are LB + digits, which Code 39 encodes directly, so labels need no library.
    var CODE39 = {
        '0': 'nnnwwnwnn', '1': 'wnnwnnnnw', '2': 'nnwwnnnnw', '3': 'wnwwnnnnn', '4': 'nnnwwnnnw',
        '5': 'wnnwwnnnn', '6': 'nnwwwnnnn', '7': 'nnnwnnwnw', '8': 'wnnwnnwnn', '9': 'nnwwnnwnn',
        'A': 'wnnnnwnnw', 'B': 'nnwnnwnnw', 'C': 'wnwnnwnnn', 'D': 'nnnnwwnnw', 'E': 'wnnnwwnnn',
        'F': 'nnwnwwnnn', 'G': 'nnnnnwwnw', 'H': 'wnnnnwwnn', 'I': 'nnwnnwwnn', 'J': 'nnnnwwwnn',
        'K': 'wnnnnnnww', 'L': 'nnwnnnnww', 'M': 'wnwnnnnwn', 'N': 'nnnnwnnww', 'O': 'wnnnwnnwn',
        'P': 'nnwnwnnwn', 'Q': 'nnnnnnwww', 'R': 'wnnnnnwwn', 'S': 'nnwnnnwwn', 'T': 'nnnnwnwwn',
        'U': 'wwnnnnnnw', 'V': 'nwwnnnnnw', 'W': 'wwwnnnnnn', 'X': 'nwnnwnnnw', 'Y': 'wwnnwnnnn',
        'Z': 'nwwnwnnnn', '-': 'nwnnnnwnw', '.': 'wwnnnnwnn', ' ': 'nwwnnnwnn', '*': 'nwnnwnwnn'
    };

    function code39Svg(text) {
        var data = '*' + String(text).toUpperCase().replace(/[^0-9A-Z.\- ]/g, '') + '*';
        var x = 0, bars = '';
        for (var i = 0; i < data.length; i++) {
            var pattern = CODE39[data[i]];
            for (var j = 0; j < 9; j++) {
                var w = pattern[j] === 'w' ? 3 : 1;
                if (j % 2 === 0) bars += '<rect x="' + x + '" y="0" width="' + w + '" height="50"/>';
                x += w;
            }
            x += 1;
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + x + ' 50" width="' + (x * 1.4) + '" height="50" preserveAspectRatio="none">' + bars + '</svg>';
    }

    // ---------------------------------------------------------------- Catalogue
    var cat = window.LibraryCatalog;
    if (cat) {
        window.libraryBookQueryParams = function (p) {
            return {
                limit: p.limit, offset: p.offset, order: p.order, sort: p.sort, search: p.search,
                category_id: $('#filter_category_id').val()
            };
        };
        $('#filter_category_id').on('change', function () { $('#table_list').bootstrapTable('refresh'); });

        window.libraryTitleFormatter = function (value, row) {
            var html = '<strong>' + esc(value) + '</strong>';
            if (row.subtitle) html += '<div class="small text-muted">' + esc(row.subtitle) + '</div>';
            if (row.has_file) html += ' <a class="badge badge-info" target="_blank" href="' + url(cat.ebookUrl, row.id) + '">e-book</a>';
            return html;
        };
        window.libraryAvailabilityFormatter = function (value, row) {
            var tone = value > 0 ? 'success' : (row.copies > 0 ? 'warning' : 'secondary');
            return '<span class="badge badge-' + tone + '">' + esc(value) + ' / ' + esc(row.copies) + '</span>';
        };

        var editFields = ['title', 'subtitle', 'author', 'publisher', 'isbn', 'category_id', 'edition', 'publication_year',
            'language', 'price', 'dewey_decimal_code', 'rack_location', 'shelf_number', 'description'];
        window.libraryBookEvents = {
            'click .edit-data': function (e, value, row) {
                editFields.forEach(function (f) { $('#edit_' + f).val(row[f] == null ? '' : row[f]); });
            },
            'click .library-copies': function (e, value, row) {
                e.preventDefault();
                openCopies(row.id, row.title);
            }
        };

        var currentBook = null, currentCopies = [];

        function openCopies(id, title) {
            currentBook = id;
            $('#library-copies-title').text(title);
            $('#library-copies-body').html('<tr><td colspan="7" class="text-muted">Loading…</td></tr>');
            $('#library-copies-modal').modal('show');
            loadCopies();
        }

        function select(name, options, current, disabled) {
            var html = '<select class="form-control form-control-sm library-copy-' + name + '"' + (disabled ? ' disabled' : '') + '>';
            options.forEach(function (o) { html += '<option' + (o === current ? ' selected' : '') + '>' + esc(o) + '</option>'; });
            if (options.indexOf(current) === -1) html += '<option selected>' + esc(current) + '</option>';
            return html + '</select>';
        }

        function loadCopies() {
            $.getJSON(url(cat.copiesUrl, currentBook)).done(function (res) {
                currentCopies = res.copies;
                var rows = res.copies.map(function (c) {
                    var locked = c.status === 'Issued' || c.status === 'Reserved';
                    return '<tr data-id="' + c.id + '"><td>' + esc(c.copy_number) + '</td><td class="text-monospace">' + esc(c.barcode) + '</td><td>' + esc(c.accession_number) + '</td>' +
                        '<td>' + select('status', cat.statuses, c.status, locked || !cat.canEdit) + '</td>' +
                        '<td>' + select('condition', cat.conditions, c.condition, !cat.canEdit) + '</td>' +
                        '<td>' + (c.borrower ? esc(c.borrower) + '<div class="text-muted">due ' + esc(c.due_date) + '</div>' : '') + '</td>' +
                        '<td>' + (cat.canDelete && !locked ? '<button type="button" class="btn btn-xs btn-link text-danger library-copy-delete"><i class="fa fa-trash"></i></button>' : '') + '</td></tr>';
                });
                $('#library-copies-body').html(rows.join('') || '<tr><td colspan="7" class="text-muted">No copies yet.</td></tr>');
            }).fail(fail);
        }

        $(document).on('change', '.library-copy-status, .library-copy-condition', function () {
            var tr = $(this).closest('tr');
            $.ajax({
                url: url(cat.copyUrl, tr.data('id')), type: 'PUT',
                data: { status: tr.find('.library-copy-status').val(), condition: tr.find('.library-copy-condition').val() }
            }).done(function (res) { showSuccessToast(res.message); $('#table_list').bootstrapTable('refresh'); }).fail(function (xhr) { fail(xhr); loadCopies(); });
        });

        $(document).on('click', '.library-copy-delete', function () {
            var tr = $(this).closest('tr');
            if (!confirm('Delete this copy?')) return;
            $.ajax({ url: url(cat.copyUrl, tr.data('id')), type: 'DELETE' })
                .done(function (res) { showSuccessToast(res.message); loadCopies(); $('#table_list').bootstrapTable('refresh'); }).fail(fail);
        });

        $('#library-add-copies').on('submit', function (e) {
            e.preventDefault();
            $.post(url(cat.copiesUrl, currentBook), { count: $('#library-copy-count').val(), acquisition_type: $('#library-copy-acquisition').val() })
                .done(function (res) { showSuccessToast(res.message); loadCopies(); $('#table_list').bootstrapTable('refresh'); }).fail(fail);
        });

        $('#library-print-labels').on('click', function () {
            var title = $('#library-copies-title').text();
            var labels = currentCopies.map(function (c) {
                return '<div class="label"><div class="t">' + esc(title) + '</div>' + code39Svg(c.barcode) + '<div class="c">' + esc(c.barcode) + ' · ' + esc(c.accession_number) + '</div></div>';
            }).join('');
            var w = window.open('', '_blank');
            w.document.write('<!doctype html><title>Labels</title><style>body{font:11px sans-serif;margin:8mm}.label{display:inline-block;width:62mm;margin:2mm;padding:2mm;border:1px dashed #ccc;text-align:center;page-break-inside:avoid}.t{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:bold}.c{font-family:monospace}svg{fill:#000}</style>' + labels + '<script>window.onload=function(){window.print()}<\/script>');
            w.document.close();
        });

        $('#library-category-form').on('submit', function (e) {
            e.preventDefault();
            $.post(cat.categoryStoreUrl, { name: $('#library-category-name').val() })
                .done(function () { location.reload(); }).fail(fail);
        });
        $(document).on('click', '.library-category-delete', function () {
            if (!confirm('Delete this category? Its books stay, uncategorised.')) return;
            $.ajax({ url: url(cat.categoryUrl, $(this).data('id')), type: 'DELETE' }).done(function () { location.reload(); }).fail(fail);
        });
    }

    // ---------------------------------------------------------------- Issue / return desk
    var desk = window.LibraryDesk;
    if (desk) {
        var member = null;
        var mode = 'issue';

        function setMode(next) {
            mode = next;
            $('#desk-mode-issue').toggleClass('btn-theme', mode === 'issue').toggleClass('btn-outline-secondary', mode !== 'issue');
            $('#desk-mode-return').toggleClass('btn-theme', mode === 'return').toggleClass('btn-outline-secondary', mode !== 'return');
            $('#desk-member-step').toggleClass('d-none', mode === 'return');
            $('#desk-copy-label').text(mode === 'issue' ? desk.t.scanToIssue : desk.t.scanToReturn);
            $('#desk-condition-wrap').toggleClass('d-none', mode !== 'return');
            $(mode === 'issue' && !member ? '#desk-member-code' : '#desk-barcode').trigger('focus');
        }

        function message(text, tone) {
            $('#desk-message').removeClass().addClass('alert alert-' + tone).text(text);
        }

        function renderMember(m) {
            member = m;
            if (!m) {
                $('#desk-member').addClass('d-none');
                return;
            }
            var loans = m.loans.map(function (l) {
                var late = l.overdue_days > 0;
                return '<tr class="' + (late ? 'table-danger' : '') + '"><td>' + esc(l.title) + '<div class="text-muted small text-monospace">' + esc(l.barcode) + '</div></td>' +
                    '<td>' + esc(l.due_date) + (late ? '<div class="small text-danger">' + esc(l.overdue_days) + ' days late · ' + esc(l.accrued_fine) + '</div>' : '') + '</td>' +
                    '<td class="text-nowrap"><button type="button" class="btn btn-xs btn-outline-primary desk-renew" data-id="' + l.id + '">' + esc(desk.t.renew) + '</button> ' +
                    '<button type="button" class="btn btn-xs btn-outline-danger desk-lost" data-id="' + l.id + '">' + esc(desk.t.lost) + '</button></td></tr>';
            }).join('');
            $('#desk-member').removeClass('d-none');
            $('#desk-member-name').text(m.name);
            $('#desk-member-meta').text([m.membership_number, m.type, m.class].filter(Boolean).join(' · '));
            $('#desk-member-image').attr('src', m.image || '');
            $('#desk-member-quota').text(m.loan_count + ' / ' + m.max_books);
            $('#desk-member-fines').text(Number(m.unpaid_fines).toFixed(2)).toggleClass('text-danger', m.unpaid_fines > 0);
            $('#desk-member-status').text(m.can_borrow ? desk.t.eligible : desk.t.blocked)
                .removeClass('badge-success badge-danger').addClass(m.can_borrow ? 'badge-success' : 'badge-danger');
            $('#desk-loans').html(loans || '<tr><td colspan="3" class="text-muted">' + esc(desk.t.noLoans) + '</td></tr>');
        }

        $('#desk-mode-issue').on('click', function () { setMode('issue'); });
        $('#desk-mode-return').on('click', function () { setMode('return'); });
        $(document).on('keydown', function (e) {
            if (e.key === 'F2') { e.preventDefault(); setMode(mode === 'issue' ? 'return' : 'issue'); }
        });

        $('#desk-member-form').on('submit', function (e) {
            e.preventDefault();
            var code = $('#desk-member-code').val();
            $.getJSON(desk.memberUrl, { code: code }).done(function (res) {
                renderMember(res.member);
                message(res.member.can_borrow ? desk.t.memberReady : desk.t.memberBlocked, res.member.can_borrow ? 'info' : 'warning');
                $('#desk-member-code').val('');
                $('#desk-barcode').trigger('focus');
            }).fail(function (xhr) { renderMember(null); message((xhr.responseJSON || {}).message || 'Not found', 'danger'); });
        });

        $('#desk-copy-form').on('submit', function (e) {
            e.preventDefault();
            var barcode = $('#desk-barcode').val();
            if (!barcode) return;
            var req = mode === 'issue'
                ? (member ? $.post(desk.issueUrl, { member_id: member.id, barcode: barcode }) : null)
                : $.post(desk.returnUrl, { barcode: barcode, condition: $('#desk-condition').val() });
            if (!req) { message(desk.t.scanMemberFirst, 'warning'); $('#desk-member-code').trigger('focus'); return; }
            req.done(function (res) {
                message(res.message, res.fine > 0 || res.reserved_for ? 'warning' : 'success');
                if (mode === 'issue' || (member && res.member && res.member.id === member.id)) renderMember(res.member);
                if (mode === 'return' && res.member && !member) renderMember(res.member);
            }).fail(function (xhr) { message((xhr.responseJSON || {}).message || 'Failed', 'danger'); })
                .always(function () { $('#desk-barcode').val('').trigger('focus'); });
        });

        $(document).on('click', '.desk-renew, .desk-lost', function () {
            var lost = $(this).hasClass('desk-lost');
            if (lost && !confirm(desk.t.confirmLost)) return;
            $.post((lost ? desk.lostUrl : desk.renewUrl).replace(':id', $(this).data('id')))
                .done(function (res) { message(res.message, lost ? 'warning' : 'success'); renderMember(res.member); })
                .fail(function (xhr) { message((xhr.responseJSON || {}).message || 'Failed', 'danger'); });
        });

        var searchTimer = null;
        $('#desk-reserve-search').on('input', function () {
            clearTimeout(searchTimer);
            var q = $(this).val();
            searchTimer = setTimeout(function () {
                $.getJSON(desk.booksUrl, { q: q }).done(function (res) {
                    $('#desk-reserve-results').html(res.books.map(function (b) {
                        return '<li class="list-group-item d-flex justify-content-between align-items-center px-2 py-1 small">' +
                            '<span>' + esc(b.title) + ' <span class="text-muted">' + esc(b.author) + '</span></span>' +
                            (b.available_count > 0 ? '<span class="badge badge-success">' + esc(desk.t.onShelf) + '</span>'
                                : '<button type="button" class="btn btn-xs btn-outline-primary desk-reserve" data-id="' + b.id + '">' + esc(desk.t.reserve) + '</button>') + '</li>';
                    }).join(''));
                });
            }, 250);
        });
        $(document).on('click', '.desk-reserve', function () {
            if (!member) { message(desk.t.scanMemberFirst, 'warning'); return; }
            $.post(desk.reserveUrl, { member_id: member.id, book_id: $(this).data('id') })
                .done(function (res) { message(res.message, 'success'); })
                .fail(function (xhr) { message((xhr.responseJSON || {}).message || 'Failed', 'danger'); });
        });

        $('#desk-copy-lookup').on('click', function () {
            var barcode = $('#desk-barcode').val();
            if (!barcode) return;
            $.getJSON(desk.copyUrl, { barcode: barcode }).done(function (res) {
                var c = res.copy;
                message(c.title + ' — ' + c.status + (c.location ? ' · ' + c.location : '') + (c.borrower ? ' · ' + c.borrower + ' (due ' + c.due_date + ')' : ''), 'info');
            }).fail(function (xhr) { message((xhr.responseJSON || {}).message || 'Not found', 'danger'); });
        });

        setMode('issue');
    }

    // ---------------------------------------------------------------- Registers
    var reg = window.LibraryRegister;
    if (reg) {
        window.libraryRegisterQueryParams = function (p) {
            return { limit: p.limit, offset: p.offset, order: p.order, sort: p.sort, search: p.search, status: $('#filter_status').val() };
        };
        $('#filter_status').on('change', function () { $('#table_list').bootstrapTable('refresh'); });

        window.libraryStatusFormatter = function (value, row) {
            var tone = { Issued: 'info', Overdue: 'danger', Returned: 'success', Lost: 'dark', Unpaid: 'danger', Paid: 'success', Waived: 'secondary', Pending: 'warning', Ready: 'success' }[value] || 'secondary';
            var late = row.overdue_days > 0 && (value === 'Issued' || value === 'Overdue') ? ' <small class="text-danger">' + esc(row.overdue_days) + 'd · ' + esc(row.accrued_fine) + '</small>' : '';
            return '<span class="badge badge-' + tone + '">' + esc(value) + '</span>' + late;
        };

        window.libraryLoanActions = function (value, row) {
            if (!reg.canCirculate || (row.status !== 'Issued' && row.status !== 'Overdue')) return '';
            return '<button type="button" class="btn btn-xs btn-outline-primary library-renew" data-id="' + row.id + '">Renew</button>';
        };
        window.libraryFineActions = function (value, row) {
            if (!reg.canCirculate || row.status !== 'Unpaid') return '';
            return '<button type="button" class="btn btn-xs btn-success library-fine-pay" data-id="' + row.id + '">Collect</button> ' +
                '<button type="button" class="btn btn-xs btn-outline-secondary library-fine-waive" data-id="' + row.id + '">Waive</button>';
        };
        window.libraryReservationActions = function (value, row) {
            if (!reg.canCirculate || (row.status !== 'Pending' && row.status !== 'Ready')) return '';
            return '<button type="button" class="btn btn-xs btn-outline-danger library-reservation-cancel" data-id="' + row.id + '">Cancel</button>';
        };

        function act(request) {
            request.done(function (res) { showSuccessToast(res.message); $('#table_list').bootstrapTable('refresh'); }).fail(fail);
        }
        $(document).on('click', '.library-renew', function () { act($.post(reg.renewUrl.replace(':id', $(this).data('id')))); });
        $(document).on('click', '.library-fine-pay', function () {
            var mode = prompt('Payment mode (Cash or Online)', 'Cash');
            if (mode) act($.post(reg.settleUrl.replace(':id', $(this).data('id')), { action: 'pay', payment_mode: mode === 'Online' ? 'Online' : 'Cash' }));
        });
        $(document).on('click', '.library-fine-waive', function () {
            var reason = prompt('Reason for waiving this fine');
            if (reason) act($.post(reg.settleUrl.replace(':id', $(this).data('id')), { action: 'waive', waived_reason: reason }));
        });
        $(document).on('click', '.library-reservation-cancel', function () {
            if (confirm('Cancel this reservation?')) act($.post(reg.cancelUrl.replace(':id', $(this).data('id'))));
        });
    }
})(jQuery);
