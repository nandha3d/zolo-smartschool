@extends('layouts.master')

@section('title')
    {{ __('Live Fleet Tracking Portal') }}
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/vendor/leaflet/leaflet.css') }}" />
<script src="{{ asset('assets/vendor/leaflet/leaflet.js') }}"></script>

<style>
    .tracking-container {
        display: flex;
        height: calc(100vh - 140px);
        min-height: 600px;
        background: #f8fafc;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    }
    .fleet-sidebar {
        width: 360px;
        background: #ffffff;
        border-right: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        z-index: 10;
    }
    .fleet-sidebar-header {
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
        background: #fafafa;
    }
    .fleet-list {
        flex: 1;
        overflow-y: auto;
        padding: 10px;
    }
    .vehicle-card {
        padding: 12px 14px;
        border-radius: 10px;
        border: 1px solid #f1f5f9;
        margin-bottom: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #fff;
    }
    .vehicle-card:hover, .vehicle-card.active {
        border-color: #3b82f6;
        background: #eff6ff;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(59, 130, 246, 0.15);
    }
    .map-pane {
        flex: 1;
        position: relative;
    }
    #live-fleet-map {
        width: 100%;
        height: 100%;
        z-index: 1;
    }
    .telemetry-overlay {
        position: absolute;
        bottom: 24px;
        left: 24px;
        right: 24px;
        background: rgba(15, 23, 42, 0.92);
        backdrop-filter: blur(8px);
        color: #fff;
        border-radius: 14px;
        padding: 18px 24px;
        z-index: 1000;
        display: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .telemetry-pill {
        background: rgba(255,255,255,0.12);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        margin-right: 12px;
        display: inline-flex;
        align-items: center;
    }
    .badge-moving { background-color: #10b981; color: #fff; }
    .badge-stopped { background-color: #3b82f6; color: #fff; }
    .badge-offline { background-color: #64748b; color: #fff; }
    .badge-emergency { background-color: #ef4444; color: #fff; }
    .badge-overspeed { background-color: #dc2626; color: #fff; animation: gps-blink 1s steps(2, start) infinite; }
    @keyframes gps-blink { to { visibility: hidden; } }
</style>

<div class="content-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
                <i class="fa fa-satellite text-primary mr-2"></i> {{ __('Live Fleet Map') }}
            </h4>
            <small class="text-muted">{{ __('Real-time mobile GPS telemetry, live speed, stop geofences, and delay monitoring.') }}</small>
        </div>
        <div>
            <button id="btn-recenter" class="btn btn-outline-primary btn-sm mr-2">
                <i class="fa fa-crosshairs mr-1"></i> {{ __('Fit All Fleet') }}
            </button>
            <a href="{{ route('gps.dashboard') }}" class="btn btn-light btn-sm">
                <i class="fa fa-th-large mr-1"></i> {{ __('Dashboard') }}
            </a>
        </div>
    </div>

    <div class="tracking-container">
        {{-- Left Fleet Sidebar --}}
        <div class="fleet-sidebar">
            <div class="fleet-sidebar-header">
                <div class="input-group input-group-sm mb-2">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                    </div>
                    <input type="text" id="vehicle-search" class="form-control" placeholder="{{ __('Search bus or driver...') }}">
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <select id="route-filter" class="form-control form-control-sm mr-2">
                        <option value="">{{ __('All Routes') }}</option>
                        @foreach ($routes as $route)
                            <option value="{{ $route->id }}">{{ $route->name }}</option>
                        @endforeach
                    </select>
                    <select id="status-filter" class="form-control form-control-sm">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="MOVING">{{ __('Moving') }}</option>
                        <option value="STOPPED">{{ __('Stopped') }}</option>
                        <option value="OFFLINE">{{ __('Offline') }}</option>
                        <option value="EMERGENCY">{{ __('Emergency') }}</option>
                        <option value="OVERSPEED">{{ __('Overspeeding') }}</option>
                    </select>
                </div>
            </div>

            <div class="fleet-list" id="fleet-list-container">
                <div class="p-4 text-center text-muted">
                    <i class="fa fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                    <p class="mb-0 text-sm">{{ __('Locating vehicles...') }}</p>
                </div>
            </div>
        </div>

        {{-- Map Pane --}}
        <div class="map-pane">
            <div id="live-fleet-map"></div>

            {{-- Telemetry Drawer --}}
            <div class="telemetry-overlay" id="telemetry-drawer">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="mr-3" style="width: 48px; height: 48px; background: #38bdf8; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #0f172a; font-size: 22px;">
                            <i class="fa fa-bus"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 font-weight-bold" id="drawer-bus-title">BUS-24</h5>
                            <span class="text-xs text-muted" id="drawer-route-title">Route R-12 • Morning Pickup</span>
                        </div>
                    </div>
                    <div>
                        <span class="telemetry-pill">
                            <i class="fa fa-tachometer-alt mr-2 text-warning"></i>
                            <span id="drawer-speed">32 km/h</span>
                        </span>
                        <span class="telemetry-pill">
                            <i class="fa fa-map-pin mr-2 text-info"></i>
                            <span>Next: </span> <b id="drawer-next-stop" class="ml-1">Gandhipuram</b>
                        </span>
                        <span class="telemetry-pill">
                            <i class="fa fa-clock mr-2 text-success"></i>
                            <span>ETA: </span> <b id="drawer-eta" class="ml-1">08:12 AM</b>
                        </span>
                        <span class="telemetry-pill">
                            <i class="fa fa-user mr-2 text-primary"></i>
                            <span id="drawer-driver">Driver Ramesh</span>
                        </span>
                        <button class="btn btn-sm btn-link text-white p-0 ml-2" onclick="closeTelemetryDrawer()">
                            <i class="fa fa-times fa-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var map;
    var markers = {};
    var stopMarkers = [];
    var routePolyline = null;
    var fleetData = [];
    var activeVehicleId = {{ $selectedVehicleId ?: 'null' }};

    document.addEventListener('DOMContentLoaded', function () {
        var defaultLat = {{ config('gpstransport.map.default_center_lat', 11.0168) }};
        var defaultLng = {{ config('gpstransport.map.default_center_lng', 76.9558) }};

        map = L.map('live-fleet-map').setView([defaultLat, defaultLng], 13);

        L.tileLayer(@json(config('gpstransport.map.tile_url')), {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        function createBusIcon(status, heading) {
            var color = '#10b981'; // Green
            if (status === 'STOPPED') color = '#3b82f6'; // Blue
            if (status === 'OFFLINE') color = '#64748b'; // Grey
            if (status === 'EMERGENCY' || status === 'BREAKDOWN') color = '#ef4444'; // Red
            if (status === 'OVERSPEED') color = '#dc2626'; // Red, ringed below

            return L.divIcon({
                className: 'custom-bus-marker',
                html: '<div style="background:' + color + '; width:34px; height:34px; border-radius:50%; border:2px solid #ffffff; display:flex; align-items:center; justify-content:center; color:#ffffff; box-shadow:' + (status === 'OVERSPEED' ? '0 0 0 6px rgba(220,38,38,0.45)' : '0 3px 10px rgba(0,0,0,0.35)') + '; transform:rotate(' + (heading || 0) + 'deg);"><i class="fa fa-bus" style="font-size:15px;"></i></div>',
                iconSize: [34, 34],
                iconAnchor: [17, 17]
            });
        }

        function pollFleet() {
            var routeId = document.getElementById('route-filter').value;
            var url = '{{ route("gps.dashboard.live-stats") }}' + (routeId ? '?route_id=' + routeId : '');

            fetch(url)
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (res.error) return;
                    fleetData = res.fleet;
                    renderFleetList();
                    updateMapMarkers();
                });
        }

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
            });
        }

        function renderFleetList() {
            var search = (document.getElementById('vehicle-search').value || '').toLowerCase();
            var statusFilter = document.getElementById('status-filter').value;
            var container = document.getElementById('fleet-list-container');
            container.innerHTML = '';

            var filtered = fleetData.filter(function (v) {
                var matchSearch = v.vehicle_number.toLowerCase().includes(search) || 
                                  v.driver_name.toLowerCase().includes(search) ||
                                  v.route_name.toLowerCase().includes(search);
                var matchStatus = !statusFilter || v.status === statusFilter;
                return matchSearch && matchStatus;
            });

            if (filtered.length === 0) {
                container.innerHTML = '<div class="p-4 text-center text-muted text-sm">No vehicles match filters.</div>';
                return;
            }

            filtered.forEach(function (v) {
                var card = document.createElement('div');
                card.className = 'vehicle-card ' + (activeVehicleId === v.vehicle_id ? 'active' : '');
                card.onclick = function() { selectVehicle(v.vehicle_id); };

                var badgeClass = 'badge-moving';
                if (v.status === 'STOPPED') badgeClass = 'badge-stopped';
                if (v.status === 'OFFLINE') badgeClass = 'badge-offline';
                if (v.status === 'EMERGENCY' || v.status === 'BREAKDOWN') badgeClass = 'badge-emergency';
                if (v.status === 'OVERSPEED') badgeClass = 'badge-overspeed';

                card.innerHTML = 
                    '<div class="d-flex justify-content-between align-items-center mb-1">' +
                        '<strong class="text-dark">' + esc(v.vehicle_number) + '</strong>' +
                        '<span class="badge ' + badgeClass + ' text-xs">' + v.status + '</span>' +
                    '</div>' +
                    '<div class="text-xs text-muted mb-1"><i class="fa fa-route mr-1"></i>' + esc(v.route_name) + '</div>' +
                    '<div class="d-flex justify-content-between align-items-center text-xs text-muted">' +
                        '<span><i class="fa fa-tachometer-alt mr-1"></i>' + v.speed + ' / ' + v.speed_limit + ' km/h</span>' +
                        '<span><i class="fa fa-clock mr-1"></i>' + v.last_seen + '</span>' +
                    '</div>';

                container.appendChild(card);
            });
        }

        function updateMapMarkers() {
            var bounds = [];
            fleetData.forEach(function (v) {
                if (!v.latitude || !v.longitude) return;
                var pos = [v.latitude, v.longitude];
                bounds.push(pos);

                if (markers[v.vehicle_id]) {
                    markers[v.vehicle_id].setLatLng(pos);
                    markers[v.vehicle_id].setIcon(createBusIcon(v.status, v.heading));
                } else {
                    var m = L.marker(pos, { icon: createBusIcon(v.status, v.heading) }).addTo(map);
                    m.on('click', function() { selectVehicle(v.vehicle_id); });
                    markers[v.vehicle_id] = m;
                }
            });

            if (bounds.length > 0 && !map._hasFitted && !activeVehicleId) {
                map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
                map._hasFitted = true;
            }
        }

        window.selectVehicle = function(vehicleId) {
            activeVehicleId = vehicleId;
            var vehicle = fleetData.find(function(v) { return v.vehicle_id === vehicleId; });
            if (!vehicle) return;

            renderFleetList();

            // Center map on vehicle
            if (vehicle.latitude && vehicle.longitude) {
                map.setView([vehicle.latitude, vehicle.longitude], 15, { animate: true });
            }

            // Show telemetry overlay
            document.getElementById('drawer-bus-title').innerText = vehicle.vehicle_number;
            document.getElementById('drawer-route-title').innerText = vehicle.route_name;
            document.getElementById('drawer-speed').innerText = vehicle.speed + ' km/h';
            document.getElementById('drawer-next-stop').innerText = vehicle.next_stop || 'In Transit';
            document.getElementById('drawer-eta').innerText = vehicle.eta || 'N/A';
            document.getElementById('drawer-driver').innerText = vehicle.driver_name || 'Driver';
            document.getElementById('telemetry-drawer').style.display = 'block';
        };

        window.closeTelemetryDrawer = function() {
            document.getElementById('telemetry-drawer').style.display = 'none';
        };

        document.getElementById('vehicle-search').addEventListener('input', renderFleetList);
        document.getElementById('route-filter').addEventListener('change', pollFleet);
        document.getElementById('status-filter').addEventListener('change', renderFleetList);

        document.getElementById('btn-recenter').onclick = function() {
            var bounds = [];
            fleetData.forEach(function(v) {
                if (v.latitude && v.longitude) bounds.push([v.latitude, v.longitude]);
            });
            if (bounds.length > 0) map.fitBounds(bounds, { padding: [60, 60] });
        };

        pollFleet();
        setInterval(pollFleet, 4000);
    });
</script>
@endsection
