<?php

return [
    /*
     * Feature name as registered in the master features table
     */
    'feature_name' => 'GPS Transport Tracking',

    /*
     * Default GPS ingestion & tracking settings
     */
    'defaults' => [
        // Mobile GPS update intervals (seconds)
        'moving_interval_seconds' => 10,
        'stopped_interval_seconds' => 30,
        'offline_sync_batch_size' => 50,

        // GPS Health thresholds
        'stale_threshold_seconds' => 300, // 5 minutes without GPS = STALE
        'accuracy_threshold_meters' => 100, // Locations with accuracy > 100m flagged as low accuracy

        // Geofencing
        'default_stop_radius_meters' => 100,
        'approaching_radius_meters' => 500,
        'school_radius_meters' => 200,

        // Safety rules
        'speed_limit_kmh' => 45.0,
        'overspeed_duration_seconds' => 20,
        'route_deviation_distance_meters' => 300,
        'route_deviation_duration_seconds' => 60,
        'long_stop_duration_seconds' => 600, // 10 minutes stopped at non-stop location

        // Location source configuration
        'primary_source' => 'MOBILE_APP', // 'MOBILE_APP' | 'GPS_DEVICE'
        'device_fallback_enabled' => true,
    ],

    /*
     * WebSocket configuration for live tracking broadcasts
     */
    'websocket' => [
        'enabled' => true,
        'host' => env('GPS_WS_HOST', '127.0.0.1'),
        'port' => env('GPS_WS_PORT', 8090),
        'secure' => env('GPS_WS_SECURE', false),
        'channel_prefix' => 'gps.fleet',
    ],

    /*
     * Map configuration
     */
    'map' => [
        'provider' => env('GPS_MAP_PROVIDER', 'leaflet_osm'), // 'leaflet_osm' or 'google_maps'
        'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY', ''),
        'default_center_lat' => 11.0168,
        'default_center_lng' => 76.9558,
        'default_zoom' => 13,
        // Raster tile server for the web maps and the parent app. The public OSM servers
        // are for light use only; point this at your own tile server for production.
        'tile_url' => env('GPS_MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('GPS_MAP_ATTRIBUTION', '© OpenStreetMap contributors'),
    ],
];
