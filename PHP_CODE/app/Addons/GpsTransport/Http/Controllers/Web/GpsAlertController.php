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

        try {
            $alert = GpsAlert::findOrFail($id);
            $alert->is_resolved = true;
            $alert->resolved_at = Carbon::now();
            $alert->resolved_by = Auth::id() ?? 1;
            $alert->save();

            return response()->json(['error' => false, 'message' => 'Alert marked as resolved']);
        } catch (\Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }
}
