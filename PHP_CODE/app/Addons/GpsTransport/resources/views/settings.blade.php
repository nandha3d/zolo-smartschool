@extends('layouts.master')

@section('title')
    {{ __('GPS Transport Settings') }}
@endsection

@section('content')
<div class="content-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
                <i class="fa fa-sliders-h text-primary mr-2"></i> {{ __('GPS & Transport Threshold Settings') }}
            </h4>
            <small class="text-muted">{{ __('Configure mobile tracking intervals, geofencing radiuses, speed limits, route deviation thresholds, and notification rules.') }}</small>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-body p-4">
            <form id="gps-settings-form" onsubmit="saveSettings(event)">
                {{-- Group 1: GPS Ingestion Intervals --}}
                <h5 class="text-primary font-weight-bold mb-3 d-flex align-items-center">
                    <i class="fa fa-broadcast-tower mr-2"></i> {{ __('1. Mobile GPS Tracking Intervals (Section 10)') }}
                </h5>
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">{{ __('Moving Vehicle Update Interval (seconds)') }}</label>
                        <input type="number" name="moving_interval_seconds" class="form-control" value="{{ $settings['moving_interval_seconds'] ?? 10 }}" min="3" max="60" required>
                        <small class="text-muted">{{ __('How often moving driver phones emit GPS telemetry. Recommended: 5–10s.') }}</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold">{{ __('Stopped Vehicle Update Interval (seconds)') }}</label>
                        <input type="number" name="stopped_interval_seconds" class="form-control" value="{{ $settings['stopped_interval_seconds'] ?? 30 }}" min="10" max="180" required>
                        <small class="text-muted">{{ __('Frequency when vehicle is stationary to conserve driver phone battery. Recommended: 20–30s.') }}</small>
                    </div>
                    <div class="col-md-6">
                        <label class="font-weight-bold">{{ __('GPS Stale / Offline Threshold (seconds)') }}</label>
                        <input type="number" name="stale_threshold_seconds" class="form-control" value="{{ $settings['stale_threshold_seconds'] ?? 300 }}" min="60" max="1800" required>
                        <small class="text-muted">{{ __('Time without GPS signal before a vehicle is marked as OFFLINE on maps. Recommended: 300s (5 min).') }}</small>
                    </div>
                </div>

                <hr>

                {{-- Group 2: Geofencing & Radiuses --}}
                <h5 class="text-primary font-weight-bold mb-3 d-flex align-items-center mt-4">
                    <i class="fa fa-bullseye mr-2"></i> {{ __('2. Geofencing & Detection Radiuses (Section 27)') }}
                </h5>
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">{{ __('Default Stop Radius (meters)') }}</label>
                        <input type="number" name="default_stop_radius" class="form-control" value="{{ $settings['default_stop_radius'] ?? 100 }}" min="30" max="300" required>
                        <small class="text-muted">{{ __('Arrival geofence radius for bus stops. Recommended: 80–120m.') }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">{{ __('Approaching Distance Threshold (meters)') }}</label>
                        <input type="number" name="approaching_radius" class="form-control" value="{{ $settings['approaching_radius'] ?? 500 }}" min="200" max="1500" required>
                        <small class="text-muted">{{ __('Triggers "Bus Approaching" alert to waiting parents. Recommended: 400–600m.') }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">{{ __('School Geofence Radius (meters)') }}</label>
                        <input type="number" name="school_geofence_radius" class="form-control" value="{{ $settings['school_geofence_radius'] ?? 200 }}" min="100" max="1000" required>
                        <small class="text-muted">{{ __('School campus perimeter for SCHOOL_ENTERED and SCHOOL_EXITED events.') }}</small>
                    </div>
                </div>

                <hr>

                {{-- Group 3: Speed & Route Safety --}}
                <h5 class="text-primary font-weight-bold mb-3 d-flex align-items-center mt-4">
                    <i class="fa fa-shield-alt mr-2"></i> {{ __('3. Speed Limits & Route Deviation (Sections 39 & 40)') }}
                </h5>
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">{{ __('School Bus Speed Limit (km/h)') }}</label>
                        <input type="number" name="default_speed_limit" class="form-control" value="{{ $settings['default_speed_limit'] ?? 45 }}" min="20" max="100" required>
                        <small class="text-muted">{{ __('Overspeed alerts trigger when vehicle exceeds this limit for > 15s.') }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">{{ __('Route Deviation Distance Threshold (meters)') }}</label>
                        <input type="number" name="route_deviation_distance_meters" class="form-control" value="{{ $settings['route_deviation_distance_meters'] ?? 300 }}" min="100" max="1000" required>
                        <small class="text-muted">{{ __('Minimum distance off the planned route polyline to consider deviation.') }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="font-weight-bold">{{ __('Route Deviation Duration (seconds)') }}</label>
                        <input type="number" name="route_deviation_duration_seconds" class="form-control" value="{{ $settings['route_deviation_duration_seconds'] ?? 60 }}" min="20" max="300" required>
                        <small class="text-muted">{{ __('Prevents GPS glitch false-positives by requiring sustained deviation.') }}</small>
                    </div>
                </div>

                <hr>

                {{-- Group 4: Parent Notification Rules --}}
                <h5 class="text-primary font-weight-bold mb-3 d-flex align-items-center mt-4">
                    <i class="fa fa-bell mr-2"></i> {{ __('4. Parent Notification Dispatch Rules (Section 56)') }}
                </h5>
                <div class="row mb-4">
                    <div class="col-md-6 mb-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="notify_parents_on_approaching" class="custom-control-input" id="notif_appr" value="1" {{ !empty($settings['notify_parents_on_approaching']) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-500" for="notif_appr">
                                {{ __('Notify parents when bus is approaching child stop (~500m)') }}
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="notify_parents_on_arrival" class="custom-control-input" id="notif_arr" value="1" {{ !empty($settings['notify_parents_on_arrival']) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-500" for="notif_arr">
                                {{ __('Notify parents when bus arrives at child stop') }}
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="notify_parents_on_boarding" class="custom-control-input" id="notif_board" value="1" {{ !empty($settings['notify_parents_on_boarding']) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-500" for="notif_board">
                                {{ __('Notify parents instantly when child boards the bus') }}
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="notify_parents_on_drop" class="custom-control-input" id="notif_drop" value="1" {{ !empty($settings['notify_parents_on_drop']) ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-500" for="notif_drop">
                                {{ __('Notify parents instantly when child is safely dropped') }}
                            </label>
                        </div>
                    </div>
                </div>

                <div class="text-right mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa fa-save mr-1"></i> {{ __('Save GPS Settings') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function saveSettings(e) {
        e.preventDefault();
        var form = e.target;
        var data = {};
        new FormData(form).forEach(function(val, key) {
            data[key] = val;
        });

        fetch('{{ route("gps.settings.update") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(data)
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.error) alert(res.message);
            else alert('GPS settings saved successfully!');
        });
    }
</script>
@endsection
