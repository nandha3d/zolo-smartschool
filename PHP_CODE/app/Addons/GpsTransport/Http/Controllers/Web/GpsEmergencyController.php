<?php

namespace App\Addons\GpsTransport\Http\Controllers\Web;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsEmergencyIncident;
use App\Addons\GpsTransport\Services\EmergencyService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GpsEmergencyController
 *
 * Implements Section 43-47: SOS Operations & Breakdown Center.
 */
class GpsEmergencyController extends Controller
{
    private EmergencyService $emergencyService;

    public function __construct(EmergencyService $emergencyService)
    {
        $this->emergencyService = $emergencyService;
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['vehicles-list', 'route-list', 'RouteVehicle-list']);

        $activeIncidents = GpsEmergencyIncident::with(['vehicle', 'driver', 'trip.route', 'actions'])
            ->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])
            ->latest('reported_at')
            ->get();

        $pastIncidents = GpsEmergencyIncident::with(['vehicle', 'driver', 'trip.route', 'resolver'])
            ->whereIn('status', ['RESOLVED', 'CANCELLED'])
            ->latest('reported_at')
            ->take(15)
            ->get();

        $availableVehicles = Vehicle::where('status', 1)->get();
        $drivers = User::role('Driver')->get();

        return view('gpstransport::emergency', compact(
            'activeIncidents',
            'pastIncidents',
            'availableVehicles',
            'drivers'
        ));
    }

    /**
     * Dispatch replacement vehicle to continue a broken-down or emergency trip.
     */
    public function assignReplacement(Request $request, $id): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        $request->validate([
            'replacement_vehicle_id' => 'required|integer|exists:vehicles,id',
            'replacement_driver_id'  => 'nullable|integer',
            'action_notes'           => 'nullable|string',
        ]);

        try {
            $success = $this->emergencyService->assignReplacementVehicle(
                (int) $id,
                (int) $request->replacement_vehicle_id,
                $request->replacement_driver_id ? (int) $request->replacement_driver_id : null,
                Auth::id() ?? 1,
                $request->action_notes
            );

            return response()->json([
                'error'   => false,
                'message' => 'Replacement vehicle assigned and trip transferred successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolve / Close an incident.
     */
    public function resolve(Request $request, $id): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);

        try {
            $incident = GpsEmergencyIncident::findOrFail($id);
            $incident->status = 'RESOLVED';
            $incident->resolved_at = Carbon::now();
            $incident->resolved_by = Auth::id() ?? 1;
            if ($request->has('resolution_notes')) {
                $incident->description = ($incident->description ? $incident->description . "\nResolution: " : "") . $request->resolution_notes;
            }
            $incident->save();

            return response()->json([
                'error'   => false,
                'message' => 'Emergency incident marked as resolved',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }
}
