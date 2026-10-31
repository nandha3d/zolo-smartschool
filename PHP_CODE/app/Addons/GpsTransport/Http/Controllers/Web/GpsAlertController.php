<?php

namespace App\Addons\GpsTransport\Http\Controllers\Web;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsAlert;
use App\Http\Controllers\Controller;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GpsAlertController
 *
 * Implements Section 42 & 66: Alert Rules & Safety Incidents Monitoring.
 */
class GpsAlertController extends Controller
{
    public function index()
    {
        $request = request();
        ResponseService::noFeatureThenRedirect(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['vehicles-list', 'route-list', 'RouteVehicle-list']);

        $severityFilter = $request->get('severity');
        $resolvedFilter = $request->get('resolved', '0');

        $query = GpsAlert::with(['vehicle', 'trip.route', 'resolver'])->latest();

        if ($severityFilter) {
            $query->where('severity', $severityFilter);
        }
        if ($resolvedFilter !== 'all') {
            $query->where('is_resolved', $resolvedFilter === '1');
        }

        $alerts = $query->paginate(20);

        return view('gpstransport::alerts', compact('alerts', 'severityFilter', 'resolvedFilter'));
    }

    public function resolve($id): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenSendJson(['vehicles-list', 'route-list', 'RouteVehicle-list']);

        $alert = GpsAlert::find($id);
        if (!$alert) {
            return response()->json(['error' => true, 'message' => 'Alert not found'], 404);
        }
        $alert->is_resolved = true;
        $alert->resolved_at = Carbon::now();
        $alert->resolved_by = Auth::id();
        $alert->acknowledged_at ??= Carbon::now();
        $alert->acknowledged_by ??= Auth::id();
        $alert->save();

        return response()->json(['error' => false, 'message' => 'Alert marked as resolved']);
    }

    /**
     * Overspeed alerts nobody has acknowledged yet, polled by every admin page so the
     * siren popup fires within seconds of the alert being raised.
     */
    public function live(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenSendJson(['vehicles-list', 'route-list', 'RouteVehicle-list']);

        $alerts = GpsAlert::with(['vehicle', 'driver', 'route', 'trip.route', 'trip.driver'])
            ->where('alert_type', 'OVERSPEED')
            ->whereNull('acknowledged_at')
            ->where('created_at', '>=', Carbon::now()->subHours(6))
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (GpsAlert $a) => $this->present($a));

        return response()->json(['error' => false, 'alerts' => $alerts]);
    }

    /**
     * Admin acknowledges an overspeed alert (optionally with remarks such as "called driver").
     */
    public function acknowledge(Request $request, $id): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenSendJson(['vehicles-list', 'route-list', 'RouteVehicle-list']);
        $request->validate(['remarks' => 'nullable|string|max:500']);

        $alert = GpsAlert::find($id);
        if (!$alert) {
            return response()->json(['error' => true, 'message' => 'Alert not found'], 404);
        }
        if (!$alert->acknowledged_at) {
            $alert->acknowledged_at = Carbon::now();
            $alert->acknowledged_by = Auth::id();
        }
        if ($request->filled('remarks')) {
            $alert->admin_remarks = $request->remarks;
        }
        $alert->save();

        return response()->json(['error' => false, 'message' => 'Alert acknowledged']);
    }

    private function present(GpsAlert $a): array
    {
        $driver = $a->driver ?? $a->trip?->driver;
        $route = $a->route ?? $a->trip?->route;

        return [
            'id'             => $a->id,
            'vehicle_id'     => $a->vehicle_id,
            'vehicle_number' => $a->vehicle?->vehicle_number ?? ('#' . $a->vehicle_id),
            'speed'          => round((float) $a->speed, 1),
            'peak_speed'     => round((float) ($a->peak_speed ?? $a->speed), 1),
            'speed_limit'    => $a->speed_limit !== null ? round((float) $a->speed_limit, 1) : null,
            'duration'       => (int) $a->duration_seconds,
            'driver_name'    => $driver?->full_name ?? '-',
            'driver_phone'   => $driver?->mobile ?? '',
            'route_name'     => $route?->name ?? '-',
            'latitude'       => $a->latitude,
            'longitude'      => $a->longitude,
            'message'        => $a->message,
            'created_at'     => $a->created_at?->format('d-m-Y h:i:s A'),
            'map_url'        => route('gps.live-tracking', ['vehicle_id' => $a->vehicle_id]),
        ];
    }
}
