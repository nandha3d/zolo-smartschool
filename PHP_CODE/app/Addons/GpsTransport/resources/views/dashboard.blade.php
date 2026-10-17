@extends('layouts.master')

@section('title')
    {{ __('GPS Transport Dashboard') }}
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/vendor/leaflet/leaflet.css') }}" />
<script src="{{ asset('assets/vendor/leaflet/leaflet.js') }}"></script>

<style>
    .gps-stat-card {
        border-radius: 12px;
        padding: 20px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .gps-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    }
    .gps-card-blue { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
    .gps-card-green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .gps-card-orange { background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%); }
    .gps-card-red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
    .gps-card-purple { background: linear-gradient(135deg, #654ea3 0%, #eaafc8 100%); }
    .gps-stat-val { font-size: 32px; font-weight: 700; margin: 4px 0; }
    .gps-stat-label { font-size: 13px; text-transform: uppercase; letter-spacing: 0.8px; opacity: 0.9; }
    .gps-stat-icon { position: absolute; right: 18px; top: 18px; font-size: 42px; opacity: 0.25; }
    
    #dashboard-map {
        height: 440px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        z-index: 1;
    }
    .pulse-dot {
        width: 14px;
        height: 14px;
        background: #10b981;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulse-green 1.8s infinite;
    }
    @keyframes pulse-green {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .bus-marker-icon {
        background: #1e293b;
        color: #fff;
        border-radius: 50%;
        border: 2px solid #38bdf8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        box-shadow: 0 3px 8px rgba(0,0,0,0.3);
        transition: transform 0.3s ease;
    }
</style>

<div class="content-wrapper">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="page-title text-dark font-weight-bold d-flex align-items-center">
                <i class="fa fa-map-marker-alt text-primary mr-2"></i> {{ __('GPS Transport Control Center') }}
            </h3>
            <p class="text-muted mb-0">{{ __('Live real-time monitoring of school transport, vehicle positions, active trips, and safety alerts.') }}</p>
        </div>
        <div class="d-flex align-items-center">
            <span class="mr-3 d-flex align-items-center text-sm font-weight-500">
                <span class="pulse-dot mr-2"></span> <span id="ws-status">{{ __('Live Telemetry Active') }}</span>
            </span>
            <a href="{{ route('gps.live-tracking') }}" class="btn btn-primary btn-sm mr-2">
                <i class="fa fa-expand mr-1"></i> {{ __('Full Screen Map') }}
            </a>
            <a href="{{ route('gps.trips.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-route mr-1"></i> {{ __('Trips') }}
            </a>
        </div>
    </div>

    {{-- Alert Banner if active emergency --}}
    @if ($activeEmergencies > 0)
        <div class="alert alert-danger d-flex align-items-center justify-content-between mb-4 shadow-sm" style="border-radius: 10px;">
            <div class="d-flex align-items-center">
                <i class="fa fa-exclamation-triangle fa-2x mr-3 text-danger"></i>
                <div>
                    <h5 class="mb-0 font-weight-bold">{{ $activeEmergencies }} {{ __('CRITICAL EMERGENCY / BREAKDOWN INCIDENT ACTIVE') }}</h5>
                    <small>{{ __('Driver SOS or breakdown reported. Immediate transport manager action required.') }}</small>
                </div>
            </div>
            <a href="{{ route('gps.emergency.index') }}" class="btn btn-danger font-weight-bold">
                {{ __('Open Emergency Center') }} <i class="fa fa-arrow-right ml-1"></i>
            </a>
        </div>
    @endif

    {{-- KPI Cards --}}
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="gps-stat-card gps-card-blue">
                <div class="gps-stat-label">{{ __('Total Fleet') }}</div>
                <div class="gps-stat-val">{{ $totalVehicles }}</div>
                <div class="text-sm opacity-80">{{ $movingCount }} {{ __('Moving') }} • {{ $stoppedCount }} {{ __('Parked') }}</div>
                <i class="fa fa-bus gps-stat-icon"></i>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="gps-stat-card gps-card-green">
                <div class="gps-stat-label">{{ __('Active Trips Now') }}</div>
                <div class="gps-stat-val" id="kpi-active-trips">{{ $activeTrips }}</div>
                <div class="text-sm opacity-80">{{ $completedToday }} {{ __('Completed Today') }}</div>
                <i class="fa fa-route gps-stat-icon"></i>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="gps-stat-card gps-card-orange">
                <div class="gps-stat-label">{{ __('GPS Offline / Stale') }}</div>
                <div class="gps-stat-val" id="kpi-offline">{{ $offlineCount }}</div>
                <div class="text-sm opacity-80">{{ __('Devices with no recent signal') }}</div>
                <i class="fa fa-signal gps-stat-icon"></i>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6 mb-3">
            <div class="gps-stat-card gps-card-red">
                <div class="gps-stat-label">{{ __('Active Emergencies') }}</div>
                <div class="gps-stat-val" id="kpi-emergencies">{{ $activeEmergencies }}</div>
                <div class="text-sm opacity-80">{{ __('SOS / Breakdown Incidents') }}</div>
                <i class="fa fa-ambulance gps-stat-icon"></i>
            </div>
        </div>
    </div>

    {{-- Map & Live Fleet Grid --}}
    <div class="row mb-4">
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 font-weight-bold text-dark">
                        <i class="fa fa-globe text-primary mr-2"></i> {{ __('Live Fleet Map') }}
                    </h5>
                    <div class="d-flex align-items-center">
                        <span class="badge badge-success mr-2" id="moving-badge">{{ $movingCount }} {{ __('Moving') }}</span>
                        <span class="badge badge-secondary mr-2" id="stopped-badge">{{ $stoppedCount }} {{ __('Stopped') }}</span>
                        <span class="badge badge-warning" id="offline-badge">{{ $offlineCount }} {{ __('Offline') }}</span>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id="dashboard-map"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            {{-- Recent Safety Alerts --}}
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 font-weight-bold text-dark">
                        <i class="fa fa-bell text-warning mr-2"></i> {{ __('Recent Alerts') }}
                    </h5>
                    <a href="{{ route('gps.alerts.index') }}" class="text-sm font-weight-500 text-primary">{{ __('View All') }}</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" id="alerts-container">
                        @forelse ($recentAlerts as $alert)
                            <div class="list-group-item px-3 py-2 border-0 border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge badge-{{ $alert->severity === 'CRITICAL' ? 'danger' : 'warning' }} text-xs">
                                        {{ $alert->alert_type }}
                                    </span>
                                    <small class="text-muted">{{ $alert->created_at->diffForHumans() }}</small>
                                </div>
                                <p class="mb-0 text-sm mt-1 text-dark">{{ $alert->message }}</p>
                                <small class="text-muted font-weight-500">Bus: {{ $alert->vehicle?->vehicle_number ?? 'N/A' }}</small>
                            </div>
                        @empty
                            <div class="p-4 text-center text-muted">
                                <i class="fa fa-check-circle text-success fa-2x mb-2"></i>
                                <p class="mb-0 text-sm">{{ __('No active alerts. Fleet operating normally.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Active Trips List --}}
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 font-weight-bold text-dark">
                        <i class="fa fa-list text-info mr-2"></i> {{ __('In-Transit Trips') }}
                    </h5>
                    <span class="badge badge-primary">{{ count($activeTripsList) }}</span>
                </div>
                <div class="card-body p-0" style="max-height: 220px; overflow-y: auto;">
                    @forelse ($activeTripsList as $trip)
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-dark">{{ $trip->vehicle?->vehicle_number }}</strong>
                                <span class="badge badge-info ml-1">{{ $trip->trip_type }}</span>
                                <div class="text-xs text-muted">{{ $trip->route?->name }} • Driver: {{ $trip->driver?->name }}</div>
                            </div>
                            <a href="{{ route('gps.live-tracking', ['vehicle_id' => $trip->vehicle_id]) }}" class="btn btn-xs btn-outline-primary">
                                <i class="fa fa-crosshairs"></i>
                            </a>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted">
                            <small>{{ __('No trips in transit right now.') }}</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Initialize Leaflet Map
        var defaultLat = {{ config('gpstransport.map.default_center_lat', 11.0168) }};
        var defaultLng = {{ config('gpstransport.map.default_center_lng', 76.9558) }};
        var map = L.map('dashboard-map').setView([defaultLat, defaultLng], 12);

        L.tileLayer(@json(config('gpstransport.map.tile_url')), {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        var markers = {};

        function createBusIcon(status, heading) {
            var color = '#10b981'; // Green moving
            if (status === 'STOPPED') color = '#3b82f6'; // Blue parked
            if (status === 'OFFLINE') color = '#94a3b8'; // Grey offline
            if (status === 'EMERGENCY' || status === 'BREAKDOWN') color = '#ef4444'; // Red

            return L.divIcon({
                className: 'custom-bus-icon',
                html: '<div style="background:' + color + '; width:32px; height:32px; border-radius:50%; border:2px solid #ffffff; display:flex; align-items:center; justify-content:center; color:#ffffff; box-shadow:0 3px 8px rgba(0,0,0,0.35); transform:rotate(' + (heading || 0) + 'deg);"><i class="fa fa-bus" style="font-size:14px;"></i></div>',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });
        }

        // 2. Load Fleet Telemetry
        function fetchFleet() {
            fetch('{{ route("gps.dashboard.live-stats") }}')
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (res.error) return;

                    var fleet = res.fleet;
                    var bounds = [];

                    fleet.forEach(function (v) {
                        if (!v.latitude || !v.longitude) return;

                        var pos = [v.latitude, v.longitude];
                        bounds.push(pos);

                        var popupHtml = '<div style="min-width:180px;">' +
                            '<h6 style="margin:0 0 5px 0; font-weight:700;">' + v.vehicle_number + '</h6>' +
                            '<div style="font-size:12px; color:#475569; margin-bottom:4px;"><b>Route:</b> ' + v.route_name + '</div>' +
                            '<div style="font-size:12px; color:#475569; margin-bottom:4px;"><b>Driver:</b> ' + v.driver_name + '</div>' +
                            '<div style="font-size:12px; color:#475569; margin-bottom:4px;"><b>Speed:</b> ' + v.speed + ' km/h</div>' +
                            '<div style="font-size:12px; color:#475569; margin-bottom:4px;"><b>ETA:</b> ' + v.eta + '</div>' +
                            '<div style="font-size:11px; color:#94a3b8;">Updated: ' + v.last_seen + '</div>' +
                            '</div>';

                        if (markers[v.vehicle_id]) {
                            markers[v.vehicle_id].setLatLng(pos);
                            markers[v.vehicle_id].setIcon(createBusIcon(v.status, v.heading));
                            markers[v.vehicle_id].setPopupContent(popupHtml);
                        } else {
                            var marker = L.marker(pos, { icon: createBusIcon(v.status, v.heading) })
                                .bindPopup(popupHtml)
                                .addTo(map);
                            markers[v.vehicle_id] = marker;
                        }
                    });

                    // Update summary tiles
                    if (res.summary) {
                        document.getElementById('kpi-active-trips').innerText = res.summary.active_trips;
                        document.getElementById('kpi-offline').innerText = res.summary.offline;
                        document.getElementById('kpi-emergencies').innerText = res.summary.active_emergencies;
                        document.getElementById('moving-badge').innerText = res.summary.moving + ' Moving';
                        document.getElementById('stopped-badge').innerText = res.summary.stopped + ' Stopped';
                        document.getElementById('offline-badge').innerText = res.summary.offline + ' Offline';
                    }

                    if (bounds.length > 0 && !map._hasFitted) {
                        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
                        map._hasFitted = true;
                    }
                })
                .catch(function (err) {
                    console.warn("Fleet poll error:", err);
                });
        }

        fetchFleet();
        // Live polling every 5 seconds for dashboard map refresh
        setInterval(fetchFleet, 5000);
    });
</script>
@endsection
