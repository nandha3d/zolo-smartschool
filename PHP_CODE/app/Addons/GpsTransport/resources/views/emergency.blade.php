@extends('layouts.master')

@section('title')
    {{ __('SOS & Breakdown Emergency Operations Center') }}
@endsection

@section('content')
<style>
    .emergency-card-active {
        border-left: 6px solid #ef4444;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.15);
        padding: 20px;
        margin-bottom: 16px;
    }
    .flashing-badge {
        animation: flash-red 1.2s infinite;
    }
    @keyframes flash-red {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
</style>

<div class="content-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
                <i class="fa fa-ambulance text-danger mr-2"></i> {{ __('Emergency & Breakdown Command Center') }}
            </h4>
            <small class="text-muted">{{ __('Real-time response center for driver SOS triggers, mechanical breakdowns, student incidents, and replacement bus dispatch.') }}</small>
        </div>
        <div>
            <span class="badge badge-danger p-2 font-weight-bold flashing-badge">
                <i class="fa fa-circle mr-1"></i> {{ count($activeIncidents) }} {{ __('Active Emergency Case(s)') }}
            </span>
        </div>
    </div>

    {{-- Active Incidents --}}
    <h5 class="font-weight-bold text-dark mb-3">{{ __('Active Emergency Incidents') }}</h5>
    @forelse ($activeIncidents as $incident)
        <div class="emergency-card-active">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="badge badge-danger text-sm font-weight-bold mr-2">{{ $incident->emergency_type }}</span>
                    <strong class="text-dark">{{ $incident->incident_number }}</strong>
                    <span class="text-xs text-muted ml-2">Reported: {{ $incident->reported_at?->diffForHumans() }}</span>
                </div>
                <div>
                    <span class="badge badge-warning">{{ $incident->status }}</span>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-3">
                    <small class="text-muted d-block">{{ __('Vehicle') }}</small>
                    <strong class="text-dark">{{ $incident->vehicle?->vehicle_number }}</strong>
                    <span class="text-xs text-muted d-block">{{ $incident->vehicle?->registration_number }}</span>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">{{ __('Driver') }}</small>
                    <strong class="text-dark">{{ $incident->driver?->name ?? 'Unknown' }}</strong>
                    <span class="text-xs text-muted d-block"><i class="fa fa-phone mr-1"></i>{{ $incident->driver?->phone ?? 'No phone' }}</span>
                </div>
                <div class="col-md-3">
                    <small class="text-muted d-block">{{ __('Route & Students Onboard') }}</small>
                    <strong class="text-dark">{{ $incident->trip?->route?->name ?? 'Unassigned' }}</strong>
                    <span class="badge badge-primary text-xs d-inline-block mt-1">{{ $incident->student_count }} Students</span>
                </div>
                <div class="col-md-3 text-right">
                    <button class="btn btn-sm btn-primary mb-1" onclick="openReplacementModal({{ $incident->id }}, '{{ $incident->vehicle?->vehicle_number }}')">
                        <i class="fa fa-exchange-alt mr-1"></i> {{ __('Assign Replacement Bus') }}
                    </button>
                    <button class="btn btn-sm btn-outline-success mb-1 ml-1" onclick="resolveIncident({{ $incident->id }})">
                        <i class="fa fa-check mr-1"></i> {{ __('Resolve') }}
                    </button>
                </div>
            </div>

            <div class="p-3 bg-light rounded mt-3 border">
                <small class="font-weight-bold text-muted d-block">{{ __('Driver Message / Breakdown Details:') }}</small>
                <div class="text-sm text-dark mt-1">{{ $incident->description }}</div>
                @if ($incident->latitude && $incident->longitude)
                    <div class="text-xs text-primary mt-2">
                        <i class="fa fa-map-marker-alt mr-1"></i> GPS Coordinates: {{ $incident->latitude }}, {{ $incident->longitude }}
                        <a href="https://maps.google.com/?q={{ $incident->latitude }},{{ $incident->longitude }}" target="_blank" class="ml-2 font-weight-bold">View in Google Maps</a>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card shadow-sm border-0 mb-4" style="border-radius: 10px;">
            <div class="card-body p-4 text-center text-muted">
                <i class="fa fa-shield-alt text-success fa-3x mb-2"></i>
                <h6 class="font-weight-bold text-dark">{{ __('No Active Emergency Incidents') }}</h6>
                <p class="mb-0 text-sm">{{ __('All vehicles and drivers are reporting normal operational telemetry.') }}</p>
            </div>
        </div>
    @endforelse

    {{-- Resolved Incidents History --}}
    <h5 class="font-weight-bold text-dark mt-4 mb-3">{{ __('Past Emergency & Breakdown Log') }}</h5>
    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>{{ __('Incident #') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Vehicle') }}</th>
                            <th>{{ __('Driver') }}</th>
                            <th>{{ __('Reported') }}</th>
                            <th>{{ __('Resolved') }}</th>
                            <th>{{ __('Resolution Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pastIncidents as $inc)
                            <tr>
                                <td><strong>{{ $inc->incident_number }}</strong></td>
                                <td><span class="badge badge-secondary">{{ $inc->emergency_type }}</span></td>
                                <td>{{ $inc->vehicle?->vehicle_number }}</td>
                                <td>{{ $inc->driver?->name }}</td>
                                <td>{{ $inc->reported_at?->format('d M, h:i A') }}</td>
                                <td>{{ $inc->resolved_at?->format('d M, h:i A') }}</td>
                                <td class="text-sm text-muted">{{ Str::limit($inc->description, 50) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center p-4 text-muted">
                                    {{ __('No historical emergency incidents recorded.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Assign Replacement Bus Modal --}}
<div class="modal fade" id="replacementModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold">{{ __('Assign Replacement Bus') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form onsubmit="handleAssignReplacement(event)">
                <input type="hidden" id="modal-incident-id">
                <div class="modal-body">
                    <p class="text-sm text-muted">
                        {{ __('Transfer students, remaining stops, and active tracking from broken bus') }}
                        <b id="modal-bus-name"></b> {{ __('to an available replacement vehicle.') }}
                    </p>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Replacement Vehicle') }}</label>
                        <select id="rep-vehicle-id" class="form-control" required>
                            <option value="">{{ __('-- Select Replacement Bus --') }}</option>
                            @foreach ($availableVehicles as $av)
                                <option value="{{ $av->id }}">{{ $av->vehicle_number }} (Capacity: {{ $av->capacity }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Replacement Driver') }}</label>
                        <select id="rep-driver-id" class="form-control">
                            <option value="">{{ __('-- Keep Current Driver or Assign New --') }}</option>
                            @foreach ($drivers as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">{{ __('Action Notes') }}</label>
                        <textarea id="rep-notes" class="form-control" rows="2" placeholder="{{ __('e.g. Dispatched Bus 31 from depot to Gandhipuram stop.') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Confirm Replacement & Transfer Trip') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openReplacementModal(incidentId, busName) {
        document.getElementById('modal-incident-id').value = incidentId;
        document.getElementById('modal-bus-name').innerText = busName;
        $('#replacementModal').modal('show');
    }

    function handleAssignReplacement(e) {
        e.preventDefault();
        var id = document.getElementById('modal-incident-id').value;
        var vehicleId = document.getElementById('rep-vehicle-id').value;
        var driverId = document.getElementById('rep-driver-id').value;
        var notes = document.getElementById('rep-notes').value;

        fetch('/gps/emergency/' + id + '/assign-replacement', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                replacement_vehicle_id: vehicleId,
                replacement_driver_id: driverId || null,
                action_notes: notes
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.error) alert(res.message);
            else {
                $('#replacementModal').modal('hide');
                window.location.reload();
            }
        });
    }

    function resolveIncident(id) {
        var notes = prompt('Enter resolution notes (optional):');
        if (notes === null) return;

        fetch('/gps/emergency/' + id + '/resolve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ resolution_notes: notes })
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.error) alert(res.message);
            else window.location.reload();
        });
    }
</script>
@endsection
