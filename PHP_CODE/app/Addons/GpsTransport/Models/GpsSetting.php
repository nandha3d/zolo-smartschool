<?php

namespace App\Addons\GpsTransport\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GpsSetting Model
 *
 * Implements Section 84: Admin Settings.
 * Key-value configuration store for transport and GPS thresholds.
 */
class GpsSetting extends Model
{
    protected $table = 'gps_settings';

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];

    public static function get(string $key, $default = null)
    {
        $setting = static::where('setting_key', $key)->first();
        return $setting ? $setting->setting_value : $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => (string) $value]
        );
    }
}
