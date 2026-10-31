<?php

namespace App\Http\Controllers;

use App\Models\RouteVehicle;
use App\Models\RouteVehicleAlternateDriver;
use App\Models\Students;
use App\Models\TransportationPayment;
use App\Models\User;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Relief driver roster: when a route vehicle's driver is on leave, an approved alternate
 * driver takes the vehicle for a date range. The driver apps follow the roster through
 * RouteVehicle::operatedBy(), so the alternate sees the route and the primary is locked out.
 */
class AlternateDriverController extends Controller
{
    public function index()
    {
        ResponseService::noFeatureThenRedirect('Transportation Module');
        ResponseService::noPermissionThenRedirect('RouteVehicle-list');

        $routeVehicles = RouteVehicle::with(['route.shift', 'vehicle', 'driver'])->where('status', 1)->get();
        $drivers = User::role('Driver')->where('status', 1)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'mobile']);

        return view('route-vehicle.alternate-drivers', compact('routeVehicles', 'drivers'));
    }

    public function list(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Transportation Module');
        ResponseService::noPermissionThenSendJson('RouteVehicle-list');

        $filter = $request->input('state', 'current');
        $query = RouteVehicleAlternateDriver::with(['routeVehicle.route', 'routeVehicle.vehicle', 'primaryDriver', 'alternateDriver', 'assignedBy'])
            ->orderByDesc('start_date')->orderByDesc('id');

        $today = Carbon::today()->toDateString();
        if ($filter === 'current') {
            $query->where('status', '!=', 'CANCELLED')->whereDate('end_date', '>=', $today);
        } elseif ($filter === 'past') {
            $query->where(fn ($q) => $q->where('status', 'CANCELLED')->orWhereDate('end_date', '<', $today));
        }

        $total = (clone $query)->count();
        $rows = $query->skip((int) $request->input('offset', 0))->take((int) $request->input('limit', 25))->get()
            ->map(function (RouteVehicleAlternateDriver $r, $i) use ($request) {
                $canCancel = $r->state === 'UPCOMING' || $r->state === 'ACTIVE';
                return [
                    'no'               => (int) $request->input('offset', 0) + $i + 1,
                    'id'               => $r->id,
                    'route'            => $r->routeVehicle?->route?->name,
                    'vehicle'          => $r->routeVehicle?->vehicle?->vehicle_number,
                    'primary_driver'   => $r->primaryDriver?->full_name ?? '-',
                    'alternate_driver' => trim(($r->alternateDriver?->full_name ?? '-') . ' ' . ($r->alternateDriver?->mobile ? '(' . $r->alternateDriver->mobile . ')' : '')),
                    'dates'            => $r->start_date->format('d-m-Y') . ($r->end_date->ne($r->start_date) ? ' → ' . $r->end_date->format('d-m-Y') : ''),
                    'shift_type'       => $r->shift_type,
                    'reason'           => $r->reason,
                    'state'            => $r->state,
                    'parents_notified' => $r->parents_notified_at ? $r->parents_notified_at->format('d-m-Y H:i') : ($r->notify_parents ? 'Pending' : 'No'),
                    'assigned_by'      => $r->assignedBy?->full_name,
                    'can_cancel'       => $canCancel,
                ];
            });

        return response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function store(Request $request)
    {
        ResponseService::noFeatureThenSendJson('Transportation Module');
        ResponseService::noAnyPermissionThenSendJson(['RouteVehicle-edit', 'RouteVehicle-create']);

        $validator = Validator::make($request->all(), [
            'route_vehicle_id'    => 'required|integer|exists:route_vehicles,id',
            'alternate_driver_id' => 'required|integer|exists:users,id',
            'start_date'          => 'required|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'shift_type'          => 'required|in:ALL,PICKUP_ONLY,DROP_ONLY',
            'reason'              => 'required|string|max:255',
            'notify_parents'      => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = $request->end_date ? Carbon::parse($request->end_date)->startOfDay() : $start->copy();
        if ($end->lt(Carbon::today())) {
            ResponseService::validationError('The relief period has already ended.');
        }
        if ($start->diffInDays($end) > 90) {
            ResponseService::validationError('A relief period can be at most 90 days. Reassign the route vehicle for longer changes.');
        }

        $routeVehicle = RouteVehicle::with(['route', 'vehicle'])->where('status', 1)->find($request->route_vehicle_id);
        if (!$routeVehicle) {
            ResponseService::validationError('This route vehicle is not active.');
        }
        $alternate = User::role('Driver')->where('status', 1)->find($request->alternate_driver_id);
        if (!$alternate) {
            ResponseService::validationError('The relief driver must be an active user with the Driver role.');
        }
        if ((int) $routeVehicle->driver_id === (int) $alternate->id) {
            ResponseService::validationError('The relief driver is already the primary driver of this vehicle.');
        }

        $runs = match ($request->shift_type) {
            'PICKUP_ONLY' => ['ALL', 'PICKUP_ONLY'],
            'DROP_ONLY'   => ['ALL', 'DROP_ONLY'],
            default       => ['ALL', 'PICKUP_ONLY', 'DROP_ONLY'],
        };
        $overlaps = fn ($q) => $q->where('status', '!=', 'CANCELLED')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->whereIn('shift_type', $runs);

        if (RouteVehicleAlternateDriver::where('route_vehicle_id', $routeVehicle->id)->where($overlaps)->exists()) {
            ResponseService::validationError('This vehicle already has a relief driver for part of these dates. Cancel that entry first.');
        }
        if (RouteVehicleAlternateDriver::where('alternate_driver_id', $alternate->id)->where($overlaps)->exists()) {
            ResponseService::validationError('This driver is already relieving another vehicle on some of these dates.');
        }
        // A driver who has their own bus on the same shift cannot drive two buses at once.
        $busySameShift = RouteVehicle::where('status', 1)
            ->where('id', '!=', $routeVehicle->id)
            ->where('driver_id', $alternate->id)
            ->whereHas('route', fn ($q) => $q->where('shift_id', $routeVehicle->route?->shift_id))
            ->exists();
        if ($busySameShift) {
            ResponseService::validationError('This driver drives another vehicle on the same shift.');
        }

        try {
            DB::beginTransaction();
            $assignment = RouteVehicleAlternateDriver::create([
                'school_id'           => Auth::user()->school_id,
                'route_vehicle_id'    => $routeVehicle->id,
                'primary_driver_id'   => $routeVehicle->driver_id,
                'alternate_driver_id' => $alternate->id,
                'start_date'          => $start->toDateString(),
                'end_date'            => $end->toDateString(),
                'shift_type'          => $request->shift_type,
                'reason'              => $request->reason,
                'notify_parents'      => $request->boolean('notify_parents', true),
                'assigned_by'         => Auth::id(),
                'status'              => 'SCHEDULED',
            ]);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e, 'AlternateDriverController -> store');
            ResponseService::errorResponse();
        }

        $this->notify($assignment, $routeVehicle, $alternate);
        ResponseService::successResponse('Relief driver rostered');
    }

    public function cancel($id)
    {
        ResponseService::noFeatureThenSendJson('Transportation Module');
        ResponseService::noAnyPermissionThenSendJson(['RouteVehicle-edit', 'RouteVehicle-delete']);

        $assignment = RouteVehicleAlternateDriver::find($id);
        if (!$assignment || !in_array($assignment->state, ['UPCOMING', 'ACTIVE'], true)) {
            ResponseService::errorResponse('Only upcoming or active relief entries can be cancelled.');
        }
        $assignment->update(['status' => 'CANCELLED', 'cancelled_by' => Auth::id(), 'cancelled_at' => now()]);

        ResponseService::successResponse('Relief driver cancelled');
    }

    /**
     * Tell the relief driver, and (if asked) the families riding this vehicle, who is
     * driving and when. A failed push must not undo the roster entry.
     */
    private function notify(RouteVehicleAlternateDriver $assignment, RouteVehicle $routeVehicle, User $alternate): void
    {
        if (!function_exists('send_notification')) {
            return;
        }
        $bus = $routeVehicle->vehicle?->vehicle_number ?? 'School bus';
        $route = $routeVehicle->route?->name ?? '';
        $when = $assignment->start_date->isSameDay($assignment->end_date)
            ? $assignment->start_date->format('d M Y')
            : $assignment->start_date->format('d M') . ' – ' . $assignment->end_date->format('d M Y');
        $runText = match ($assignment->shift_type) {
            'PICKUP_ONLY' => ' (morning pickup)',
            'DROP_ONLY'   => ' (afternoon drop)',
            default       => '',
        };

        try {
            send_notification([$alternate->id], 'Relief driving assigned',
                "You will drive {$bus} on route {$route}{$runText}, {$when}.", 'transportation');
        } catch (Throwable $e) {
            Log::info('Relief driver push not sent: ' . $e->getMessage());
        }

        if (!$assignment->notify_parents) {
            return;
        }

        $riderIds = TransportationPayment::where('route_vehicle_id', $routeVehicle->id)
            ->where('status', 'paid')
            ->whereDate('expiry_date', '>=', $assignment->start_date->toDateString())
            ->pluck('user_id')
            ->unique();
        $guardianIds = Students::whereIn('user_id', $riderIds)->pluck('guardian_id');
        // Staff who ride the bus are told directly; students through their guardians.
        $staffRiders = User::whereIn('id', $riderIds)->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Student'))->pluck('id');
        $recipients = $guardianIds->merge($staffRiders)->filter()->unique()->values()->all();

        if (!empty($recipients)) {
            try {
                $phone = $alternate->mobile ? " (Ph: {$alternate->mobile})" : '';
                send_notification($recipients, 'School bus driver change',
                    "{$bus} on route {$route}{$runText} will be driven by {$alternate->full_name}{$phone}, {$when}.", 'transportation');
            } catch (Throwable $e) {
                Log::info('Driver change push not sent: ' . $e->getMessage());
            }
        }
        $assignment->update(['parents_notified_at' => now()]);
    }
}
