@extends('layouts.master')

@section('title')
    {{ __('Trip History & Replay') }}
@endsection

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .replay-player {
        background: #0f172a;
        color: #fff;
        border-radius: 12px;
        padding: 16px 20px;
        margin-top: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    #replay-map {
        height: 520px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    }
</style>

<div class="content-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
                <i class="fa fa-history text-primary mr-2"></i> {{ __('Trip History & GPS Replay') }}
            </h4>
            <small class="text-muted">{{ __('Inspect historical trips, replay routes with playback controls, view stop timestamps, speed graph, and student events.') }}</small>
        </div>
        <form method="GET" action="{{ route('gps.history.index') }}" class="form-inline">
            <input type="date" name="date" class="form-control form-control-sm mr-2" value="{{ $date }}">
            <select name="vehicle_id" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All Vehicles') }}</option>
                @foreach ($vehicles as $v)
                    <option value="{{ $v->id }}" {{ $vehicleId == $v->id ? 'selected' : '' }}>{{ $v->vehicle_number }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Search') }}</button>
        </form>
    </div>

    <div class="row">
        {{-- Trip selection list --}}
        <div class="col-lg-4 mb-4 mb-lg-0">
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-header bg-white border-0 py-3 font-weight-bold">
                    {{ __('Completed Trips') }} ({{ $trips->total() }})
                </div>
                <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                    @forelse ($trips as $t)
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="loadReplay({{ $t->id }})">
                            <div>
                                <strong class="text-primary">{{ $t->trip_number }}</strong>
                                <span class="badge badge-light border ml-1">{{ $t->trip_type }}</span>
                                <div class="text-xs text-dark mt-1 font-weight-bold">{{ $t->vehicle?->vehicle_number }} • {{ $t->route?->name }}</div>
                                <div class="text-xs text-muted">
                                    {{ $t->actual_start_time?->format('h:i A') }} - {{ $t->actual_end_time?->format('h:i A') }} ({{ $t->duration_minutes }} min)
                                </div>
                            </div>
                            <button class="btn btn-xs btn-outline-primary">
                                <i class="fa fa-play mr-1"></i> {{ __('Replay') }}
                            </button>
                        </div>
                    @empty
                        <div class="p-4 text-center text-muted">
                            <small>{{ __('No completed trips recorded for selected date/vehicle.') }}</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Map & Player Pane --}}
        <div class="col-lg-8">
            <div class="card shadow-sm border-0" style="border-radius: 12px;">
                <div class="card-body p-2">
                    <div id="replay-map"></div>

                    {{-- Replay Bar --}}
                    <div class="replay-player" id="replay-controls" style="display: none;">
                        <div class="d-flex align-items-center mr-3">
                            <button class="btn btn-primary btn-sm rounded-circle mr-2" id="btn-play-pause" onclick="togglePlay()">
                                <i class="fa fa-play" id="play-icon"></i>
                            </button>
                            <div>
                                <div class="font-weight-bold text-sm" id="replay-time">00:00:00</div>
                                <div class="text-xs text-muted" id="replay-speed-val">0 km/h</div>
                            </div>
                        </div>

                        <div style="flex: 1; margin: 0 16px;">
                            <input type="range" id="replay-slider" min="0" max="100" value="0" class="w-100" oninput="seekTo(this.value)">
                        </div>

                        <div class="d-flex align-items-center">
                            <button class="btn btn-sm btn-outline-light mr-1" onclick="setSpeed(1)">1x</button>
                            <button class="btn btn-sm btn-outline-light mr-1" onclick="setSpeed(2)">2x</button>
                            <button class="btn btn-sm btn-outline-light mr-1" onclick="setSpeed(5)">5x</button>
                            <button class="btn btn-sm btn-outline-light" onclick="setSpeed(10)">10x</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var map;
    var replayLocations = [];
    var currentStep = 0;
    var isPlaying = false;
    var playbackSpeed = 1;
    var timer = null;
    var replayMarker = null;
    var pathLine = null;
    var stopMarkers = [];

    document.addEventListener('DOMContentLoaded', function () {
        var defaultLat = {{ config('gpstransport.map.default_center_lat', 11.0168) }};
        var defaultLng = {{ config('gpstransport.map.default_center_lng', 76.9558) }};

        map = L.map('replay-map').setView([defaultLat, defaultLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        @if ($trips->count() > 0)
            loadReplay({{ $trips->first()->id }});
        @endif
    });

    function loadReplay(tripId) {
        pause();
        currentStep = 0;

        fetch('/gps/history/replay/' + tripId)
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.error) return;

                replayLocations = res.locations;
                var stops = res.stops;

                // Clear previous
                if (replayMarker) map.removeLayer(replayMarker);
                if (pathLine) map.removeLayer(pathLine);
                stopMarkers.forEach(function(m) { map.removeLayer(m); });
                stopMarkers = [];

                if (replayLocations.length === 0) {
                    alert('No GPS breadcrumb points recorded for this trip.');
                    document.getElementById('replay-controls').style.display = 'none';
                    return;
                }

                document.getElementById('replay-controls').style.display = 'flex';
                document.getElementById('replay-slider').max = replayLocations.length - 1;
                document.getElementById('replay-slider').value = 0;

                // Render stops
                stops.forEach(function(s, idx) {
                    if (s.lat && s.lng) {
                        var m = L.circleMarker([s.lat, s.lng], {
                            radius: 8,
                            fillColor: '#3b82f6',
                            color: '#fff',
                            weight: 2,
                            fillOpacity: 0.9
                        }).bindPopup('<b>' + s.name + '</b><br>Planned: ' + (s.planned_time || 'N/A') + '<br>Actual: ' + (s.actual_time || 'N/A')).addTo(map);
                        stopMarkers.push(m);
                    }
                });

                // Full path polyline
                var points = replayLocations.map(function(p) { return [p.lat, p.lng]; });
                pathLine = L.polyline(points, { color: '#6366f1', weight: 4, opacity: 0.7 }).addTo(map);
                map.fitBounds(pathLine.getBounds(), { padding: [50, 50] });

                // Vehicle marker
                var start = replayLocations[0];
                var icon = L.divIcon({
                    className: 'replay-bus-icon',
                    html: '<div style="background:#10b981; width:34px; height:34px; border-radius:50%; border:2px solid #fff; display:flex; align-items:center; justify-content:center; color:#fff; box-shadow:0 3px 8px rgba(0,0,0,0.4);"><i class="fa fa-bus"></i></div>',
                    iconSize: [34, 34],
                    iconAnchor: [17, 17]
                });
                replayMarker = L.marker([start.lat, start.lng], { icon: icon }).addTo(map);

                updateDisplay(0);
            });
    }

    function updateDisplay(step) {
        if (!replayLocations[step]) return;
        var p = replayLocations[step];
        replayMarker.setLatLng([p.lat, p.lng]);
        document.getElementById('replay-slider').value = step;
        document.getElementById('replay-time').innerText = p.timestamp;
        document.getElementById('replay-speed-val').innerText = p.speed + ' km/h';
    }

    function togglePlay() {
        if (isPlaying) pause();
        else play();
    }

    function play() {
        if (replayLocations.length === 0) return;
        isPlaying = true;
        document.getElementById('play-icon').className = 'fa fa-pause';

        timer = setInterval(function() {
            currentStep++;
            if (currentStep >= replayLocations.length) {
                pause();
                currentStep = replayLocations.length - 1;
            }
            updateDisplay(currentStep);
        }, 1000 / playbackSpeed);
    }

    function pause() {
        isPlaying = false;
        if (timer) clearInterval(timer);
        document.getElementById('play-icon').className = 'fa fa-play';
    }

    function seekTo(val) {
        currentStep = parseInt(val);
        updateDisplay(currentStep);
    }

    function setSpeed(spd) {
        playbackSpeed = spd;
        if (isPlaying) {
            pause();
            play();
        }
    }
</script>
@endsection
