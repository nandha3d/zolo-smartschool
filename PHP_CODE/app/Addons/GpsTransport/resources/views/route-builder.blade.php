@extends('layouts.master')

@section('title')
    {{ __('Routes & Geofences Visual Builder') }}
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('assets/vendor/leaflet/leaflet.css') }}" />
<script src="{{ asset('assets/vendor/leaflet/leaflet.js') }}"></script>

<style>
    .builder-container {
        display: flex;
        height: calc(100vh - 160px);
        min-height: 600px;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .builder-sidebar {
        width: 380px;
        background: #fafafa;
        border-right: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }
    .builder-map {
        flex: 1;
        position: relative;
    }
    #route-builder-map {
        width: 100%;
        height: 100%;
    }
    .stop-item {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 8px;
        cursor: grab;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .stop-item:hover {
        border-color: #3b82f6;
    }
    .stop-seq-badge {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: #2563eb;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 12px;
        margin-right: 10px;
    }
</style>

<div class="content-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
                <i class="fa fa-draw-polygon text-primary mr-2"></i> {{ __('Route & Geofence Builder') }}
            </h4>
            <small class="text-muted">{{ __('Design routes visually on the map, adjust stop geofence radiuses, and generate route polylines for deviation monitoring.') }}</small>
        </div>
        <div>
            <button id="btn-save-geometry" class="btn btn-primary btn-sm" onclick="saveRouteGeometry()">
                <i class="fa fa-save mr-1"></i> {{ __('Save Route Geometry') }}
            </button>
            <a href="{{ route('routes.index') }}" class="btn btn-outline-secondary btn-sm ml-2">
                {{ __('Manage Standard Routes') }}
            </a>
        </div>
    </div>

    <div class="builder-container">
        {{-- Sidebar --}}
        <div class="builder-sidebar p-3">
            <div class="form-group mb-3">
                <label class="font-weight-bold text-xs text-uppercase text-muted">{{ __('Select Route') }}</label>
                <select id="route-select" class="form-control" onchange="loadRouteData(this.value)">
                    <option value="">{{ __('-- Select Route to Edit --') }}</option>
                    @foreach ($routes as $route)
                        <option value="{{ $route->id }}">{{ $route->name }} ({{ $route->routePickupPoints->count() }} stops)</option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="font-weight-bold text-sm text-dark">{{ __('Route Stops & Geofences') }}</span>
                <span class="text-xs text-muted" id="stops-count">0 stops</span>
            </div>

            <div id="stops-list" style="flex: 1; overflow-y: auto;">
                <div class="text-center text-muted p-4 text-sm">
                    {{ __('Select a route above to load its stops and geofence circles on the map.') }}
                </div>
            </div>

            <div class="p-2 bg-light rounded mt-2 border text-xs text-muted">
                <i class="fa fa-info-circle text-primary mr-1"></i>
                {{ __('Geofence circles (100m) automatically trigger arrival/departure events and parent notifications.') }}
            </div>
        </div>

        {{-- Map --}}
        <div class="builder-map">
            <div id="route-builder-map"></div>
        </div>
    </div>
</div>

<script>
    var map;
    var currentRouteId = null;
    var stopMarkers = [];
    var geofenceCircles = [];
    var routePolyline = null;
    var waypoints = [];

    document.addEventListener('DOMContentLoaded', function () {
        var defaultLat = {{ config('gpstransport.map.default_center_lat', 11.0168) }};
        var defaultLng = {{ config('gpstransport.map.default_center_lng', 76.9558) }};

        map = L.map('route-builder-map').setView([defaultLat, defaultLng], 13);

        L.tileLayer(@json(config('gpstransport.map.tile_url')), {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // Click map to add waypoint
        map.on('click', function(e) {
            if (!currentRouteId) return;
            waypoints.push([e.latlng.lat, e.latlng.lng]);
            redrawPolyline();
        });
    });

    function loadRouteData(routeId) {
        if (!routeId) return;
        currentRouteId = routeId;

        // Clear existing markers & lines
        stopMarkers.forEach(function(m) { map.removeLayer(m); });
        geofenceCircles.forEach(function(c) { map.removeLayer(c); });
        if (routePolyline) map.removeLayer(routePolyline);
        stopMarkers = [];
        geofenceCircles = [];
        waypoints = [];

        fetch('/gps/routes/' + routeId + '/data')
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.error) return;

                var stops = res.stops;
                var listContainer = document.getElementById('stops-list');
                listContainer.innerHTML = '';
                document.getElementById('stops-count').innerText = stops.length + ' stops';

                var bounds = [];

                stops.forEach(function(s, index) {
                    // Create list item
                    var item = document.createElement('div');
                    item.className = 'stop-item';
                    item.innerHTML = 
                        '<div class="d-flex align-items-center">' +
                            '<div class="stop-seq-badge">' + (index + 1) + '</div>' +
                            '<div>' +
                                '<strong class="text-sm text-dark">' + s.name + '</strong>' +
                                '<div class="text-xs text-muted">Geofence: ' + s.geofence_radius + 'm' + (s.pickup_time ? ' • ' + s.pickup_time : '') + '</div>' +
                            '</div>' +
                        '</div>';
                    listContainer.appendChild(item);

                    if (s.latitude && s.longitude) {
                        var pos = [s.latitude, s.longitude];
                        bounds.push(pos);
                        waypoints.push(pos);

                        // Stop marker with sequence number
                        var icon = L.divIcon({
                            className: 'stop-map-icon',
                            html: '<div style="background:#2563eb; width:28px; height:28px; border-radius:50%; border:2px solid #fff; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:bold; font-size:12px; box-shadow:0 2px 6px rgba(0,0,0,0.3);">' + (index + 1) + '</div>',
                            iconSize: [28, 28],
                            iconAnchor: [14, 14]
                        });

                        var marker = L.marker(pos, { icon: icon }).bindPopup('<b>' + s.name + '</b><br>Stop #' + (index + 1) + '<br>Radius: ' + s.geofence_radius + 'm').addTo(map);
                        stopMarkers.push(marker);

                        // Geofence Circle
                        var circle = L.circle(pos, {
                            radius: s.geofence_radius,
                            color: '#3b82f6',
                            fillColor: '#93c5fd',
                            fillOpacity: 0.25,
                            weight: 1.5
                        }).addTo(map);
                        geofenceCircles.push(circle);
                    }
                });

                // If saved geometry exists, use it
                if (res.geometry && res.geometry.length > 0) {
                    waypoints = res.geometry.map(function(p) {
                        return [p.lat || p[0], p.lng || p[1]];
                    });
                }

                redrawPolyline();

                if (bounds.length > 0) {
                    map.fitBounds(bounds, { padding: [60, 60] });
                }
            });
    }

    function redrawPolyline() {
        if (routePolyline) map.removeLayer(routePolyline);
        if (waypoints.length > 1) {
            routePolyline = L.polyline(waypoints, {
                color: '#2563eb',
                weight: 5,
                opacity: 0.85,
                dashArray: null
            }).addTo(map);
        }
    }

    function saveRouteGeometry() {
        if (!currentRouteId) {
            alert('Please select a route first.');
            return;
        }

        var formattedWaypoints = waypoints.map(function(p) {
            return { lat: p[0], lng: p[1] };
        });

        // Calculate approximate distance
        var totalDistKm = 0;
        for (var i = 1; i < waypoints.length; i++) {
            totalDistKm += map.distance(waypoints[i-1], waypoints[i]) / 1000;
        }

        fetch('{{ route("gps.routes.save-geometry") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                route_id: currentRouteId,
                waypoints: formattedWaypoints,
                total_distance: totalDistKm.toFixed(2)
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.error) {
                alert('Error: ' + res.message);
            } else {
                alert('Route geometry saved successfully! Distance: ' + totalDistKm.toFixed(2) + ' km');
            }
        });
    }
</script>
@endsection
