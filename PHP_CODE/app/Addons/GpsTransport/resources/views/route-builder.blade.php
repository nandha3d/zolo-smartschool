@extends('layouts.master')

@section('title')
    {{ __('Route Builder') }}
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/vendor/leaflet/leaflet.css') }}" />
<script src="{{ asset('assets/vendor/leaflet/leaflet.js') }}"></script>

<style>
    .builder-container { display: flex; height: calc(100vh - 160px); min-height: 640px; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
    .builder-sidebar { width: 420px; background: #fafafa; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow-y: auto; }
    .builder-map { flex: 1; position: relative; }
    #route-builder-map { width: 100%; height: 100%; }
    .stop-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 10px; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between; transition: all .2s; }
    .stop-item.inactive { opacity: .55; }
    .stop-item:hover { border-color: #3b82f6; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .stop-seq-badge { width: 24px; height: 24px; border-radius: 50%; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; margin-right: 8px; flex-shrink: 0; }
    .afternoon .stop-seq-badge { background: #d97706; }
    .stop-actions .btn { padding: 1px 6px; }
    .builder-mode .btn.active { background: #2563eb; color: #fff; }
    .map-floating-search { position: absolute; top: 12px; left: 60px; z-index: 1000; width: 320px; }
    .search-results-dropdown { max-height: 220px; overflow-y: auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; }
    .search-result-item { padding: 6px 10px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
    .search-result-item:hover { background: #f8fafc; color: #2563eb; }
    .stop-quick-popup .leaflet-popup-content-wrapper { padding: 8px; border-radius: 8px; }
    .stop-quick-popup .leaflet-popup-content { margin: 4px; font-size: 12px; min-width: 230px; }
    @media (max-width: 900px) { .builder-container { flex-direction: column; height: auto; } .builder-sidebar { width: 100%; } #route-builder-map { height: 480px; } .map-floating-search { width: calc(100% - 80px); } }
</style>

<div class="content-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h4 class="mb-0 font-weight-bold text-dark"><i class="fa fa-map text-primary mr-2"></i>{{ __('Route Builder') }}</h4>
            <small class="text-muted">{{ __('Interactive stop placement, vehicle assignment, geofencing, and intelligent timing calculator.') }}</small>
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

                <!-- Assigned Vehicle & Driver Card -->
                <div class="card border rounded p-2 mb-2 bg-white shadow-sm" id="route-vehicle-card">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="font-weight-bold text-xs text-uppercase text-muted"><i class="fa fa-bus text-primary mr-1"></i>{{ __('Assigned Vehicle') }}</span>
                        <button type="button" class="btn btn-xs btn-outline-primary" id="btn-manage-vehicle"><i class="fa fa-pencil mr-1"></i>{{ __('Assign / Change') }}</button>
                    </div>
                    <div id="route-vehicle-info" class="text-xs">
                        <span class="text-muted">{{ __('No vehicle assigned to this route yet.') }}</span>
                    </div>
                </div>

                <div class="mb-2">
                    <span class="text-xs text-muted d-block mb-1">{{ __('Clicking the map will') }}</span>
                    <div class="btn-group btn-group-sm builder-mode w-100" role="group">
                        <button type="button" class="btn btn-outline-primary active" data-mode="stop"><i class="fa fa-map-marker mr-1"></i>{{ __('Add a stop') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-mode="path"><i class="fa fa-pencil mr-1"></i>{{ __('Add a path point') }}</button>
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size:11px;">
                        <i class="fa fa-info-circle text-info mr-1"></i>{{ __('Tip: Click anywhere on the map to drop a stop instantly with auto-timing!') }}
                    </small>
                </div>

                <div class="input-group input-group-sm mb-2">
                    <select id="existing-stop" class="form-control">
                        <option value="">{{ __('Add an existing pickup point...') }}</option>
                        @foreach ($pickupPoints as $p)
                            <option value="{{ $p->id }}" data-lat="{{ $p->latitude }}" data-lng="{{ $p->longitude }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <div class="input-group-append">
                        <button class="btn btn-outline-primary" type="button" id="btn-add-existing">{{ __('Add') }}</button>
                        <button class="btn btn-outline-secondary" type="button" id="btn-open-batch-add" title="{{ __('Batch Add Multiple Stops') }}"><i class="fa fa-list-ul mr-1"></i>{{ __('Batch') }}</button>
                    </div>
                </div>

                <!-- Sequence Header & Auto-Scheduler -->
                <div class="d-flex justify-content-between align-items-center mt-2 mb-1">
                    <span class="text-xs text-muted font-weight-bold text-uppercase">{{ __('Stops Sequence') }}</span>
                    <button type="button" class="btn btn-xs btn-outline-info" id="btn-open-auto-schedule" title="{{ __('Calculate sequential stop times automatically') }}">
                        <i class="fa fa-clock-o mr-1"></i>{{ __('⚡ Auto-Schedule Times') }}
                    </button>
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

        <div class="builder-map">
            <!-- Search location directly on map -->
            <div class="map-floating-search">
                <div class="input-group input-group-sm shadow-sm">
                    <div class="input-group-prepend"><span class="input-group-text bg-white border-right-0"><i class="fa fa-search text-primary"></i></span></div>
                    <input type="text" id="map-search-input" class="form-control border-left-0" placeholder="{{ __('Search place or address to drop stop...') }}" autocomplete="off">
                </div>
                <div id="map-search-results" class="search-results-dropdown"></div>
            </div>
            <div id="route-builder-map"></div>
        </div>
    </div>
</div>

<!-- Modal 1: Stop Details (Advanced) -->
<div class="modal fade" id="stopModal" tabindex="-1" role="dialog" aria-labelledby="stopModalTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title font-weight-bold" id="stopModalTitle">{{ __('Stop details') }}</h5>
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

<!-- Modal 2: Assign Vehicle to Route -->
<div class="modal fade" id="vehicleAssignModal" tabindex="-1" role="dialog" aria-labelledby="vehicleAssignModalTitle">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title font-weight-bold" id="vehicleAssignModalTitle"><i class="fa fa-bus text-primary mr-1"></i>{{ __('Assign Vehicle to Route') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 text-xs">
                    <i class="fa fa-info-circle mr-1"></i>{{ __('Assigning a vehicle automatically aligns the route trip times with the earliest and latest stops.') }}
                </div>
                <div class="form-group">
                    <label for="va-vehicle" class="font-weight-bold text-xs">{{ __('Vehicle') }} <span class="text-danger">*</span></label>
                    <select id="va-vehicle" class="form-control" required>
                        <option value="">{{ __('-- Select Vehicle --') }}</option>
                        @foreach ($vehicles as $v)
                            <option value="{{ $v->id }}">{{ $v->name }} {{ $v->vehicle_number ? '('.$v->vehicle_number.')' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label for="va-driver" class="font-weight-bold text-xs">{{ __('Driver') }}</label>
                        <select id="va-driver" class="form-control">
                            <option value="">{{ __('-- Select Driver --') }}</option>
                            @foreach ($drivers as $d)
                                <option value="{{ $d->id }}">{{ $d->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label for="va-helper" class="font-weight-bold text-xs">{{ __('Helper') }}</label>
                        <select id="va-helper" class="form-control">
                            <option value="">{{ __('-- Select Helper --') }}</option>
                            @foreach ($helpers as $h)
                                <option value="{{ $h->id }}">{{ $h->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6 mb-0">
                        <label for="va-pickup-start" class="text-xs text-muted mb-0">{{ __('Morning Start Time') }}</label>
                        <input type="time" id="va-pickup-start" class="form-control form-control-sm">
                        <small class="text-muted" style="font-size:10px;">{{ __('Leave blank to auto-calculate') }}</small>
                    </div>
                    <div class="form-group col-6 mb-0">
                        <label for="va-drop-end" class="text-xs text-muted mb-0">{{ __('Afternoon End Time') }}</label>
                        <input type="time" id="va-drop-end" class="form-control form-control-sm">
                        <small class="text-muted" style="font-size:10px;">{{ __('Leave blank to auto-calculate') }}</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-vehicle-assign"><i class="fa fa-check mr-1"></i>{{ __('Save Assignment') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3: Auto-Schedule Times -->
<div class="modal fade" id="autoScheduleModal" tabindex="-1" role="dialog" aria-labelledby="autoScheduleModalTitle">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title font-weight-bold text-sm" id="autoScheduleModalTitle"><i class="fa fa-clock-o text-info mr-1"></i>{{ __('⚡ Auto-Schedule Times') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="modal-body py-2">
                <p class="text-xs text-muted mb-2">{{ __('Automatically calculate sequential times for all stops with a single click.') }}</p>
                <div class="form-group mb-2">
                    <label for="as-morning-start" class="text-xs font-weight-bold mb-1">{{ __('Morning Route Start') }}</label>
                    <input type="time" id="as-morning-start" class="form-control form-control-sm" value="07:15">
                </div>
                <div class="form-group mb-2">
                    <label for="as-morning-interval" class="text-xs font-weight-bold mb-1">{{ __('Interval Between Stops (mins)') }}</label>
                    <input type="number" id="as-morning-interval" class="form-control form-control-sm" value="5" min="1" max="60">
                </div>
                <hr class="my-2">
                <div class="form-group mb-2">
                    <label for="as-drop-start" class="text-xs font-weight-bold mb-1">{{ __('Afternoon Drop Start') }}</label>
                    <input type="time" id="as-drop-start" class="form-control form-control-sm" value="15:30">
                </div>
                <div class="form-group mb-0">
                    <label for="as-drop-interval" class="text-xs font-weight-bold mb-1">{{ __('Interval Between Drops (mins)') }}</label>
                    <input type="number" id="as-drop-interval" class="form-control form-control-sm" value="5" min="1" max="60">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-info btn-sm text-white" id="btn-apply-auto-schedule"><i class="fa fa-magic mr-1"></i>{{ __('Apply to All Stops') }}</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 4: Batch Add Existing Stops -->
<div class="modal fade" id="batchAddModal" tabindex="-1" role="dialog" aria-labelledby="batchAddModalTitle">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title font-weight-bold text-sm" id="batchAddModalTitle"><i class="fa fa-list-ul text-primary mr-1"></i>{{ __('Batch Add Existing Pickup Points') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Close') }}">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div class="form-group mb-2">
                    <input type="text" id="batch-filter-input" class="form-control form-control-sm" placeholder="{{ __('Type to filter pickup points...') }}">
                </div>
                <div id="batch-stops-list" style="max-height: 280px; overflow-y: auto;" class="border rounded p-2">
                    <!-- Populated via JS -->
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted"><span id="batch-selected-count">0</span> {{ __('selected') }}</small>
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-batch-select-all">{{ __('Select All') }}</button>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-apply-batch-add"><i class="fa fa-plus mr-1"></i>{{ __('Add Selected Stops') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var dataUrl = @json(route('gps.routes.data', ['id' => '__ID__']));
        var saveUrl = @json(route('gps.routes.save'));
        var assignVehicleUrl = @json(url('gps/routes/__ID__/assign-vehicle'));
        var unassignVehicleUrl = @json(url('gps/routes/__ID__/vehicles/__ASSIGN_ID__'));
        var routingUrl = @json(rtrim((string) config('gpstransport.map.routing_url'), '/'));
        var allPickupPoints = @json($pickupPoints);

        var map, routeId = null, mode = 'stop';
        var stops = {};          // key -> stop
        var morning = [], afternoon = [];
        var markers = {}, circles = {};
        var waypoints = [], line = null, editingKey = null, seq = 0;
        var searchTimeout = null;

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
            });
        }
        function newKey() { return 'k' + (++seq); }

        function timeToMinutes(t) {
            if (!t) return 0;
            var parts = t.split(':');
            return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
        }
        function minutesToTime(m) {
            m = m % 1440;
            var hrs = Math.floor(m / 60);
            var mins = m % 60;
            return ('0' + hrs).slice(-2) + ':' + ('0' + mins).slice(-2);
        }

        document.addEventListener('DOMContentLoaded', function () {
            map = L.map('route-builder-map').setView([{{ (float) config('gpstransport.map.default_center_lat', 11.0168) }}, {{ (float) config('gpstransport.map.default_center_lng', 76.9558) }}], 13);
            L.tileLayer(@json(config('gpstransport.map.tile_url')), {attribution: @json(config('gpstransport.map.attribution')), maxZoom: 19}).addTo(map);

            // Frictionless Map Click Workflow (Zero browser prompt dialogs)
            map.on('click', function (e) {
                if (!routeId) { return; }
                if (mode === 'path') {
                    waypoints.push([e.latlng.lat, e.latlng.lng]);
                    drawLine();
                    return;
                }

                var lat = +e.latlng.lat.toFixed(7);
                var lng = +e.latlng.lng.toFixed(7);
                var defName = @json(__('Stop')) + ' ' + (morning.length + 1);

                // Auto-calculate sequential time (+5 mins from previous stop)
                var pTime = '07:30', dTime = '15:30';
                if (morning.length > 0) {
                    var prevKey = morning[morning.length - 1];
                    var prevStop = stops[prevKey];
                    if (prevStop && prevStop.pickup_time) {
                        pTime = minutesToTime(timeToMinutes(prevStop.pickup_time) + 5);
                    }
                    if (prevStop && prevStop.drop_time) {
                        dTime = minutesToTime(timeToMinutes(prevStop.drop_time) + 5);
                    }
                }

                var key = addStop({
                    name: defName,
                    latitude: lat,
                    longitude: lng,
                    pickup_time: pTime,
                    drop_time: dTime,
                    silent: true
                });

                // Open quick-edit leaflet popup directly on the marker
                openMarkerQuickPopup(key);

                // Reverse geocode in background to provide intelligent place name
                reverseGeocodeStop(key, lat, lng);
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

            // Vehicle Assignment Events
            $('#btn-manage-vehicle').on('click', openVehicleModal);
            $('#btn-save-vehicle-assign').on('click', saveVehicleAssign);
            $(document).on('click', '.btn-unassign-vehicle', unassignVehicle);

            // Auto-Schedule Events
            $('#btn-open-auto-schedule').on('click', function () { $('#autoScheduleModal').modal('show'); });
            $('#btn-apply-auto-schedule').on('click', applyAutoSchedule);

            // Batch Add Events
            $('#btn-open-batch-add').on('click', openBatchAddModal);
            $('#batch-filter-input').on('input', filterBatchStops);
            $('#btn-batch-select-all').on('click', toggleBatchSelectAll);
            $('#btn-apply-batch-add').on('click', applyBatchAdd);

            // Map Location Search Events
            initMapSearch();
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

                renderAssignedVehicles(res.assigned_vehicles || []);

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

        function renderAssignedVehicles(list) {
            var $c = $('#route-vehicle-info');
            if (!list || !list.length) {
                $c.html('<div class="d-flex align-items-center text-muted"><i class="fa fa-exclamation-circle text-warning mr-1"></i>{{ __('No vehicle assigned to this route.') }}</div>');
                return;
            }
            var html = list.map(function(v) {
                return '<div class="d-flex justify-content-between align-items-center py-1 border-bottom">' +
                    '<div>' +
                        '<div class="font-weight-bold text-dark"><i class="fa fa-bus text-success mr-1"></i>' + esc(v.vehicle_name) + ' <span class="badge badge-light border">(' + esc(v.vehicle_number) + ')</span></div>' +
                        '<div class="text-muted"><i class="fa fa-user mr-1"></i>' + esc(v.driver_name) + (v.driver_phone && v.driver_phone !== '-' ? ' · ' + esc(v.driver_phone) : '') + '</div>' +
                    '</div>' +
                    '<div>' +
                        '<button type="button" class="btn btn-xs btn-outline-danger btn-unassign-vehicle" data-id="' + v.id + '" title="{{ __('Remove Assignment') }}"><i class="fa fa-times"></i></button>' +
                    '</div>' +
                '</div>';
            }).join('');
            $c.html(html);
        }

        function openVehicleModal() {
            if (!routeId) return;
            $('#va-vehicle').val('');
            $('#va-driver').val('');
            $('#va-helper').val('');
            $('#va-pickup-start').val('');
            $('#va-drop-end').val('');
            $('#vehicleAssignModal').modal('show');
        }

        function saveVehicleAssign() {
            var vId = $('#va-vehicle').val();
            if (!vId) { alert(@json(__('Please select a vehicle.'))); return; }
            var payload = {
                vehicle_id: vId,
                driver_id: $('#va-driver').val() || null,
                helper_id: $('#va-helper').val() || null,
                pickup_trip_start_time: $('#va-pickup-start').val() || null,
                drop_trip_end_time: $('#va-drop-end').val() || null,
            };
            var url = assignVehicleUrl.replace('__ID__', routeId);
            var btn = $('#btn-save-vehicle-assign').prop('disabled', true);

            $.ajax({
                url: url, method: 'POST', contentType: 'application/json', data: JSON.stringify(payload),
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
            }).done(function (res) {
                $('#vehicleAssignModal').modal('hide');
                if (typeof showSuccessToast === 'function') { showSuccessToast(res.message); } else { alert(res.message); }
                load(routeId);
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || @json(__('Could not assign vehicle.'));
                if (typeof showErrorToast === 'function') { showErrorToast(msg); } else { alert(msg); }
            }).always(function () { btn.prop('disabled', false); });
        }

        function unassignVehicle() {
            var assignId = $(this).data('id');
            if (!confirm(@json(__('Remove this vehicle assignment from the route?')))) return;
            var url = unassignVehicleUrl.replace('__ID__', routeId).replace('__ASSIGN_ID__', assignId);
            $.ajax({
                url: url, method: 'DELETE',
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
            }).done(function (res) {
                if (typeof showSuccessToast === 'function') { showSuccessToast(res.message); } else { alert(res.message); }
                load(routeId);
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || @json(__('Could not remove vehicle assignment.'));
                alert(msg);
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
            if (!data.silent) {
                openModal(key);
            }
            return key;
        }

        function openMarkerQuickPopup(key) {
            var s = stops[key];
            var m = markers[key];
            if (!m) return;

            var content = document.createElement('div');
            content.className = 'stop-quick-popup-content';
            content.innerHTML =
                '<div class="d-flex justify-content-between align-items-center mb-1">' +
                    '<span class="font-weight-bold text-xs text-primary"><i class="fa fa-map-marker mr-1"></i>' + @json(__('Quick Stop Edit')) + '</span>' +
                '</div>' +
                '<input type="text" class="form-control form-control-sm mb-1 q-name" value="' + esc(s.name) + '" placeholder="' + @json(__('Stop Name')) + '">' +
                '<div class="form-row mb-2">' +
                    '<div class="col-6"><label class="text-xs text-muted mb-0">' + @json(__('Morning')) + '</label><input type="time" class="form-control form-control-sm q-pickup" value="' + esc(s.pickup_time || '') + '"></div>' +
                    '<div class="col-6"><label class="text-xs text-muted mb-0">' + @json(__('Afternoon')) + '</label><input type="time" class="form-control form-control-sm q-drop" value="' + esc(s.drop_time || '') + '"></div>' +
                '</div>' +
                '<div class="d-flex justify-content-between align-items-center">' +
                    '<button type="button" class="btn btn-xs btn-outline-danger q-del" title="' + @json(__('Delete Stop')) + '"><i class="fa fa-trash"></i></button>' +
                    '<div>' +
                        '<button type="button" class="btn btn-xs btn-outline-secondary mr-1 q-adv" title="' + @json(__('Advanced Details')) + '"><i class="fa fa-sliders"></i> ' + @json(__('Details')) + '</button>' +
                        '<button type="button" class="btn btn-xs btn-primary q-done"><i class="fa fa-check mr-1"></i>' + @json(__('Done')) + '</button>' +
                    '</div>' +
                '</div>';

            var popup = L.popup({offset: [0, -14], closeButton: true, className: 'stop-quick-popup'})
                .setLatLng([s.latitude, s.longitude])
                .setContent(content)
                .openOn(map);

            var $c = $(content);
            $c.find('.q-name').on('input change', function () {
                s.name = $(this).val().trim() || s.name;
                refreshMarker(key);
                render();
            });
            $c.find('.q-pickup').on('change', function () {
                s.pickup_time = $(this).val() || null;
                render();
            });
            $c.find('.q-drop').on('change', function () {
                s.drop_time = $(this).val() || null;
                render();
            });
            $c.find('.q-done').on('click', function () {
                map.closePopup();
            });
            $c.find('.q-del').on('click', function () {
                map.closePopup();
                map.removeLayer(markers[key]); map.removeLayer(circles[key]);
                delete markers[key]; delete circles[key]; delete stops[key];
                morning = morning.filter(function (k) { return k !== key; });
                afternoon = afternoon.filter(function (k) { return k !== key; });
                render();
            });
            $c.find('.q-adv').on('click', function () {
                map.closePopup();
                openModal(key);
            });

            setTimeout(function() {
                $c.find('.q-name').focus().select();
            }, 100);
        }

        function reverseGeocodeStop(key, lat, lng) {
            var url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&zoom=18&addressdetails=1';
            $.getJSON(url).done(function(data) {
                if (!stops[key]) return;
                var addr = data.address || {};
                var street = addr.road || addr.suburb || addr.neighbourhood || addr.amenity || addr.shop || data.name;
                if (street && (stops[key].name.indexOf(@json(__('Stop'))) === 0 || stops[key].name.indexOf('Stop ') === 0)) {
                    stops[key].name = street.substring(0, 191);
                    stops[key].landmark = (addr.city || addr.town || addr.county || '').substring(0, 255);
                    refreshMarker(key);
                    render();
                    $('.q-name').val(stops[key].name);
                }
            }).fail(function() {});
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
            var key = addStop({pickup_point_id: id, name: opt.text().trim(), latitude: lat, longitude: lng, silent: true});
            openMarkerQuickPopup(key);
        }

        function openBatchAddModal() {
            if (!routeId) return;
            var assignedIds = Object.keys(stops).map(function(k) { return stops[k].pickup_point_id; }).filter(Boolean);
            var $list = $('#batch-stops-list').empty();

            var available = allPickupPoints.filter(function(p) { return assignedIds.indexOf(p.id) === -1; });
            if (!available.length) {
                $list.html('<div class="text-center text-muted p-3 text-xs">{{ __('All existing pickup points are already added to this route!') }}</div>');
                $('#btn-apply-batch-add').prop('disabled', true);
                $('#batchAddModal').modal('show');
                return;
            }

            $('#btn-apply-batch-add').prop('disabled', false);
            available.forEach(function(p) {
                var itemHtml = '<div class="form-check py-1 border-bottom batch-item" data-name="' + esc(p.name.toLowerCase()) + '">' +
                    '<input class="form-check-input batch-chk" type="checkbox" value="' + p.id + '" id="bchk-' + p.id + '" data-lat="' + (p.latitude || '') + '" data-lng="' + (p.longitude || '') + '" data-name="' + esc(p.name) + '">' +
                    '<label class="form-check-label text-xs font-weight-bold" for="bchk-' + p.id + '">' + esc(p.name) + '</label>' +
                    (p.latitude ? '<small class="text-muted ml-2">(' + p.latitude + ', ' + p.longitude + ')</small>' : '<small class="text-warning ml-2">{{ __("No GPS coordinates") }}</small>') +
                '</div>';
                $list.append(itemHtml);
            });

            updateBatchCount();
            $('#batchAddModal').modal('show');
        }

        function filterBatchStops() {
            var q = $(this).val().toLowerCase().trim();
            $('.batch-item').each(function() {
                var name = $(this).data('name');
                $(this).toggle(!q || name.indexOf(q) !== -1);
            });
        }

        function toggleBatchSelectAll() {
            var visibleChks = $('.batch-item:visible .batch-chk');
            var allChecked = visibleChks.filter(':checked').length === visibleChks.length;
            visibleChks.prop('checked', !allChecked);
            updateBatchCount();
        }

        $(document).on('change', '.batch-chk', updateBatchCount);

        function updateBatchCount() {
            var count = $('.batch-chk:checked').length;
            $('#batch-selected-count').text(count);
        }

        function applyBatchAdd() {
            var selected = $('.batch-chk:checked');
            if (!selected.length) { alert(@json(__('Please select at least one pickup point.'))); return; }

            selected.each(function() {
                var id = parseInt($(this).val(), 10);
                var name = $(this).data('name');
                var lat = parseFloat($(this).data('lat')), lng = parseFloat($(this).data('lng'));
                if (isNaN(lat) || isNaN(lng)) {
                    var c = map.getCenter();
                    lat = c.lat; lng = c.lng;
                }
                addStop({pickup_point_id: id, name: name, latitude: lat, longitude: lng, silent: true});
            });

            $('#batchAddModal').modal('hide');
            fit();
        }

        function applyAutoSchedule() {
            var mStart = $('#as-morning-start').val() || '07:15';
            var mInterval = parseInt($('#as-morning-interval').val(), 10) || 5;
            var dStart = $('#as-drop-start').val() || '15:30';
            var dInterval = parseInt($('#as-drop-interval').val(), 10) || 5;

            var mBase = timeToMinutes(mStart);
            morning.forEach(function (key, idx) {
                stops[key].pickup_time = minutesToTime(mBase + (idx * mInterval));
            });

            var dBase = timeToMinutes(dStart);
            afternoon.forEach(function (key, idx) {
                stops[key].drop_time = minutesToTime(dBase + (idx * dInterval));
            });

            $('#autoScheduleModal').modal('hide');
            render();
            if (typeof showSuccessToast === 'function') {
                showSuccessToast(@json(__('Timings calculated and applied to all stops!')));
            }
        }

        function initMapSearch() {
            $('#map-search-input').on('input', function() {
                clearTimeout(searchTimeout);
                var q = $(this).val().trim();
                if (q.length < 3) {
                    $('#map-search-results').hide().empty();
                    return;
                }
                searchTimeout = setTimeout(function() {
                    var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&limit=5';
                    $.getJSON(url).done(function(results) {
                        var $res = $('#map-search-results').empty();
                        if (!results || !results.length) {
                            $res.html('<div class="p-2 text-xs text-muted">{{ __("No places found") }}</div>').show();
                            return;
                        }
                        results.forEach(function(item) {
                            var title = item.display_name.split(',')[0];
                            var $el = $('<div class="search-result-item">')
                                .html('<strong><i class="fa fa-map-pin text-primary mr-1"></i>' + esc(title) + '</strong><br><small class="text-muted text-truncate d-block">' + esc(item.display_name) + '</small>')
                                .data('lat', parseFloat(item.lat))
                                .data('lng', parseFloat(item.lon))
                                .data('name', title);
                            $res.append($el);
                        });
                        $res.show();
                    });
                }, 350);
            });

            $(document).on('click', '.search-result-item', function() {
                var lat = $(this).data('lat');
                var lng = $(this).data('lng');
                var name = $(this).data('name');
                $('#map-search-results').hide().empty();
                $('#map-search-input').val('');

                if (!routeId) {
                    alert(@json(__('Please select a route first.')));
                    return;
                }

                map.flyTo([lat, lng], 15, {duration: 0.8});

                var pTime = '07:30', dTime = '15:30';
                if (morning.length > 0) {
                    var prev = stops[morning[morning.length - 1]];
                    if (prev && prev.pickup_time) pTime = minutesToTime(timeToMinutes(prev.pickup_time) + 5);
                    if (prev && prev.drop_time) dTime = minutesToTime(timeToMinutes(prev.drop_time) + 5);
                }

                var key = addStop({
                    name: name,
                    latitude: lat,
                    longitude: lng,
                    pickup_time: pTime,
                    drop_time: dTime,
                    silent: true
                });
                openMarkerQuickPopup(key);
            });
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
            m.on('click', function () { openMarkerQuickPopup(key); });
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
