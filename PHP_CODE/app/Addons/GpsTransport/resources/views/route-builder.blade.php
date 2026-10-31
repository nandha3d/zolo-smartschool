@extends('layouts.master')

@section('title')
    {{ __('Route Builder') }}
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/vendor/leaflet/leaflet.css') }}" />
<script src="{{ asset('assets/vendor/leaflet/leaflet.js') }}"></script>

<style>
    .builder-container { display: flex; height: calc(100vh - 160px); min-height: 620px; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
    .builder-sidebar { width: 400px; background: #fafafa; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow-y: auto; }
    .builder-map { flex: 1; position: relative; }
    #route-builder-map { width: 100%; height: 100%; }
    .stop-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 10px; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between; }
    .stop-item.inactive { opacity: .55; }
    .stop-item:hover { border-color: #3b82f6; }
    .stop-seq-badge { width: 24px; height: 24px; border-radius: 50%; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; margin-right: 8px; flex-shrink: 0; }
    .afternoon .stop-seq-badge { background: #d97706; }
    .stop-actions .btn { padding: 1px 6px; }
    .builder-mode .btn.active { background: #2563eb; color: #fff; }
    @media (max-width: 900px) { .builder-container { flex-direction: column; height: auto; } .builder-sidebar { width: 100%; } #route-builder-map { height: 480px; } }
</style>

<div class="content-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h4 class="mb-0 font-weight-bold text-dark"><i class="fa fa-map text-primary mr-2"></i>{{ __('Route Builder') }}</h4>
            <small class="text-muted">{{ __('Place stops on the map, set geofence and halt time per stop, and order the morning pickup and afternoon drop separately.') }}</small>
        </div>
        <div class="mt-2">
            <button id="btn-save-route" class="btn btn-primary btn-sm" disabled><i class="fa fa-save mr-1"></i>{{ __('Save route') }}</button>
            <a href="{{ route('routes.index') }}" class="btn btn-outline-secondary btn-sm ml-2">{{ __('Manage Standard Routes') }}</a>
        </div>
    </div>

    <div class="builder-container">
        <div class="builder-sidebar p-3">
            <div class="form-group mb-2">
                <label class="font-weight-bold text-xs text-uppercase text-muted" for="route-select">{{ __('Select Route') }}</label>
                <select id="route-select" class="form-control">
                    <option value="">{{ __('-- Select Route to Edit --') }}</option>
                    @foreach ($routes as $route)
                        <option value="{{ $route->id }}">{{ $route->name }} ({{ $route->routePickupPoints->count() }} {{ __('stops') }})</option>
                    @endforeach
                </select>
            </div>

            <div id="route-editor" style="display:none;">
                <div class="form-row">
                    <div class="form-group col-6 mb-2">
                        <label class="text-xs text-muted mb-0" for="start-landmark">{{ __('Start landmark') }}</label>
                        <input id="start-landmark" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="form-group col-6 mb-2">
                        <label class="text-xs text-muted mb-0" for="end-landmark">{{ __('End landmark') }}</label>
                        <input id="end-landmark" class="form-control form-control-sm" maxlength="255">
                    </div>
                </div>

                <div class="mb-2">
                    <span class="text-xs text-muted d-block mb-1">{{ __('Clicking the map will') }}</span>
                    <div class="btn-group btn-group-sm builder-mode w-100" role="group">
                        <button type="button" class="btn btn-outline-primary active" data-mode="stop"><i class="fa fa-map-marker mr-1"></i>{{ __('Add a stop') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-mode="path"><i class="fa fa-pencil mr-1"></i>{{ __('Add a path point') }}</button>
                    </div>
                </div>

                <div class="input-group input-group-sm mb-2">
                    <select id="existing-stop" class="form-control">
                        <option value="">{{ __('Add an existing pickup point...') }}</option>
                        @foreach ($pickupPoints as $p)
                            <option value="{{ $p->id }}" data-lat="{{ $p->latitude }}" data-lng="{{ $p->longitude }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <div class="input-group-append"><button class="btn btn-outline-primary" type="button" id="btn-add-existing">{{ __('Add') }}</button></div>
                </div>

                <ul class="nav nav-tabs nav-fill mb-2" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-morning" role="tab"><i class="fa fa-sun-o mr-1"></i>{{ __('Morning pickup') }}</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-afternoon" role="tab"><i class="fa fa-moon-o mr-1"></i>{{ __('Afternoon drop') }}</a></li>
                </ul>
                <div class="tab-content p-0 border-0">
                    <div class="tab-pane fade show active" id="tab-morning" role="tabpanel">
                        <div id="morning-list"></div>
                    </div>
                    <div class="tab-pane fade afternoon" id="tab-afternoon" role="tabpanel">
                        <button type="button" class="btn btn-xs btn-outline-warning mb-2" id="btn-reverse"><i class="fa fa-exchange mr-1"></i>{{ __('Reverse the morning order') }}</button>
                        <div id="afternoon-list"></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap mt-2" style="gap:6px;">
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-line-from-stops"><i class="fa fa-random mr-1"></i>{{ __('Draw line through stops') }}</button>
                    @if (config('gpstransport.map.routing_url'))
                        <button type="button" class="btn btn-xs btn-outline-success" id="btn-snap"><i class="fa fa-road mr-1"></i>{{ __('Snap to roads') }}</button>
                    @endif
                    <button type="button" class="btn btn-xs btn-outline-danger" id="btn-clear-line"><i class="fa fa-eraser mr-1"></i>{{ __('Clear line') }}</button>
                </div>
                <div class="text-xs text-muted mt-2" id="route-distance"></div>
            </div>

            <div id="route-placeholder" class="text-center text-muted p-4 text-sm">{{ __('Select a route above to load its stops on the map.') }}</div>
        </div>

        <div class="builder-map"><div id="route-builder-map"></div></div>
    </div>
</div>

<div class="modal fade" id="stopModal" tabindex="-1" role="dialog" aria-labelledby="stopModalTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="stopModalTitle">{{ __('Stop details') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group"><label for="sm-name">{{ __('Name') }} *</label><input id="sm-name" class="form-control" maxlength="191"></div>
                <div class="form-row">
                    <div class="form-group col-6"><label for="sm-lat">{{ __('Latitude') }}</label><input id="sm-lat" type="number" step="0.000001" class="form-control"></div>
                    <div class="form-group col-6"><label for="sm-lng">{{ __('Longitude') }}</label><input id="sm-lng" type="number" step="0.000001" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label for="sm-radius">{{ __('Geofence radius (m)') }}</label>
                        <input id="sm-radius" type="number" min="20" max="1000" step="10" class="form-control" list="radius-options">
                        <datalist id="radius-options"><option value="50"><option value="100"><option value="150"><option value="200"></datalist>
                    </div>
                    <div class="form-group col-6"><label for="sm-halt">{{ __('Halt time (min)') }}</label><input id="sm-halt" type="number" min="0" max="60" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6"><label for="sm-pickup">{{ __('Morning pickup time') }}</label><input id="sm-pickup" type="time" class="form-control"></div>
                    <div class="form-group col-6"><label for="sm-drop">{{ __('Afternoon drop time') }}</label><input id="sm-drop" type="time" class="form-control"></div>
                </div>
                <div class="form-group"><label for="sm-landmark">{{ __('Landmark') }}</label><input id="sm-landmark" class="form-control" maxlength="255"></div>
                <div class="form-row">
                    <div class="form-group col-6"><label for="sm-contact">{{ __('Contact person') }}</label><input id="sm-contact" class="form-control" maxlength="191"></div>
                    <div class="form-group col-6"><label for="sm-phone">{{ __('Contact phone') }}</label><input id="sm-phone" class="form-control" maxlength="30"></div>
                </div>
                <div class="form-check"><input id="sm-active" type="checkbox" class="form-check-input"><label class="form-check-label" for="sm-active">{{ __('Active (the bus stops here)') }}</label></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="sm-save">{{ __('Apply') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var dataUrl = @json(route('gps.routes.data', ['id' => '__ID__']));
        var saveUrl = @json(route('gps.routes.save'));
        var routingUrl = @json(rtrim((string) config('gpstransport.map.routing_url'), '/'));
        var map, routeId = null, mode = 'stop';
        var stops = {};          // key -> stop
        var morning = [], afternoon = [];
        var markers = {}, circles = {};
        var waypoints = [], line = null, editingKey = null, seq = 0;

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
            });
        }
        function newKey() { return 'k' + (++seq); }

        document.addEventListener('DOMContentLoaded', function () {
            map = L.map('route-builder-map').setView([{{ (float) config('gpstransport.map.default_center_lat', 11.0168) }}, {{ (float) config('gpstransport.map.default_center_lng', 76.9558) }}], 13);
            L.tileLayer(@json(config('gpstransport.map.tile_url')), {attribution: @json(config('gpstransport.map.attribution')), maxZoom: 19}).addTo(map);

            map.on('click', function (e) {
                if (!routeId) { return; }
                if (mode === 'path') {
                    waypoints.push([e.latlng.lat, e.latlng.lng]);
                    drawLine();
                    return;
                }
                var name = prompt(@json(__('Name of the new stop')), @json(__('Stop')) + ' ' + (morning.length + 1));
                if (!name) { return; }
                addStop({name: name.substring(0, 191), latitude: e.latlng.lat, longitude: e.latlng.lng});
            });

            $('#route-select').on('change', function () { load(this.value); });
            $('.builder-mode .btn').on('click', function () {
                $('.builder-mode .btn').removeClass('active');
                $(this).addClass('active');
                mode = $(this).data('mode');
            });
            $('#btn-add-existing').on('click', addExisting);
            $('#btn-reverse').on('click', function () { afternoon = morning.slice().reverse(); render(); });
            $('#btn-line-from-stops').on('click', function () { lineFromStops(); });
            $('#btn-clear-line').on('click', function () { waypoints = []; drawLine(); });
            $('#btn-snap').on('click', snapToRoads);
            $('#sm-save').on('click', applyModal);
            $('#btn-save-route').on('click', save);
            $(document).on('click', '[data-act]', onAction);
        });

        function load(id) {
            Object.keys(markers).forEach(function (k) { map.removeLayer(markers[k]); map.removeLayer(circles[k]); });
            stops = {}; morning = []; afternoon = []; markers = {}; circles = {}; waypoints = [];
            drawLine();
            routeId = id || null;
            $('#route-editor').toggle(!!routeId);
            $('#route-placeholder').toggle(!routeId);
            $('#btn-save-route').prop('disabled', !routeId);
            if (!routeId) { return; }

            $.get(dataUrl.replace('__ID__', routeId)).done(function (res) {
                if (res.error) { alert(res.message); return; }
                $('#start-landmark').val(res.route.start_landmark || '');
                $('#end-landmark').val(res.route.end_landmark || '');
                var byAfternoon = [];
                res.stops.forEach(function (s) {
                    var key = newKey();
                    s.key = key;
                    stops[key] = s;
                    morning.push(key);
                    byAfternoon.push(s);
                    placeMarker(key);
                });
                afternoon = byAfternoon.sort(function (a, b) { return (a.afternoon_order || 999) - (b.afternoon_order || 999); })
                    .map(function (s) { return s.key; });
                waypoints = (res.geometry || []).map(function (p) { return [p.lat !== undefined ? p.lat : p[0], p.lng !== undefined ? p.lng : p[1]]; });
                if (!waypoints.length) { lineFromStops(true); } else { drawLine(); }
                render();
                fit();
            });
        }

        function addStop(data) {
            var key = newKey();
            stops[key] = $.extend({
                route_stop_id: null, pickup_point_id: null, geofence_radius: 100, halt_duration_minutes: 2,
                landmark: '', contact_person: '', contact_phone: '', pickup_time: null, drop_time: null, is_active: true
            }, data, {key: key});
            morning.push(key);
            afternoon.unshift(key);
            placeMarker(key);
            render();
            openModal(key);
        }

        function addExisting() {
            var opt = $('#existing-stop option:selected');
            var id = parseInt(opt.val(), 10);
            if (!id) { return; }
            var dup = Object.keys(stops).some(function (k) { return stops[k].pickup_point_id === id; });
            if (dup) { alert(@json(__('This stop is already on the route.'))); return; }
            var lat = parseFloat(opt.data('lat')), lng = parseFloat(opt.data('lng'));
            if (isNaN(lat) || isNaN(lng)) {
                var c = map.getCenter();
                lat = c.lat; lng = c.lng;
                alert(@json(__('This pickup point has no location yet. It was placed at the map centre: drag it to the right place.')));
            }
            addStop({pickup_point_id: id, name: opt.text().trim(), latitude: lat, longitude: lng});
        }

        function placeMarker(key) {
            var s = stops[key];
            if (s.latitude === null || s.longitude === null) { return; }
            var m = L.marker([s.latitude, s.longitude], {draggable: true}).addTo(map);
            m.on('dragend', function () {
                var p = m.getLatLng();
                s.latitude = +p.lat.toFixed(7);
                s.longitude = +p.lng.toFixed(7);
                circles[key].setLatLng(p);
            });
            m.on('click', function () { openModal(key); });
            markers[key] = m;
            circles[key] = L.circle([s.latitude, s.longitude], {radius: s.geofence_radius, color: '#3b82f6', fillColor: '#93c5fd', fillOpacity: 0.2, weight: 1.5}).addTo(map);
        }

        function refreshMarker(key) {
            var s = stops[key];
            var n = morning.indexOf(key) + 1;
            if (!markers[key]) { return; }
            markers[key].setIcon(L.divIcon({
                className: 'stop-map-icon',
                html: '<div style="background:' + (s.is_active ? '#2563eb' : '#94a3b8') + ';width:28px;height:28px;border-radius:50%;border:2px solid #fff;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:12px;box-shadow:0 2px 6px rgba(0,0,0,.3);">' + n + '</div>',
                iconSize: [28, 28], iconAnchor: [14, 14]
            }));
            markers[key].setLatLng([s.latitude, s.longitude]);
            markers[key].bindTooltip(esc(s.name));
            circles[key].setLatLng([s.latitude, s.longitude]).setRadius(s.geofence_radius);
        }

        function listHtml(order, list) {
            if (!order.length) { return '<div class="text-center text-muted p-3 text-sm">' + @json(__('No stops yet. Click the map to add one.')) + '</div>'; }
            return order.map(function (key, i) {
                var s = stops[key];
                var time = list === 'morning' ? s.pickup_time : s.drop_time;
                return '<div class="stop-item' + (s.is_active ? '' : ' inactive') + '">' +
                    '<div class="d-flex align-items-center" style="min-width:0;">' +
                        '<div class="stop-seq-badge">' + (i + 1) + '</div>' +
                        '<div style="min-width:0;"><strong class="text-sm text-dark d-block text-truncate">' + esc(s.name) + '</strong>' +
                        '<div class="text-xs text-muted">' + esc(s.geofence_radius) + 'm · ' + esc(s.halt_duration_minutes) + ' min' + (time ? ' · ' + esc(time) : '') + '</div></div>' +
                    '</div>' +
                    '<div class="stop-actions text-nowrap">' +
                        '<button type="button" class="btn btn-light" data-act="up" data-list="' + list + '" data-key="' + key + '" title="Up">&uarr;</button>' +
                        '<button type="button" class="btn btn-light" data-act="down" data-list="' + list + '" data-key="' + key + '" title="Down">&darr;</button>' +
                        '<button type="button" class="btn btn-light" data-act="edit" data-key="' + key + '" title="Edit"><i class="fa fa-pencil"></i></button>' +
                        '<button type="button" class="btn btn-light text-danger" data-act="remove" data-key="' + key + '" title="Remove"><i class="fa fa-trash"></i></button>' +
                    '</div></div>';
            }).join('');
        }

        function render() {
            $('#morning-list').html(listHtml(morning, 'morning'));
            $('#afternoon-list').html(listHtml(afternoon, 'afternoon'));
            Object.keys(stops).forEach(refreshMarker);
        }

        function onAction() {
            var key = $(this).data('key'), act = $(this).data('act');
            if (act === 'edit') { openModal(key); return; }
            if (act === 'remove') {
                if (!confirm(@json(__('Remove this stop from the route?')))) { return; }
                map.removeLayer(markers[key]); map.removeLayer(circles[key]);
                delete markers[key]; delete circles[key]; delete stops[key];
                morning = morning.filter(function (k) { return k !== key; });
                afternoon = afternoon.filter(function (k) { return k !== key; });
                render();
                return;
            }
            var arr = $(this).data('list') === 'morning' ? morning : afternoon;
            var i = arr.indexOf(key), j = act === 'up' ? i - 1 : i + 1;
            if (j < 0 || j >= arr.length) { return; }
            arr[i] = arr[j]; arr[j] = key;
            render();
        }

        function openModal(key) {
            var s = stops[key];
            editingKey = key;
            $('#sm-name').val(s.name);
            $('#sm-lat').val(s.latitude);
            $('#sm-lng').val(s.longitude);
            $('#sm-radius').val(s.geofence_radius);
            $('#sm-halt').val(s.halt_duration_minutes);
            $('#sm-pickup').val(s.pickup_time || '');
            $('#sm-drop').val(s.drop_time || '');
            $('#sm-landmark').val(s.landmark || '');
            $('#sm-contact').val(s.contact_person || '');
            $('#sm-phone').val(s.contact_phone || '');
            $('#sm-active').prop('checked', !!s.is_active);
            $('#stopModal').modal('show');
        }

        function applyModal() {
            var s = stops[editingKey];
            var name = $('#sm-name').val().trim();
            var lat = parseFloat($('#sm-lat').val()), lng = parseFloat($('#sm-lng').val());
            if (!name || isNaN(lat) || isNaN(lng)) { alert(@json(__('Name, latitude and longitude are required.'))); return; }
            s.name = name;
            s.latitude = lat;
            s.longitude = lng;
            s.geofence_radius = Math.min(1000, Math.max(20, parseInt($('#sm-radius').val(), 10) || 100));
            s.halt_duration_minutes = Math.min(60, Math.max(0, parseInt($('#sm-halt').val(), 10) || 0));
            s.pickup_time = $('#sm-pickup').val() || null;
            s.drop_time = $('#sm-drop').val() || null;
            s.landmark = $('#sm-landmark').val().trim();
            s.contact_person = $('#sm-contact').val().trim();
            s.contact_phone = $('#sm-phone').val().trim();
            s.is_active = $('#sm-active').is(':checked');
            $('#stopModal').modal('hide');
            render();
        }

        function lineFromStops(silent) {
            waypoints = morning.filter(function (k) { return stops[k].is_active; })
                .map(function (k) { return [stops[k].latitude, stops[k].longitude]; });
            drawLine();
            if (!silent && waypoints.length < 2) { alert(@json(__('Add at least two active stops first.'))); }
        }

        function snapToRoads() {
            var pts = morning.filter(function (k) { return stops[k].is_active; }).map(function (k) { return stops[k]; });
            if (pts.length < 2) { alert(@json(__('Add at least two active stops first.'))); return; }
            var coords = pts.map(function (s) { return s.longitude + ',' + s.latitude; }).join(';');
            $.getJSON(routingUrl + '/route/v1/driving/' + coords + '?overview=full&geometries=geojson')
                .done(function (res) {
                    if (!res.routes || !res.routes.length) { alert(@json(__('The routing server found no road route.'))); return; }
                    waypoints = res.routes[0].geometry.coordinates.map(function (c) { return [c[1], c[0]]; });
                    drawLine();
                })
                .fail(function () { alert(@json(__('Could not reach the routing server.'))); });
        }

        function distanceKm() {
            var d = 0;
            for (var i = 1; i < waypoints.length; i++) { d += map.distance(waypoints[i - 1], waypoints[i]); }
            return d / 1000;
        }

        function drawLine() {
            if (line) { map.removeLayer(line); line = null; }
            if (waypoints.length > 1) { line = L.polyline(waypoints, {color: '#2563eb', weight: 5, opacity: 0.8}).addTo(map); }
            $('#route-distance').text(waypoints.length > 1 ? @json(__('Route length')) + ': ' + distanceKm().toFixed(2) + ' km' : '');
        }

        function fit() {
            var pts = Object.keys(stops).map(function (k) { return [stops[k].latitude, stops[k].longitude]; }).concat(waypoints);
            if (pts.length) { map.fitBounds(pts, {padding: [50, 50], maxZoom: 16}); }
        }

        function save() {
            if (!routeId) { return; }
            var payload = {
                route_id: routeId,
                start_landmark: $('#start-landmark').val(),
                end_landmark: $('#end-landmark').val(),
                stops: morning.map(function (key, i) {
                    var s = stops[key];
                    return {
                        route_stop_id: s.route_stop_id, pickup_point_id: s.pickup_point_id, name: s.name,
                        latitude: s.latitude, longitude: s.longitude, geofence_radius: s.geofence_radius,
                        halt_duration_minutes: s.halt_duration_minutes, landmark: s.landmark, contact_person: s.contact_person,
                        contact_phone: s.contact_phone, pickup_time: s.pickup_time, drop_time: s.drop_time,
                        is_active: !!s.is_active, morning_order: i + 1, afternoon_order: afternoon.indexOf(key) + 1
                    };
                }),
                waypoints: waypoints.map(function (p) { return {lat: p[0], lng: p[1]}; }),
                total_distance: distanceKm().toFixed(2)
            };
            var btn = $('#btn-save-route').prop('disabled', true);
            $.ajax({
                url: saveUrl, method: 'POST', contentType: 'application/json', data: JSON.stringify(payload),
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
            }).done(function (res) {
                if (typeof showSuccessToast === 'function') { showSuccessToast(res.message); } else { alert(res.message); }
                load(routeId);
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || @json(__('Could not save the route.'));
                if (typeof showErrorToast === 'function') { showErrorToast(msg); } else { alert(msg); }
            }).always(function () { btn.prop('disabled', false); });
        }
    })();
</script>
@endsection
