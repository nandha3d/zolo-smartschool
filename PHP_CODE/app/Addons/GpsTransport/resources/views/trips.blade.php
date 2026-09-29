@extends('layouts.master')

@section('title')
    {{ __('Trips & Student Transport Attendance') }}
@endsection

@section('content')
<div class="content-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
                <i class="fa fa-route text-primary mr-2"></i> {{ __('Trips & Rosters') }}
            </h4>
            <small class="text-muted">{{ __('Manage scheduled and active trips, live stop arrivals, student boarding check-in, and child-onboard safety verification.') }}</small>
        </div>
        <div>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createTripModal">
                <i class="fa fa-plus mr-1"></i> {{ __('Schedule New Trip') }}
            </button>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card shadow-sm border-0 mb-3" style="border-radius: 10px;">
        <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <span class="text-xs text-muted font-weight-bold text-uppercase mr-2">{{ __('Filter:') }}</span>
                <a href="{{ route('gps.trips.index') }}" class="btn btn-xs {{ empty($statusFilter) ? 'btn-primary' : 'btn-light' }} mr-1">{{ __('All') }}</a>
                <a href="{{ route('gps.trips.index', ['status' => 'ACTIVE']) }}" class="btn btn-xs {{ $statusFilter === 'ACTIVE' ? 'btn-success' : 'btn-light' }} mr-1">{{ __('Active Now') }}</a>
                <a href="{{ route('gps.trips.index', ['status' => 'SCHEDULED']) }}" class="btn btn-xs {{ $statusFilter === 'SCHEDULED' ? 'btn-info' : 'btn-light' }} mr-1">{{ __('Scheduled') }}</a>
                <a href="{{ route('gps.trips.index', ['status' => 'COMPLETED']) }}" class="btn btn-xs {{ $statusFilter === 'COMPLETED' ? 'btn-secondary' : 'btn-light' }}">{{ __('Completed') }}</a>
            </div>
        </div>
    </div>

    {{-- Trips Table --}}
    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>{{ __('Trip #') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Vehicle') }}</th>
                            <th>{{ __('Driver') }}</th>
                            <th>{{ __('Route') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Students') }}</th>
                            <th>{{ __('Distance') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($trips as $trip)
                            <tr>
                                <td>
                                    <strong class="text-dark">{{ $trip->trip_number }}</strong>
                                    <div class="text-xs text-muted">{{ $trip->created_at->format('d M, h:i A') }}</div>
                                </td>
                                <td>
                                    <span class="badge badge-light border text-xs">{{ $trip->trip_type }}</span>
                                </td>
                                <td>
                                    <strong>{{ $trip->vehicle?->vehicle_number }}</strong>
                                    <div class="text-xs text-muted">{{ $trip->vehicle?->registration_number }}</div>
                                </td>
                                <td>{{ $trip->driver?->name ?? 'Unassigned' }}</td>
                                <td>{{ $trip->route?->name }} ({{ $trip->tripStops->count() }} stops)</td>
                                <td>
                                    @php
                                        $badgeColor = match($trip->status) {
                                            'ACTIVE' => 'success',
                                            'SCHEDULED' => 'info',
                                            'COMPLETED' => 'secondary',
                                            'EMERGENCY' => 'danger',
                                            default => 'warning'
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $badgeColor }}">{{ $trip->status }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-primary">{{ $trip->boarded_students }} / {{ $trip->total_students }}</span>
                                    @if ($trip->status === 'ACTIVE' && ($trip->boarded_students - $trip->dropped_students) > 0)
                                        <span class="badge badge-warning ml-1">{{ $trip->boarded_students - $trip->dropped_students }} Onboard</span>
                                    @endif
                                </td>
                                <td>{{ $trip->distance_km }} km</td>
                                <td class="text-right">
                                    @if ($trip->status === 'SCHEDULED')
                                        <button class="btn btn-xs btn-success" onclick="startTrip({{ $trip->id }})">
                                            <i class="fa fa-play mr-1"></i> {{ __('Start') }}
                                        </button>
                                    @elseif ($trip->status === 'ACTIVE')
                                        <a href="{{ route('gps.live-tracking', ['vehicle_id' => $trip->vehicle_id]) }}" class="btn btn-xs btn-primary mr-1">
                                            <i class="fa fa-crosshairs mr-1"></i> {{ __('Track') }}
                                        </a>
                                        <button class="btn btn-xs btn-danger" onclick="initCompleteTrip({{ $trip->id }}, {{ $trip->boarded_students - $trip->dropped_students }})">
                                            <i class="fa fa-flag-checkered mr-1"></i> {{ __('Complete') }}
                                        </button>
                                    @elseif ($trip->status === 'COMPLETED')
                                        <a href="{{ route('gps.history.index', ['vehicle_id' => $trip->vehicle_id, 'date' => $trip->actual_start_time?->toDateString()]) }}" class="btn btn-xs btn-outline-secondary">
                                            <i class="fa fa-history mr-1"></i> {{ __('Replay') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center p-4 text-muted">
                                    {{ __('No trips found for selected filter.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $trips->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>

{{-- Create Trip Modal --}}
<div class="modal fade" id="createTripModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold">{{ __('Schedule New Transportation Trip') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="create-trip-form" onsubmit="handleCreateTrip(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Trip Type') }}</label>
                        <select name="trip_type" class="form-control" required>
                            <option value="MORNING">{{ __('Morning Pickup') }}</option>
                            <option value="EVENING">{{ __('Evening Drop') }}</option>
                            <option value="SPECIAL">{{ __('Special Trip / Excursion') }}</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Vehicle') }}</label>
                        <select name="vehicle_id" class="form-control" required>
                            <option value="">{{ __('-- Select Vehicle --') }}</option>
                            @foreach ($vehicles as $v)
                                <option value="{{ $v->id }}">{{ $v->vehicle_number }} (Capacity: {{ $v->capacity }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Route') }}</label>
                        <select name="route_id" class="form-control" required>
                            <option value="">{{ __('-- Select Route --') }}</option>
                            @foreach ($routes as $r)
                                <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->routePickupPoints->count() }} stops)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Driver') }}</label>
                        <select name="driver_id" class="form-control">
                            <option value="">{{ __('-- Auto / Assign Later --') }}</option>
                            @foreach ($drivers as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Schedule Trip') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Complete Trip & Child Still Onboard Safety Check Modal --}}
<div class="modal fade" id="completeTripModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold text-danger d-flex align-items-center">
                    <i class="fa fa-shield-alt mr-2"></i> {{ __('Child Still Onboard Safety Check') }}
                </h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="safety-warning-box" class="alert alert-warning mb-3">
                    <i class="fa fa-exclamation-triangle mr-1"></i>
                    <span id="safety-warning-text"></span>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">{{ __('Override Reason (Required if any student remains)') }}</label>
                    <textarea id="complete-override-reason" class="form-control" rows="3" placeholder="{{ __('e.g. Parent picked student directly from campus; verified absent.') }}"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="submitCompleteTrip()">{{ __('Verify & Complete Trip') }}</button>
            </div>
        </div>
    </div>
</div>

<script>
    var completingTripId = null;

    function startTrip(id) {
        if (!confirm('Start this trip and activate mobile GPS tracking?')) return;
        fetch('/gps/trips/' + id + '/start', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.error) alert(res.message);
            else window.location.reload();
        });
    }

    function initCompleteTrip(tripId, onboardCount) {
        completingTripId = tripId;
        if (onboardCount > 0) {
            document.getElementById('safety-warning-text').innerText = 
                'WARNING: ' + onboardCount + ' student(s) are still registered onboard. Every student must be dropped or explicitly verified before closing the trip.';
            $('#completeTripModal').modal('show');
        } else {
            if (confirm('Complete this trip? All students have safely departed.')) {
                submitCompleteTripDirect(tripId);
            }
        }
    }

    function submitCompleteTrip() {
        var reason = document.getElementById('complete-override-reason').value;
        submitCompleteTripDirect(completingTripId, reason);
    }

    function submitCompleteTripDirect(tripId, overrideReason) {
        var body = {};
        if (overrideReason) body.override_reason = overrideReason;

        fetch('/gps/trips/' + tripId + '/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(body)
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.error) {
                alert(res.message);
            } else {
                $('#completeTripModal').modal('hide');
                window.location.reload();
            }
        });
    }

    function handleCreateTrip(e) {
        e.preventDefault();
        var form = e.target;
        var data = {
            trip_type: form.trip_type.value,
            vehicle_id: form.vehicle_id.value,
            route_id: form.route_id.value,
            driver_id: form.driver_id.value || null
        };

        fetch('{{ route("gps.trips.store") }}', {
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
            else {
                $('#createTripModal').modal('hide');
                window.location.reload();
            }
        });
    }
</script>
@endsection
