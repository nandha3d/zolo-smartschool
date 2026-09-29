<?php

namespace App\Addons\GpsTransport\Http\Controllers\Web;

use App\Addons\GpsTransport\GpsTransportServiceProvider;
use App\Addons\GpsTransport\Models\GpsSetting;
use App\Http\Controllers\Controller;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GpsSettingController
 *
 * Implements Section 84: Transport & GPS Administration Settings.
 */
class GpsSettingController extends Controller
{
    public function index()
    {
        ResponseService::noFeatureThenRedirect(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['vehicles-list', 'route-list']);

        $settings = GpsSetting::pluck('setting_value', 'setting_key')->toArray();

        return view('gpstransport::settings', compact('settings'));
    }

    public function update(Request $request): JsonResponse
    {
        ResponseService::noFeatureThenSendJson(GpsTransportServiceProvider::FEATURE);
        ResponseService::noAnyPermissionThenRedirect(['vehicles-list', 'route-list']);

        $inputs = $request->except(['_token']);

        foreach ($inputs as $key => $value) {
            GpsSetting::set($key, $value);
        }

        return response()->json([
            'error'   => false,
            'message' => 'GPS settings updated successfully',
        ]);
    }
}
