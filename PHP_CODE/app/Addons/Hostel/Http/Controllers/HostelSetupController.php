<?php

namespace App\Addons\Hostel\Http\Controllers;

use App\Addons\Hostel\HostelServiceProvider;
use App\Addons\Hostel\Models\HostelBlock;
use App\Addons\Hostel\Models\HostelRoom;
use App\Addons\Hostel\Models\HostelRoomType;
use App\Addons\Hostel\Services\HostelService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Buildings, room types and rooms (beds are created to match each room's capacity). */
class HostelSetupController extends Controller
{
    public function __construct(private HostelService $hostel)
    {
    }

    public function index()
    {
        ResponseService::noFeatureThenRedirect(HostelServiceProvider::FEATURE);
        ResponseService::noPermissionThenRedirect('hostel-list');

        $blocks = HostelBlock::with('warden:id,first_name,last_name')->withCount('rooms')->orderBy('name')->get();
        $roomTypes = HostelRoomType::orderBy('name')->get();
        // Any staff account can be a warden (custom roles included); never students or parents.
        $wardens = User::whereHas('roles', fn($q) => $q->whereNotIn('name', ['Student', 'Guardian', 'Super Admin']))
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
        return view('hostel::setup', compact('blocks', 'roomTypes', 'wardens'));
    }

    public function rooms(Request $request): JsonResponse
    {
        $this->guard('hostel-list');
        $rooms = HostelRoom::with('roomType:id,name', 'beds.currentAllocation.student.user')
            ->when($request->block_id, fn($q, $id) => $q->where('block_id', $id))
            ->orderBy('floor')->orderBy('room_number')->get()
            ->map(fn(HostelRoom $r) => [
                'id' => $r->id,
                'block_id' => $r->block_id,
                'room_number' => $r->room_number,
                'floor' => $r->floor,
                'room_type_id' => $r->room_type_id,
                'room_type' => $r->roomType?->name,
                'bed_capacity' => $r->bed_capacity,
                'status' => $r->status,
                'beds' => $r->beds->sortBy('bed_number')->values()->map(fn($b) => [
                    'id' => $b->id,
                    'bed_number' => $b->bed_number,
                    'allocation_id' => $b->current_allocation_id,
                    'student' => $b->currentAllocation?->student?->user?->full_name,
                    'admission_no' => $b->currentAllocation?->student?->admission_no,
                ]),
            ]);
        return response()->json(['error' => false, 'rooms' => $rooms]);
    }

    public function storeBlock(Request $request): JsonResponse
    {
        $this->guard('hostel-create');
        $data = $this->blockData($request);
        HostelBlock::create($data);
        return $this->ok('Data Stored Successfully');
    }

    public function updateBlock(Request $request, $id): JsonResponse
    {
        $this->guard('hostel-edit');
        HostelBlock::findOrFail($id)->update($this->blockData($request));
        return $this->ok('Data Updated Successfully');
    }

    public function destroyBlock($id): JsonResponse
    {
        $this->guard('hostel-delete');
        $block = HostelBlock::findOrFail($id);
        if ($block->rooms()->whereHas('beds', fn($q) => $q->whereNotNull('current_allocation_id'))->exists()) {
            return $this->fail('Students are still allocated in this block.');
        }
        $block->delete();
        return $this->ok('Data Deleted Successfully');
    }

    public function storeRoomType(Request $request): JsonResponse
    {
        $this->guard('hostel-create');
        HostelRoomType::create($this->roomTypeData($request));
        return $this->ok('Data Stored Successfully');
    }

    public function updateRoomType(Request $request, $id): JsonResponse
    {
        $this->guard('hostel-edit');
        HostelRoomType::findOrFail($id)->update($this->roomTypeData($request));
        return $this->ok('Data Updated Successfully');
    }

    public function destroyRoomType($id): JsonResponse
    {
        $this->guard('hostel-delete');
        HostelRoomType::findOrFail($id)->delete();
        return $this->ok('Data Deleted Successfully');
    }

    /** Create one room, or a run of rooms ("101" to "110") with the same type and capacity. */
    public function storeRooms(Request $request): JsonResponse
    {
        $this->guard('hostel-create');
        $data = $request->validate([
            'block_id' => 'required|integer',
            'room_type_id' => 'nullable|integer',
            'floor' => 'required|integer|min:0|max:100',
            'bed_capacity' => 'required|integer|min:1|max:30',
            'room_number' => 'required|string|max:32',
            'room_number_to' => 'nullable|integer',
        ]);
        HostelBlock::findOrFail($data['block_id']);

        $numbers = [$data['room_number']];
        if (!empty($data['room_number_to']) && ctype_digit($data['room_number'])) {
            $from = (int) $data['room_number'];
            $to = (int) $data['room_number_to'];
            if ($to < $from || $to - $from > 200) {
                return $this->fail('Room range must run forwards and cover at most 200 rooms.');
            }
            $numbers = array_map('strval', range($from, $to));
        }

        $created = 0;
        foreach ($numbers as $number) {
            if (HostelRoom::where('block_id', $data['block_id'])->where('room_number', $number)->exists()) {
                continue;
            }
            $this->hostel->createRoom([
                'block_id' => $data['block_id'],
                'room_type_id' => $data['room_type_id'] ?? null,
                'floor' => $data['floor'],
                'bed_capacity' => $data['bed_capacity'],
                'room_number' => $number,
                'status' => 'Available',
            ]);
            $created++;
        }
        return $this->ok(trans(':count room(s) created.', ['count' => $created]));
    }

    public function updateRoom(Request $request, $id): JsonResponse
    {
        $this->guard('hostel-edit');
        $data = $request->validate([
            'room_type_id' => 'nullable|integer',
            'floor' => 'required|integer|min:0|max:100',
            'bed_capacity' => 'required|integer|min:1|max:30',
            'status' => ['required', Rule::in(['Available', 'Maintenance'])],
        ]);
        $room = HostelRoom::findOrFail($id);
        try {
            $room->update($data);
            $this->hostel->syncBeds($room);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage());
        }
        return $this->ok('Data Updated Successfully');
    }

    public function destroyRoom($id): JsonResponse
    {
        $this->guard('hostel-delete');
        $room = HostelRoom::findOrFail($id);
        if ($room->beds()->whereNotNull('current_allocation_id')->exists()) {
            return $this->fail('Students are still allocated in this room.');
        }
        $room->delete();
        return $this->ok('Data Deleted Successfully');
    }

    private function blockData(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:191',
            'code' => 'nullable|string|max:32',
            'type' => ['required', Rule::in(HostelBlock::TYPES)],
            'total_floors' => 'required|integer|min:1|max:100',
            'warden_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:191',
        ]);
    }

    private function roomTypeData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'fee_cycle' => ['required', Rule::in(array_keys(HostelRoomType::CYCLES))],
            'base_fee' => 'required|numeric|min:0|max:9999999',
            'amenities' => 'nullable|string|max:500',
        ]);
        $data['amenities'] = array_values(array_filter(array_map('trim', explode(',', (string) ($data['amenities'] ?? '')))));
        return $data;
    }

    private function guard(string $permission): void
    {
        ResponseService::noFeatureThenSendJson(HostelServiceProvider::FEATURE);
        ResponseService::noPermissionThenSendJson($permission);
    }

    private function ok(string $message): JsonResponse
    {
        return response()->json(['error' => false, 'message' => trans($message)]);
    }

    private function fail(string $message): JsonResponse
    {
        return response()->json(['error' => true, 'message' => trans($message)], 422);
    }
}
