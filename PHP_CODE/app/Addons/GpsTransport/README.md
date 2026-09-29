# School Management System — Mobile GPS Transport Module (Addon)

**Addon Name:** GPS Transport Tracking (`GpsTransport`)  
**Feature Flag:** `GPS Transport Tracking`  
**Strategy:** Mobile-phone GPS first (Phase 1), Dedicated vehicle GPS hardware ready (Phase 2)  
**Platforms:** School Web Portal + Driver Mobile App + Parent Mobile App  

---

## 1. Architectural Principle

This module is designed strictly according to `gps-module.md` so that the business logic never couples to "mobile phone GPS". Instead, it depends on the normalized abstraction:

```
Location Provider (MobileLocationProvider / FutureGpsDeviceProvider)
       |
       v
Location Gateway
       |
       v
Normalized Location (DTO)
       |
       v
Tracking Engine
       |
       +---- vehicle_current_states (Fast Redis / In-Memory Cache)
       +---- vehicle_locations (Historical Breadcrumbs & Replay)
       +---- Geofence Service (Stop State Machine: UPCOMING -> APPROACHING -> ARRIVED -> DEPARTED)
       +---- ETA Engine (Dynamic Travel Speed & Stop Dwell Time)
       +---- Route Deviation Engine (Polyline Deviation with Duration Filter)
       +---- Alert Engine (Overspeed, Long Stops, GPS Offline, Mock Location)
       +---- Centralized Notification Engine (FCM Push / Web)
       +---- WebSocket Broadcaster (Ratchet ws://...:8090)
```

When dedicated GPS hardware (Teltonika, Queclink, Concox) is introduced in Phase 2, `FutureGpsDeviceProvider` normalizes incoming packets into the exact same `NormalizedLocation` DTO. Routes, trips, attendance, geofences, ETAs, alerts, and parent portals remain 100% unchanged.

---

## 2. Directory Structure

```
app/Addons/GpsTransport/
├── GpsTransportServiceProvider.php       Provider registration, routes, views, migrations
├── README.md                            Technical and product documentation
├── config/
│   └── gpstransport.php                 Thresholds, intervals, WebSocket & map settings
├── Contracts/
│   └── LocationProviderInterface.php    Core boundary interface
├── DTO/
│   └── NormalizedLocation.php           Normalized telemetry packet
├── Providers/
│   ├── MobileLocationProvider.php       Driver Mobile App GPS ingestion (Phase 1)
│   └── FutureGpsDeviceProvider.php      Hardware GPS device ingestion stub (Phase 2)
├── Services/
│   ├── LocationGateway.php              Routing & validation entry point
│   ├── LocationValidator.php            Coordinates, accuracy, speed bounds & mock GPS checks
│   ├── TrackingEngine.php               State management & event engine dispatcher
│   ├── GeofenceService.php              Stop radii, school boundary & state transitions
│   ├── ETAService.php                   Dynamic distance & speed-based ETA calculations
│   ├── RouteDeviationService.php        Polyline distance & sustained duration detection
│   ├── AlertService.php                 Overspeed, deviation, long stop & offline alerts
│   ├── EmergencyService.php             SOS incidents & replacement bus assignments
│   ├── GpsNotificationService.php       Centralized notification dispatcher
│   └── GpsBroadcaster.php               WebSocket & real-time telemetry broadcaster
├── Models/
│   ├── LocationSource.php               Phase 1 MOBILE_APP / Phase 2 GPS_DEVICE registry
│   ├── GpsSession.php                   Trip tracking session
│   ├── VehicleLocation.php              Historical audit coordinates
│   ├── VehicleCurrentState.php          Fast-lookup single row per vehicle
│   ├── GpsTrip.php                      Scheduled / Active / Completed trips
│   ├── GpsTripStop.php                  Stop state machine & student counts
│   ├── GpsRouteGeometry.php             Polyline geometry & distance
│   ├── GpsGeofence.php                  Geofence zones
│   ├── GpsGeofenceEvent.php             Geofence entry/exit audit trail
│   ├── GpsBoardingEvent.php             Immutable student boarding logs
│   ├── GpsStudentTransportAssignment.php Morning/Evening student assignments
│   ├── GpsStudentTripAttendance.php     Trip student roster
│   ├── GpsAlertRule.php                 Configurable rule thresholds
│   ├── GpsAlert.php                     Recorded safety alerts
│   ├── GpsEmergencyIncident.php         SOS & breakdown cases
│   ├── GpsEmergencyAction.php           Replacement bus allocations
│   └── GpsSetting.php                   Key-value configuration store
├── Http/Controllers/
│   ├── Web/
│   │   ├── GpsDashboardController.php   Overview & KPI cards
│   │   ├── GpsLiveTrackingController.php Fullscreen command center map
│   │   ├── GpsRouteController.php       Visual route & geofence builder
│   │   ├── GpsTripController.php        Trip management & child onboard safety check
│   │   ├── GpsHistoryController.php     Trip replay with playback scrubber
│   │   ├── GpsEmergencyController.php   SOS & breakdown operations
│   │   ├── GpsAlertController.php       Safety alerts list & resolution
│   │   └── GpsSettingController.php     Interval & threshold configuration
│   └── Api/
│       ├── DriverGpsApiController.php   Mobile driver telemetry & attendance API
│       ├── ParentGpsApiController.php   Strictly-scoped parent tracking API
│       └── SchoolGpsApiController.php   Real-time fleet position API
├── Console/Commands/
│   ├── GpsMigrateCommand.php            Master & school database provisioning (`php artisan gps:migrate`)
│   └── GpsSimulateTelemetryCommand.php  Live telemetry generator (`php artisan gps:simulate`)
├── routes/
│   ├── web.php                          Admin & transport manager web routes
│   └── api.php                          Driver, Parent, and School REST endpoints
├── resources/views/                     Blade templates with Leaflet.js mapping
└── lang/en.json                         Fallback localization strings
```

---

## 3. REST API Endpoints

### Driver Mobile App
- `POST /api/driver/gps/today-trips`: Get assigned trips, routes, stops, and student rosters.
- `POST /api/driver/gps/start-trip`: Pre-flight readiness check and GPS session activation.
- `POST /api/driver/gps/location`: Ingest single or batch telemetry points (supports offline sync queue).
- `POST /api/driver/gps/student-attendance`: Record student boarding/dropping with coordinates.
- `POST /api/driver/gps/sos`: Emergency SOS trigger.
- `POST /api/driver/gps/breakdown`: Report vehicle breakdown with problem category.
- `POST /api/driver/gps/end-trip`: Complete trip with mandatory **Child Still Onboard Safety Check**.

### Parent Mobile App (Privacy-First)
- `POST /api/parent/gps/children-transport`: Lists transport status for authenticated parent's children.
- `POST /api/parent/gps/track-bus`: Live bus coordinates, speed, heading, next stop, and ETA to child's stop. Strictly verifies parent -> student -> route -> vehicle authorization.
- `POST /api/parent/gps/history`: Child's recent boarding event history.

### School Web Portal
- `GET /api/school/gps/fleet-live`: Real-time fleet telemetry for all active vehicles.
- `GET /api/school/gps/trip/{id}/telemetry`: Detailed telemetry stream for active trip.

---

## 4. Setup & Migration

Run the unified migration command to register the feature on the master database and provision all school tenant databases:

```bash
php artisan gps:migrate
```

To simulate vehicle movement and test real-time tracking in development:

```bash
php artisan gps:simulate --steps=20 --delay=2
```
