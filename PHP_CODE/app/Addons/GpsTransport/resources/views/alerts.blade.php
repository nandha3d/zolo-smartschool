@extends('layouts.master')

@section('title')
    {{ __('Driver Safety & Transport Alerts') }}
@endsection

@section('content')
<div class="content-wrapper p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 font-weight-bold text-dark d-flex align-items-center">
                <i class="fa fa-bell text-warning mr-2"></i> {{ __('Safety Alerts & Compliance Log') }}
            </h4>
            <small class="text-muted">{{ __('Monitor overspeed events, persistent route deviations, unexpected long stops, and GPS integrity warnings.') }}</small>
        </div>
        <div class="d-flex align-items-center">
            <a href="{{ route('gps.alerts.index', ['resolved' => '0']) }}" class="btn btn-xs {{ $resolvedFilter === '0' ? 'btn-primary' : 'btn-light' }} mr-1">{{ __('Unresolved') }}</a>
            <a href="{{ route('gps.alerts.index', ['resolved' => '1']) }}" class="btn btn-xs {{ $resolvedFilter === '1' ? 'btn-success' : 'btn-light' }} mr-1">{{ __('Resolved') }}</a>
            <a href="{{ route('gps.alerts.index', ['resolved' => 'all']) }}" class="btn btn-xs {{ $resolvedFilter === 'all' ? 'btn-secondary' : 'btn-light' }}">{{ __('All') }}</a>
        </div>
    </div>

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('Vehicle') }}</th>
                            <th>{{ __('Severity') }}</th>
                            <th>{{ __('Alert Type') }}</th>
                            <th>{{ __('Message / Detail') }}</th>
                            <th>{{ __('Speed') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($alerts as $a)
                            <tr>
                                <td>
                                    <strong class="text-dark">{{ $a->created_at->format('h:i A') }}</strong>
                                    <div class="text-xs text-muted">{{ $a->created_at->format('d M Y') }}</div>
                                </td>
                                <td>
                                    <strong>{{ $a->vehicle?->vehicle_number ?? 'N/A' }}</strong>
                                    <div class="text-xs text-muted">{{ $a->trip?->route?->name ?? 'Unassigned' }}</div>
                                </td>
                                <td>
                                    @php
                                        $badge = match($a->severity) {
                                            'CRITICAL' => 'danger',
                                            'WARNING' => 'warning',
                                            default => 'info'
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $badge }}">{{ $a->severity }}</span>
                                </td>
                                <td><strong>{{ $a->alert_type }}</strong></td>
                                <td class="text-sm text-dark">{{ $a->message }}</td>
                                <td>{{ $a->speed ? $a->speed . ' km/h' : '-' }}</td>
                                <td>
                                    @if ($a->is_resolved)
                                        <span class="badge badge-success">{{ __('Resolved') }}</span>
                                    @else
                                        <span class="badge badge-warning">{{ __('Open') }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if (!$a->is_resolved)
                                        <button class="btn btn-xs btn-outline-success" onclick="resolveAlert({{ $a->id }})">
                                            <i class="fa fa-check mr-1"></i> {{ __('Resolve') }}
                                        </button>
                                    @else
                                        <span class="text-xs text-muted">{{ $a->resolved_at?->diffForHumans() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center p-4 text-muted">
                                    {{ __('No safety alerts found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $alerts->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>

<script>
    function resolveAlert(id) {
        fetch('/gps/alerts/' + id + '/resolve', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .then(function(res) { return res.json(); })
        .then(function(res) {
            if (res.error) alert(res.message);
            else window.location.reload();
        });
    }
</script>
@endsection
