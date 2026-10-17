/*
 * Canteen addon pages: shared AJAX helpers, the touch POS (cart, RFID customer,
 * allergen warning), the kitchen board, the order register, wallets and the menu.
 */
(function ($) {
    'use strict';

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function msg(xhr) {
        return (xhr.responseJSON && xhr.responseJSON.message) || 'Something went wrong.';
    }

    function money(v) {
        return Number(v || 0).toFixed(2);
    }

    function refresh(selector) {
        if ($(selector || '#table_list').length) $(selector || '#table_list').bootstrapTable('refresh');
    }

    $(document).on('submit', 'form[data-canteen-form]', function (e) {
        e.preventDefault();
        var form = $(this), button = form.find('[type=submit]').prop('disabled', true);
        $.ajax({ url: form.attr('action'), type: 'POST', data: new FormData(this), processData: false, contentType: false })
            .done(function (res) {
                showSuccessToast(res.message);
                if (form.data('after') === 'reload') return location.reload();
                refresh();
                form[0].reset();
            })
            .fail(function (xhr) { showErrorToast(msg(xhr)); })
            .always(function () { button.prop('disabled', false); });
    });
    $(document).on('click', '[data-canteen-delete]', function () {
        if (!confirm('Delete this item?')) return;
        $.ajax({ url: $(this).data('canteen-delete'), type: 'DELETE' })
            .done(function (res) { showSuccessToast(res.message); location.reload(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
    });
    $(document).on('click', '[data-canteen-post]', function () {
        $.post($(this).data('canteen-post'))
            .done(function (res) { showSuccessToast(res.message); location.reload(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
    });

    window.canteenQueryParams = function (p) {
        return {
            limit: p.limit, offset: p.offset, order: p.order, sort: p.sort, search: p.search,
            status: $('#filter_status').val(), date: $('#filter_date').val(), category_id: $('#filter_category_id').val()
        };
    };
    $('#filter_status, #filter_date, #filter_category_id').on('change', function () { refresh(); });

    window.canteenAllergenFormatter = function (value) {
        return (value || []).map(function (a) { return '<span class="badge badge-danger mr-1">' + esc(a) + '</span>'; }).join('');
    };

    // ---------------------------------------------------------------- Menu
    if (window.CanteenMenu) {
        var menu = window.CanteenMenu;
        window.canteenDietFormatter = function (value) {
            var tone = { Veg: 'success', Vegan: 'success', 'Non-Veg': 'danger', Egg: 'warning' }[value] || 'secondary';
            return '<span class="badge badge-' + tone + '">' + esc(value) + '</span>';
        };
        window.canteenStockFormatter = function (value, row) { return row.track_stock ? value : '—'; };
        window.canteenAvailableFormatter = function (value, row) {
            return '<button type="button" class="btn btn-xs ' + (value ? 'btn-success' : 'btn-outline-secondary') + ' canteen-toggle" data-id="' + row.id + '">' + (value ? 'On menu' : 'Sold out') + '</button>';
        };
        window.canteenItemEvents = {
            'click .edit-data': function (e, value, row) {
                ['name', 'code', 'category_id', 'dietary_type', 'price', 'cost_price', 'calories', 'stock_qty'].forEach(function (f) {
                    $('#edit_' + f).val(row[f] == null ? '' : row[f]);
                });
                $('#edit_track_stock').prop('checked', !!row.track_stock);
                $('#edit_is_available').prop('checked', !!row.is_available);
                $('.edit_allergen').each(function () { $(this).prop('checked', (row.allergens || []).indexOf($(this).val()) >= 0); });
            }
        };
        $(document).on('click', '.canteen-toggle', function () {
            $.post(menu.toggleUrl.replace(':id', $(this).data('id')))
                .done(function (res) { showSuccessToast(res.message); refresh(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
    }

    // ---------------------------------------------------------------- POS
    if (window.CanteenPos) {
        var pos = window.CanteenPos;
        var cart = {}, customer = null, category = '';
        var byId = {};
        pos.items.forEach(function (i) { byId[i.id] = i; });

        var conflict = function (item) {
            if (!customer) return null;
            var hits = (item.allergens || []).filter(function (a) { return (customer.allergens || []).indexOf(a) >= 0; });
            if (hits.length) return 'contains ' + hits.join(', ');
            if (item.category_id && (customer.blocked_category_ids || []).indexOf(item.category_id) >= 0) return 'blocked by guardian';
            return null;
        };

        var renderItems = function () {
            var q = ($('#pos-search').val() || '').toLowerCase();
            $('#pos-items').html(pos.items.filter(function (i) {
                return (!category || String(i.category_id) === String(category)) && (!q || i.name.toLowerCase().indexOf(q) >= 0 || (i.code || '').toLowerCase() === q);
            }).map(function (i) {
                var warn = conflict(i);
                return '<button type="button" class="pos-item" data-id="' + i.id + '"' + (i.available ? '' : ' disabled') + '>' +
                    '<div><span class="pos-diet ' + esc(i.dietary_type) + '"></span>' + esc(i.name) + '</div>' +
                    '<div class="price">' + money(i.price) + '</div>' +
                    (i.stock !== null ? '<div class="small text-muted">' + esc(i.stock) + ' left</div>' : '') +
                    (warn ? '<div class="small text-danger"><i class="fa fa-exclamation-triangle"></i> ' + esc(warn) + '</div>' : '') +
                    '</button>';
            }).join(''));
        };

        var renderCart = function () {
            var total = 0;
            var rows = Object.keys(cart).map(function (id) {
                var item = byId[id], qty = cart[id];
                total += item.price * qty;
                return '<tr data-id="' + id + '"><td>' + esc(item.name) + '</td><td class="text-nowrap"><button type="button" class="btn btn-xs btn-outline-secondary pos-dec">−</button> ' + qty +
                    ' <button type="button" class="btn btn-xs btn-outline-secondary pos-inc">+</button></td><td class="text-right">' + money(item.price * qty) + '</td></tr>';
            });
            $('#pos-cart').html(rows.join('') || '<tr><td class="text-muted small">Tap items to add them.</td></tr>');
            $('#pos-total').text(money(total));
            return total;
        };

        var renderCustomer = function () {
            $('#pos-customer').toggleClass('d-none', !customer);
            if (customer) {
                $('#pos-customer-name').text(customer.name);
                $('#pos-customer-class').text(customer.class || '');
                $('#pos-customer-image').attr('src', customer.image || '');
                $('#pos-balance').text(money(customer.balance));
                $('#pos-left').text(customer.left_today === null ? 'no limit' : money(customer.left_today));
                var alerts = [];
                if (customer.is_locked) alerts.push('Wallet locked');
                if ((customer.allergens || []).length) alerts.push('Allergies: ' + customer.allergens.join(', '));
                if ((customer.blocked_categories || []).length) alerts.push('Blocked: ' + customer.blocked_categories.join(', '));
                $('#pos-alerts').text(alerts.join(' · '));
            }
            renderItems();
        };

        var allergenAlarm = function (text) {
            $('#pos-allergen-text').text(text);
            $('#pos-allergen-modal').modal('show');
        };

        $(document).on('click', '.pos-cat', function () {
            category = $(this).data('id');
            $('.pos-cat').removeClass('btn-theme').addClass('btn-outline-secondary');
            $(this).addClass('btn-theme').removeClass('btn-outline-secondary');
            renderItems();
        });
        $('#pos-search').on('input', renderItems).on('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var code = $(this).val().toLowerCase(), hit = pos.items.filter(function (i) { return (i.code || '').toLowerCase() === code && i.available; })[0];
            if (hit) { $('.pos-item[data-id="' + hit.id + '"]').trigger('click'); $(this).val(''); renderItems(); }
        });
        $(document).on('click', '.pos-item', function () {
            var item = byId[$(this).data('id')], warn = conflict(item);
            if (warn && warn.indexOf('contains') === 0) {
                allergenAlarm(customer.name + ' has a registered allergy: ' + item.name + ' ' + warn + '. Item blocked.');
                return;
            }
            if (warn) { showErrorToast(item.name + ' is ' + warn + '.'); return; }
            cart[item.id] = (cart[item.id] || 0) + 1;
            renderCart();
        });
        $(document).on('click', '.pos-inc, .pos-dec', function () {
            var id = $(this).closest('tr').data('id');
            cart[id] += $(this).hasClass('pos-inc') ? 1 : -1;
            if (cart[id] <= 0) delete cart[id];
            renderCart();
        });
        $('#pos-clear').on('click', function () { cart = {}; renderCart(); });

        $('#pos-customer-form').on('submit', function (e) {
            e.preventDefault();
            $.getJSON(pos.customerUrl, { code: $('#pos-customer-code').val() })
                .done(function (res) {
                    customer = res.wallet;
                    renderCustomer();
                    // Anything already in the cart that this customer may not have comes out.
                    Object.keys(cart).forEach(function (id) {
                        var warn = conflict(byId[id]);
                        if (warn) {
                            delete cart[id];
                            if (warn.indexOf('contains') === 0) allergenAlarm(customer.name + ' has a registered allergy: ' + byId[id].name + ' ' + warn + '. Removed from the order.');
                        }
                    });
                    renderCart();
                })
                .fail(function (xhr) { showErrorToast(msg(xhr)); })
                .always(function () { $('#pos-customer-code').val(''); });
        });
        $('#pos-customer-clear').on('click', function () { customer = null; renderCustomer(); });

        $(document).on('click', '.pos-pay', function () {
            var mode = $(this).data('mode');
            var lines = Object.keys(cart).map(function (id) { return { item_id: id, quantity: cart[id] }; });
            if (!lines.length) { showErrorToast('The order is empty.'); return; }
            if (mode === 'Wallet' && !customer) { showErrorToast('Tap the student\'s card first.'); $('#pos-customer-code').trigger('focus'); return; }
            var buttons = $('.pos-pay').prop('disabled', true);
            $.post(pos.checkoutUrl, { lines: lines, payment_mode: mode, wallet_id: customer ? customer.id : '', notes: $('#pos-notes').val() })
                .done(function (res) {
                    $('#pos-last').removeClass('d-none').html('<strong>Token #' + esc(res.order.token_number) + '</strong> · ' + money(res.order.total_amount) + ' · ' + esc(res.order.payment_mode));
                    lines.forEach(function (l) { var it = byId[l.item_id]; if (it.stock !== null) { it.stock -= l.quantity; it.available = it.stock > 0; } });
                    cart = {};
                    $('#pos-notes').val('');
                    if (res.wallet) customer = res.wallet;
                    renderCart();
                    renderCustomer();
                })
                .fail(function (xhr) {
                    var res = xhr.responseJSON || {};
                    if (res.allergen) allergenAlarm(res.message); else showErrorToast(msg(xhr));
                })
                .always(function () { buttons.prop('disabled', false); });
        });

        renderItems();
        renderCart();
        $('#pos-customer-code').trigger('focus');
    }

    // ---------------------------------------------------------------- Kitchen
    if (window.CanteenKitchen) {
        var kitchen = window.CanteenKitchen;
        var next = { Pending: 'Preparing', Preparing: 'Ready', Ready: 'Completed' };
        var loadKitchen = function () {
            $.getJSON(kitchen.ordersUrl).done(function (res) {
                ['Pending', 'Preparing', 'Ready'].forEach(function (status) {
                    var list = res.orders.filter(function (o) { return o.status === status; });
                    $('#kitchen-count-' + status).text(list.length);
                    $('#kitchen-' + status).html(list.map(function (o) {
                        return '<div class="border rounded p-2 mb-2"><div class="d-flex justify-content-between"><strong class="h4 mb-0">#' + esc(o.token_number) + '</strong><span class="small text-muted">' + esc(o.time) + (o.order_type === 'Pre_Order' ? ' · pre-order' : '') + '</span></div>' +
                            '<div class="small">' + esc(o.customer || 'Walk-in') + '</div><ul class="small mb-1 pl-3">' + o.items.map(function (l) { return '<li>' + esc(l.quantity) + '× ' + esc(l.name) + '</li>'; }).join('') + '</ul>' +
                            (o.notes ? '<div class="small text-warning">' + esc(o.notes) + '</div>' : '') +
                            '<button type="button" class="btn btn-sm btn-theme kitchen-next" data-id="' + o.id + '" data-status="' + next[status] + '">' + esc(next[status]) + ' →</button> ' +
                            '<button type="button" class="btn btn-sm btn-link text-danger kitchen-cancel" data-id="' + o.id + '">cancel</button></div>';
                    }).join('') || '<div class="text-muted small">Nothing here.</div>');
                });
            });
        };
        $(document).on('click', '.kitchen-next, .kitchen-cancel', function () {
            var cancel = $(this).hasClass('kitchen-cancel'), data = { status: cancel ? 'Cancelled' : $(this).data('status') };
            if (cancel) { data.reason = prompt('Reason for cancelling (wallet payments are refunded)'); if (data.reason === null) return; }
            $.post(kitchen.statusUrl.replace(':id', $(this).data('id')), data)
                .done(function (res) { showSuccessToast(res.message); loadKitchen(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
        loadKitchen();
        setInterval(loadKitchen, 10000);
    }

    // ---------------------------------------------------------------- Orders register
    if (window.CanteenOrders) {
        window.canteenOrderActions = function (value, row) {
            return ['Completed', 'Cancelled'].indexOf(row.status) >= 0 ? '' : '<button type="button" class="btn btn-xs btn-outline-danger order-cancel" data-id="' + row.id + '">Cancel</button>';
        };
        $(document).on('click', '.order-cancel', function () {
            var reason = prompt('Reason for cancelling (wallet payments are refunded)');
            if (reason === null) return;
            $.post(window.CanteenOrders.statusUrl.replace(':id', $(this).data('id')), { status: 'Cancelled', reason: reason })
                .done(function (res) { showSuccessToast(res.message); refresh(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
    }

    // ---------------------------------------------------------------- Wallets
    if (window.CanteenWallets) {
        var wallets = window.CanteenWallets, current = null;
        window.canteenLedgerParams = function (p) {
            return { limit: p.limit, offset: p.offset, order: p.order, sort: p.sort, wallet_id: current ? current.id : '' };
        };
        window.canteenWalletName = function (value, row) {
            return '<a href="#" class="wallet-open" data-code="' + esc(row.user_id) + '">' + esc(value) + '</a>' + (row.is_locked ? ' <span class="badge badge-dark">locked</span>' : '');
        };
        var show = function (w) {
            current = w;
            $('#wallet-panel').removeClass('d-none');
            $('#wallet-name').text(w.name);
            $('#wallet-class').text(w.class || '');
            $('#wallet-balance').text(money(w.balance));
            var f = $('#wallet-controls-form');
            f.find('[name=rfid_card]').val(w.rfid_card || '');
            f.find('[name=daily_limit]').val(w.daily_limit === null ? '' : w.daily_limit);
            $('.wallet-allergen').each(function () { $(this).prop('checked', (w.allergens || []).indexOf($(this).val()) >= 0); });
            $('.wallet-blocked').each(function () { $(this).prop('checked', (w.blocked_category_ids || []).indexOf(Number($(this).val())) >= 0); });
            $('#wallet-locked').prop('checked', !!w.is_locked);
            $('#ledger-for').text('— ' + w.name);
            refresh('#ledger_list');
        };
        var lookup = function (code) {
            $.getJSON(wallets.lookupUrl, { code: code }).done(function (res) { show(res.wallet); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        };
        $('#wallet-lookup-form').on('submit', function (e) { e.preventDefault(); lookup($('#wallet-code').val()); });
        $(document).on('click', '.wallet-open', function (e) { e.preventDefault(); lookup(String($(this).data('code'))); });

        $('#wallet-topup-form').on('submit', function (e) {
            e.preventDefault();
            var form = this;
            $.post(wallets.topupUrl.replace(':id', current.id), $(form).serialize())
                .done(function (res) { showSuccessToast(res.message); form.reset(); lookup(String(current.user_id)); refresh(); })
                .fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
        $('#wallet-adjust-form').on('submit', function (e) {
            e.preventDefault();
            var form = this;
            $.post(wallets.adjustUrl.replace(':id', current.id), $(form).serialize())
                .done(function (res) { showSuccessToast(res.message); form.reset(); lookup(String(current.user_id)); refresh(); })
                .fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
        $('#wallet-controls-form').on('submit', function (e) {
            e.preventDefault();
            var f = $(this);
            $.ajax({
                url: wallets.updateUrl.replace(':id', current.id), type: 'PUT',
                data: {
                    rfid_card: f.find('[name=rfid_card]').val(),
                    daily_limit: f.find('[name=daily_limit]').val(),
                    allergens: $('.wallet-allergen:checked').map(function () { return $(this).val(); }).get(),
                    blocked_category_ids: $('.wallet-blocked:checked').map(function () { return $(this).val(); }).get(),
                    is_locked: $('#wallet-locked').is(':checked') ? 1 : 0
                }
            }).done(function (res) { showSuccessToast(res.message); show(res.wallet); refresh(); }).fail(function (xhr) { showErrorToast(msg(xhr)); });
        });
    }
})(jQuery);
