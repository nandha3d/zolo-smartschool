# Zolo SmartSchool — GPS Integration Completion & Implementation Specification

## 0. PURPOSE

This document is a **LOW-LEVEL IMPLEMENTATION SPECIFICATION FOR AN AI CODING AGENT**.

The repository already contains a substantial GPS backend/addon, but the current implementation is **NOT COMPLETE** according to the GPS architecture specification.

The agent MUST inspect the existing implementation first, then implement all missing pieces described below.

Repository:

```text
nandha3d/zolo-smartschool
```

Primary areas:

```text
PHP_CODE/
App code/eschool-saas-staff/eschool-saas-staff/
App code/e-school-saas-student-parent/e-school-saas/
```

Do NOT create a parallel GPS system.

Do NOT remove working transport functionality.

Do NOT break existing Flutter APIs.

The goal is to connect the existing GPS backend architecture to the real driver mobile GPS source and to the parent/school consumers.

---

# 1. CURRENT AUDIT RESULT

## 1.1 Overall status

Current repository status:

| Area | Current status |
|---|---|
| GPS backend addon architecture | Mostly implemented |
| GPS database | Mostly implemented |
| Location provider abstraction | Implemented |
| Location validation | Implemented |
| Tracking engine | Implemented |
| Geofencing | Mostly implemented |
| ETA | Partially/mostly implemented |
| Route deviation | Partially implemented |
| Alert engine | Partially implemented |
| Emergency/SOS backend | Partially implemented |
| School live tracking backend | Implemented |
| Trip history/replay backend | Implemented |
| Parent GPS backend API | Implemented |
| Driver phone GPS collection | NOT implemented |
| Driver background GPS service | NOT implemented |
| Driver offline GPS queue | NOT implemented |
| Driver GPS upload integration | NOT implemented |
| Parent Flutter integration with new GPS API | NOT implemented |
| Driver trip start -> GPS start integration | NOT implemented |
| Driver trip end -> GPS stop integration | NOT implemented |
| Real-time WebSocket client integration | NOT complete |
| GPS-specific automated tests | NOT complete |
| Long-stop alert | NOT complete |
| GPS-offline alert | NOT complete |
| Proper route-segment deviation | NOT complete |
| Complete driver/trip/vehicle authorization | MUST be hardened |

---

# 2. IMPORTANT EXISTING FILES

Before changing anything, inspect these files.

## Backend

```text
PHP_CODE/app/Addons/GpsTransport/
```

Important existing classes include:

```text
GpsTransportServiceProvider.php

Contracts/LocationProviderInterface.php

DTO/NormalizedLocation.php

Providers/MobileLocationProvider.php
Providers/FutureGpsDeviceProvider.php

Services/LocationGateway.php
Services/LocationValidator.php
Services/TrackingEngine.php
Services/GeofenceService.php
Services/ETAService.php
Services/RouteDeviationService.php
Services/AlertService.php
Services/EmergencyService.php
Services/GpsBroadcaster.php
Services/GpsNotificationService.php

Http/Controllers/Api/DriverGpsApiController.php
Http/Controllers/Api/ParentGpsApiController.php
Http/Controllers/Api/SchoolGpsApiController.php

Http/Controllers/Web/GpsLiveTrackingController.php
Http/Controllers/Web/GpsRouteController.php
Http/Controllers/Web/GpsHistoryController.php
```

The GPS database migration and GPS models are also under the same addon.

## Driver Flutter app

```text
App code/eschool-saas-staff/eschool-saas-staff/
```

Important existing files:

```text
lib/cubits/transport/tripsCubit.dart
lib/data/repositories/transportRepository.dart
lib/ui/screens/tripDetailsScreen/tripDetailsScreen.dart
lib/ui/screens/tripDetailsScreen/widgets/startTripBottomSheet.dart
lib/ui/screens/tripDetailsScreen/widgets/tripActionButtons.dart
```

## Parent/Student Flutter app

```text
App code/e-school-saas-student-parent/e-school-saas/
```

Important existing files:

```text
lib/cubits/liveRouteCubit.dart
lib/data/repositories/liveRouteRepository.dart
lib/ui/screens/parentTransportEnroll/transportHome/widgets/liveTrackingCard.dart
lib/ui/screens/parentTransportEnroll/transportHome/widgets/liveRouteBottomSheet.dart
```

---

# 3. CRITICAL ARCHITECTURE

The final architecture MUST be:

```text
DRIVER PHONE
    |
    | GPS coordinates
    v
Flutter Location Service
    |
    +--> foreground tracking
    |
    +--> background tracking
    |
    +--> local offline queue
    |
    v
GPS Upload Service
    |
    | HTTPS
    v
Laravel GPS API
    |
    v
DriverGpsApiController
    |
    v
LocationGateway
    |
    v
LocationValidator
    |
    v
TrackingEngine
    |
    +--> VehicleLocation history
    |
    +--> VehicleCurrentState
    |
    +--> GeofenceService
    |
    +--> ETAService
    |
    +--> RouteDeviationService
    |
    +--> AlertService
    |
    +--> GpsNotificationService
    |
    +--> GpsBroadcaster
    |
    v
CONSUMERS

Parent Flutter
School Web Dashboard
School Admin
Transport Manager
```

The GPS backend MUST remain provider-agnostic.

Do not place GPS business logic directly into Flutter.

Do not place geofence/ETA logic in Flutter.

---

# 4. DRIVER MOBILE GPS — REQUIRED

This is the largest missing implementation.

## 4.1 Add location dependency

Inspect the existing Flutter dependency versions first.

Use a stable Flutter GPS library compatible with the project's current Flutter/Dart version.

Preferred implementation:

```yaml
geolocator:
```

If another existing dependency is already suitable, reuse it.

Do not add multiple GPS packages unnecessarily.

---

# 5. ANDROID LOCATION PERMISSIONS

Update:

```text
App code/eschool-saas-staff/eschool-saas-staff/android/app/src/main/AndroidManifest.xml
```

Add the permissions required by the chosen GPS implementation.

At minimum, for Android:

```xml
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION"/>
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION"/>
<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION"/>
```

For foreground service based tracking, also configure the appropriate foreground-service permissions for the Android SDK version used by the project.

For modern Android versions, the implementation MUST correctly handle:

```text
location permission
background location permission
foreground service location permission
notification permission where required
```

Do not blindly request all permissions at startup.

Only request location permissions when the driver attempts to start a trip.

---

# 6. IOS LOCATION PERMISSIONS

Update:

```text
App code/eschool-saas-staff/eschool-saas-staff/ios/Runner/Info.plist
```

Required descriptions:

```xml
NSLocationWhenInUseUsageDescription
NSLocationAlwaysAndWhenInUseUsageDescription
```

If the chosen background implementation requires additional iOS configuration, implement it correctly.

Update:

```xml
UIBackgroundModes
```

to include the background location mode required by the chosen implementation.

Do not claim background tracking works unless the iOS configuration actually supports it.

---

# 7. DRIVER GPS SERVICE

Create a dedicated service.

Recommended structure:

```text
lib/services/gps/
    gps_tracking_service.dart
    gps_upload_service.dart
    gps_permission_service.dart
    gps_offline_queue.dart
    gps_session_manager.dart
    gps_models.dart
```

Names may be adapted to existing project conventions.

Do NOT put GPS tracking code inside:

```text
TripsCubit
TripDetailsScreen
TripActionButtons
```

Those components should call the GPS service.

---

# 8. GPS TRACKING SERVICE REQUIREMENTS

The service MUST expose an API similar to:

```dart
Future<bool> initialize();

Future<bool> requestPermissions();

Future<void> startTracking({
  required int tripId,
  required int vehicleId,
});

Future<void> stopTracking();

bool get isTracking;

Stream<GpsTrackingState> get stateStream;
```

State should include at least:

```text
idle
permissionDenied
starting
tracking
paused
offline
error
stopping
stopped
```

---

# 9. LOCATION DATA MODEL

Every location sample should contain:

```text
latitude
longitude
accuracy
altitude
speed
heading
timestamp
batteryLevel
isMocked
provider/source
tripId
vehicleId
```

Use nullable values where the platform cannot provide the value.

Do not substitute fake coordinates.

Do not silently convert invalid coordinates to:

```text
0,0
```

Invalid coordinates MUST be rejected.

---

# 10. LOCATION SAMPLING

Implement adaptive sampling.

## Moving

Target:

```text
5–10 seconds
```

or distance-based equivalent.

## Stationary

Target:

```text
20–30 seconds
```

The implementation should avoid excessive battery consumption.

Do not keep a 1-second GPS loop.

---

# 11. DISTANCE FILTER

Use a reasonable minimum displacement filter.

Recommended starting point:

```text
10–20 meters
```

The exact value should be configurable.

Example:

```text
GPS_CONFIG_LOCATION_DISTANCE_FILTER
```

Do not hardcode the value throughout the application.

---

# 12. BACKGROUND TRACKING

The driver MUST be able to:

1. Start a trip.
2. Lock the phone.
3. Minimize the application.
4. Continue driving.
5. Keep GPS collection active.
6. Upload location points.
7. Recover after temporary network loss.

The GPS service MUST NOT depend on the trip screen remaining visible.

This is a hard requirement.

---

# 13. START TRIP INTEGRATION

Current Flutter trip start uses the existing transport API:

```text
driver-helpr/trip/start-end
```

Do not remove it.

After the backend successfully starts the trip:

```text
Trip start success
        |
        v
obtain trip_id
        |
        v
obtain vehicle_id
        |
        v
GPS Tracking Service.startTracking()
```

GPS MUST NOT start if the trip API fails.

If GPS permission is denied:

- clearly inform the driver
- do not silently pretend GPS is active
- follow the product's configured policy for whether the trip may continue
- record GPS unavailable status

The backend must remain authoritative for trip state.

---

# 14. STOP TRIP INTEGRATION

When the trip ends successfully:

```text
Trip end success
       |
       v
GPS Tracking Service.stopTracking()
       |
       v
flush pending queue
       |
       v
close local GPS session
```

Do not stop GPS before the backend confirms trip completion unless an emergency recovery policy explicitly requires it.

---

# 15. GPS UPLOAD API

The driver application must upload location data to the GPS backend.

Use the existing GPS API if present.

Do not create duplicate endpoints without first inspecting:

```text
DriverGpsApiController.php
routes/api.php
```

The upload payload must contain enough information to identify:

```text
trip
vehicle
location
timestamp
accuracy
speed
heading
battery
mock status
```

---

# 16. SERVER-SIDE AUTHORIZATION

This is a HARD SECURITY REQUIREMENT.

Never trust the mobile client to choose an arbitrary vehicle.

The server must verify:

```text
authenticated user
        |
        v
authorized driver
        |
        v
active/authorized trip
        |
        v
trip vehicle
        |
        v
GPS session
```

A driver must NOT be able to submit:

```text
driver A -> vehicle B
driver A -> trip C
```

unless the server confirms that relationship.

The server should derive vehicle/trip relationships from trusted database records whenever possible.

---

# 17. LOCATION VALIDATION

`LocationValidator` must validate:

```text
latitude range: -90 to +90
longitude range: -180 to +180
accuracy >= 0
speed >= 0
heading >= 0 and < 360
timestamp reasonable
```

Reject:

```text
NaN
Infinity
0,0
future timestamps beyond allowed clock skew
extremely old timestamps
impossible movement
```

---

# 18. IMPOSSIBLE MOVEMENT

Implement sanity checks.

Example:

```text
previous location
       |
       v
new location
       |
       v
distance / elapsed time
       |
       v
calculated speed
```

If calculated speed is physically unreasonable:

```text
reject
or mark suspicious
```

Do not automatically delete the point from history if auditing requires retention.

---

# 19. MOCK LOCATION DETECTION

On Android/iOS, capture the platform's mock-location indicator where available.

Send:

```text
is_mocked
```

to backend.

Backend must retain the flag.

Mock location should trigger the configured security/alert policy.

Do not rely only on client-provided mock status.

---

# 20. OFFLINE GPS QUEUE

Implement a persistent local queue.

Do NOT use only an in-memory list.

Use an appropriate local storage mechanism compatible with the application.

Recommended queue record:

```text
id
trip_id
vehicle_id
latitude
longitude
accuracy
speed
heading
timestamp
battery_level
is_mocked
created_at
upload_attempts
```

---

# 21. OFFLINE QUEUE BEHAVIOR

When network is unavailable:

```text
GPS point
   |
   v
local persistent queue
```

When network returns:

```text
queue
  |
  v
batch upload
  |
  v
server success
  |
  v
delete acknowledged records
```

Do NOT delete a record before server acknowledgement.

---

# 22. QUEUE LIMIT

Configure a maximum local queue size.

Example:

```text
MAX_PENDING_GPS_POINTS
```

When the queue is full:

- preserve the newest data
- apply a documented retention policy
- never crash the application

---

# 23. BATCH UPLOAD

Prefer batch upload when multiple points are pending.

Example:

```json
{
  "trip_id": 123,
  "vehicle_id": 10,
  "locations": [
    {
      "latitude": 11.0123,
      "longitude": 76.1234,
      "accuracy": 8.5,
      "speed": 32.1,
      "heading": 90,
      "timestamp": "..."
    }
  ]
}
```

If the backend only supports one-point uploads, add a safe batch endpoint only if necessary.

Do not duplicate processing logic between single and batch endpoints.

---

# 24. RETRY POLICY

Use controlled retry.

Recommended:

```text
1st retry: short delay
2nd retry: increasing delay
3rd retry: longer delay
```

Use exponential backoff with a maximum limit.

Do not retry infinitely without delay.

---

# 25. DUPLICATE LOCATION HANDLING

The server should tolerate duplicate uploads.

Use a client-generated location/event ID or equivalent idempotency mechanism where practical.

The same GPS point uploaded twice must not create duplicate business events.

---

# 26. GPS SESSION MANAGEMENT

A GPS session should be tied to:

```text
trip_id
vehicle_id
driver_id
started_at
ended_at
status
```

Starting a trip should create/activate the session.

Ending a trip should close it.

A driver must not have multiple active GPS sessions for the same vehicle/trip unless explicitly supported.

---

# 27. VEHICLE CURRENT STATE

For each active vehicle maintain:

```text
latitude
longitude
speed
heading
status
gps_status
last_location_at
eta
next_stop
battery_level
trip_id
```

This is for fast live queries.

Historical points belong in:

```text
vehicle_locations
```

Do not query the entire location history for every parent refresh.

---

# 28. PARENT APP INTEGRATION

The current parent app uses:

```text
transportation/live-route
```

through:

```text
LiveRouteCubit
LiveRouteRepository
LiveRouteBottomSheet
```

The new GPS API must be integrated.

Inspect:

```text
ParentGpsApiController.php
```

and use the new GPS response as the authoritative live vehicle data.

---

# 29. PARENT LIVE DATA

Parent view should be able to show:

```text
bus position
vehicle number
route
driver
speed
heading
GPS status
last update
next stop
ETA
child pickup/drop stop
boarding status
```

Do not show stale GPS as if it were current.

---

# 30. PARENT MAP

The parent live tracking UI should contain an actual map.

Minimum:

```text
Map
  |
  +-- bus marker
  +-- child's stop marker
  +-- route/stops if available
  +-- current/next stop
  +-- last update status
  +-- ETA
```

If Google Maps is already used, reuse the existing Google Maps integration.

Do not introduce another map SDK without reason.

---

# 31. STALE GPS UI

Parent UI must clearly distinguish:

```text
LIVE
STALE
OFFLINE
```

Suggested policy:

```text
LIVE    = recent telemetry
STALE   = telemetry older than configured threshold
OFFLINE = no valid recent telemetry / session offline
```

Do not show a stale location with a green "Live" status.

---

# 32. SCHOOL FLEET DASHBOARD

The existing:

```text
SchoolGpsApiController::fleetLive()
```

should remain the school fleet source.

Verify that:

```text
vehicle
route
driver
location
speed
heading
status
ETA
next stop
last update
```

are correctly displayed.

---

# 33. SCHOOL TRIP TELEMETRY

Existing:

```text
SchoolGpsApiController::tripTelemetry()
```

must provide:

```text
current location
current speed
heading
ETA
trail
stops
```

Ensure the trail is ordered chronologically.

Do not use an arbitrary fixed limit without pagination or a documented replay limit.

---

# 34. TRIP HISTORY / REPLAY

Existing:

```text
GpsHistoryController
```

already supports replay.

Verify:

```text
location trail
stops
boarding events
distance
duration
start time
end time
vehicle
driver
route
```

The UI must be able to replay the route chronologically.

---

# 35. GEOFENCING

Existing geofence functionality must remain.

Each stop should support:

```text
latitude
longitude
geofence_radius
```

Default radius should be configurable.

Do not hardcode the radius throughout the code.

---

# 36. GEOFENCE STATE MACHINE

Avoid repeatedly firing the same event.

Use states:

```text
OUTSIDE
APPROACHING
INSIDE
EXITED
```

Example:

```text
OUTSIDE
  |
  | enters approach radius
  v
APPROACHING
  |
  | enters geofence
  v
INSIDE
  |
  | leaves geofence
  v
EXITED
```

Notifications must be idempotent.

---

# 37. STOP APPROACHING

When bus approaches a student's stop:

```text
BUS_APPROACHING
```

should be generated according to configured distance thresholds.

Do not send the same notification on every GPS update.

---

# 38. STOP ARRIVAL

When the vehicle enters the stop geofence:

```text
BUS_ARRIVED_AT_STOP
```

should be generated once per trip/stop event.

---

# 39. BOARDING / DROPOFF GPS

Boarding and dropoff events should contain:

```text
student_id
trip_id
vehicle_id
stop_id
latitude
longitude
timestamp
event_type
```

This provides an auditable location for the event.

---

# 40. ETA ENGINE

ETA must not simply return a static value.

It should use available:

```text
current position
current speed
route geometry
remaining route
next stop
historical/estimated travel time
```

If insufficient data exists:

```text
ETA = unavailable/calculating
```

Do not invent an ETA.

---

# 41. ROUTE DEVIATION

Current implementation must be corrected.

Do NOT calculate deviation only from waypoint-to-point distances.

Correct approach:

```text
GPS point
     |
     v
route polyline
     |
     v
minimum point-to-segment distance
```

For each line segment:

```text
A ---------------- B
       ^
       |
       P
```

Calculate the shortest distance from P to segment AB.

Take the minimum across the route.

Use the configured deviation threshold.

---

# 42. ROUTE DEVIATION ALERT

Generate a deviation alert only when:

```text
distance_from_route > configured_threshold
```

and maintain an alert state so that every GPS point does not generate a duplicate alert.

Recommended states:

```text
ON_ROUTE
DEVIATING
RETURNED
```

---

# 43. OVERSPEED

Overspeed should evaluate:

```text
vehicle speed
vs
configured speed limit
```

The speed limit should be configurable.

Do not hardcode:

```text
60
80
100
```

inside service logic.

---

# 44. LONG STOP DETECTION

This is currently missing and MUST be implemented.

Track:

```text
stationary_since
```

When:

```text
speed <= stationary threshold
```

for longer than:

```text
long_stop_duration_seconds
```

generate:

```text
LONG_STOP
```

Do not generate it while the vehicle is legitimately inside a configured stop/geofence if the product rules classify that as normal.

---

# 45. GPS OFFLINE ALERT

Implement actual telemetry freshness evaluation.

Example:

```text
last_location_at
        |
        v
age in seconds
        |
        +--> normal
        +--> stale
        +--> offline
```

Generate an alert when the vehicle remains offline beyond the configured threshold.

Avoid repeated alerts for the same outage.

---

# 46. DRIVER SOS

The driver app must eventually expose an SOS action during an active trip.

SOS request should include:

```text
trip_id
vehicle_id
current GPS
timestamp
emergency_type
notes
```

Server must verify driver/trip/vehicle authorization.

SOS should notify configured school/admin users.

---

# 47. BREAKDOWN / PROBLEM REPORTING

The existing `TripProblemReporting` widget is currently only a UI callback.

It is not a complete GPS/transport incident workflow.

Implement:

```text
report type
description
trip_id
vehicle_id
driver_id
GPS position
timestamp
```

Possible types:

```text
BREAKDOWN
ACCIDENT
TRAFFIC
MEDICAL
VEHICLE_PROBLEM
ROUTE_PROBLEM
OTHER
```

Server must persist the incident.

---

# 48. WEBSOCKET / REAL-TIME

Existing `GpsBroadcaster` must be reviewed.

A production WebSocket implementation requires:

```text
authentication
authorization
school isolation
channel/topic management
reconnect
heartbeat
stale detection
subscription lifecycle
```

Do not assume that opening a raw socket is enough.

If the project uses a WebSocket server package, integrate with that architecture.

---

# 49. PARENT REAL-TIME UPDATES

Preferred:

```text
Parent app
    |
    v
WebSocket
    |
    v
vehicle.location.updated
    |
    v
update bus marker
```

Fallback:

```text
poll every configurable interval
```

Polling must not become an uncontrolled infinite timer.

---

# 50. SCHOOL REAL-TIME UPDATES

School dashboard should update vehicle markers without requiring a full page reload.

Use the configured WebSocket/event mechanism.

Fallback polling may be used if the server deployment does not support WebSockets.

---

# 51. SCHOOL ISOLATION

Every GPS query MUST respect school tenancy.

A user from school A must never see:

```text
school B vehicles
school B trips
school B locations
school B students
```

This is a HARD SECURITY REQUIREMENT.

---

# 52. PARENT AUTHORIZATION

Parent GPS access MUST verify:

```text
authenticated parent
        |
        v
requested student
        |
        v
student belongs to parent
        |
        v
student transport assignment
        |
        v
assigned route/trip
```

Do not allow:

```text
parent A -> student B
```

by changing a `student_id` parameter.

The existing `ParentGpsApiController` already performs an important parent-child check. Preserve and extend it.

---

# 53. DRIVER AUTHORIZATION

Every driver GPS endpoint must verify:

```text
Auth user
driver record
active trip
trip driver
trip vehicle
school
```

Do not trust:

```text
vehicle_id
trip_id
driver_id
school_id
```

from the mobile client without verification.

---

# 54. API RATE LIMITING

GPS upload endpoints must be rate-limited.

Prevent:

```text
1,000 requests/second from one device
```

from overwhelming the server.

Rate limits should allow legitimate:

```text
5–10 second
```

GPS uploads.

Batch upload is preferred.

---

# 55. TRANSACTION SAFETY

When processing a GPS point, avoid partial state updates.

For business-critical operations:

```text
validate
  |
  v
persist location
  |
  v
update current state
  |
  v
process tracking events
```

Use transactions where required.

Do not wrap expensive external notification operations unnecessarily inside a long DB transaction.

---

# 56. ERROR HANDLING

Never return raw exceptions to mobile users in production.

Do not expose:

```text
SQL errors
stack traces
filesystem paths
internal service names
credentials
```

Use structured errors:

```json
{
  "error": true,
  "code": "GPS_UPLOAD_FAILED",
  "message": "Unable to process location."
}
```

---

# 57. LOGGING

Log:

```text
trip_id
vehicle_id
driver_id
event type
GPS status
processing time
validation result
```

Do NOT log:

```text
JWT
password
API secrets
full authentication headers
```

Avoid logging every GPS coordinate at high volume unless explicitly required.

---

# 58. CONFIGURATION

GPS configuration must live in configuration files/env variables.

Do not hardcode business thresholds.

At minimum configure:

```text
location interval moving
location interval stationary
distance filter
stale threshold
offline threshold
geofence radius
approach distance
route deviation threshold
overspeed threshold
long stop threshold
maximum queue size
upload batch size
retry configuration
```

---

# 59. DRIVER GPS HEALTH

Driver screen should show a compact GPS state:

```text
GPS: Connected
GPS: Weak
GPS: Offline
GPS: Permission Required
GPS: Mock Location Detected
```

This is critical because the driver must know whether tracking is actually working.

---

# 60. TRIP START GPS VALIDATION

When starting a trip:

1. Check location service enabled.
2. Check permission.
3. Check background permission if required.
4. Check GPS availability.
5. Start GPS.
6. Confirm first valid location.
7. Start upload.
8. Display GPS state.

Do not display:

```text
GPS Tracking Active
```

before the service actually starts.

---

# 61. FIRST LOCATION TIMEOUT

After starting tracking, wait for a valid location up to a configured timeout.

If no valid location arrives:

```text
GPS initialization failed
```

must be shown.

Do not silently continue as though tracking is active.

---

# 62. BATTERY OPTIMIZATION

Android devices may kill background services.

Provide an appropriate user flow for battery optimization restrictions if required by the selected GPS library.

Do not force unsafe device settings.

Clearly explain why background location is needed.

---

# 63. APP RESTART RECOVERY

If the driver application is killed/restarted during an active trip:

1. Authenticate user.
2. Query active trip.
3. Query GPS session.
4. Restore tracking state.
5. Resume GPS if authorized.
6. Resume queued uploads.

Do not create duplicate trips.

---

# 64. DEVICE REBOOT

If the product requires tracking to survive device reboot, implement the platform-specific boot/start behavior only where legally/platform-appropriate and only for an active trip.

Do not automatically track outside an active trip.

---

# 65. PRIVACY

GPS tracking MUST be limited to legitimate transport/trip operation.

Expected behavior:

```text
No active trip -> no continuous driver tracking
Active trip -> GPS tracking
Trip ended -> GPS tracking stops
```

Do not track drivers continuously when they are not operating a school trip unless the product explicitly requires it and the appropriate consent/legal requirements are handled.

---

# 66. PARENT DATA MINIMIZATION

Parent response should expose only the information required for transport tracking.

Do not expose:

```text
other students
other parents
unrelated vehicles
internal database IDs unless needed
sensitive driver information
```

---

# 67. DATABASE INDEXING

Inspect existing GPS migrations and add indexes for high-volume queries.

Important candidate indexes:

```text
vehicle_locations:
    trip_id
    vehicle_id
    timestamp
    (trip_id, timestamp)

vehicle_current_states:
    vehicle_id
    trip_id
    last_location_at

gps_sessions:
    trip_id
    vehicle_id
    driver_id
    status

gps_alerts:
    trip_id
    vehicle_id
    created_at
    status
```

Do not add duplicate indexes.

Inspect the actual schema first.

---

# 68. RETENTION

GPS history can become very large.

Implement a configurable retention strategy.

Example:

```text
raw GPS points: configurable number of days
trip summaries: long-term
alerts: long-term/configurable
```

Do not delete data blindly.

Use scheduled cleanup where the project's scheduler architecture supports it.

---

# 69. GPS TEST SUITE

Create automated tests.

Minimum backend tests:

## Validation

```text
valid coordinate
invalid latitude
invalid longitude
invalid accuracy
invalid speed
invalid heading
future timestamp
old timestamp
0,0
```

## Authorization

```text
driver cannot upload for another driver
driver cannot upload another vehicle
driver cannot upload another trip
parent cannot access another student
school A cannot access school B
```

## Tracking

```text
valid location stored
current state updated
duplicate point handled
out-of-order point handled
invalid point rejected
```

## Geofence

```text
outside -> approaching
approaching -> inside
inside -> exited
duplicate event not generated
```

## Route deviation

```text
on route
near route
off route
return to route
```

## Overspeed

```text
below limit
above limit
repeated overspeed does not spam alerts
```

## Long stop

```text
short stop
long stop
normal stop at bus stop
duplicate long-stop alert prevention
```

## Offline

```text
stale location
offline transition
recovery
```

---

# 70. FLUTTER TESTS

Create tests for:

```text
GPS permission flow
GPS service state
start tracking
stop tracking
offline queue
retry
batch upload
trip start integration
trip end integration
app restart recovery
```

Mock platform location data.

Do not depend on real GPS hardware in unit tests.

---

# 71. INTEGRATION TEST

Create an end-to-end test flow:

```text
Driver login
    ↓
Driver starts trip
    ↓
GPS permission
    ↓
GPS service starts
    ↓
location generated
    ↓
location uploaded
    ↓
server validates
    ↓
VehicleCurrentState updated
    ↓
parent API returns location
    ↓
school API returns location
    ↓
trip ends
    ↓
GPS stops
```

This is the most important integration test.

---

# 72. STATIC CODE AUDIT

Search the entire repository for:

```text
shuffle(
rand(
mt_rand(
random_
```

inside GPS/tracking decision logic.

GPS state processing must be deterministic.

Also search for:

```text
env(
```

inside runtime controllers/services.

Use config values instead.

---

# 73. DUPLICATE SYSTEM AUDIT

Search for duplicate GPS implementations.

The final repository should have one authoritative:

```text
GPS tracking domain
```

Do not create:

```text
OldGpsService
NewGpsService
LiveGpsService2
GpsTrackerFinal
```

Avoid duplicated logic.

---

# 74. API COMPATIBILITY

Existing transport APIs must continue to work unless explicitly deprecated.

Do not break:

```text
driver-helpr/get-trips
driver-helpr/trip/start-end
transport/dashboard
transportation/live-route
transport/routes/stops
```

During migration, adapters may be used.

The new GPS APIs should be introduced cleanly.

---

# 75. MIGRATION STRATEGY

Do NOT replace the old transport system in one destructive step.

Recommended sequence:

```text
Existing transport
        |
        +--> GPS session integration
        |
        +--> GPS location upload
        |
        +--> live state
        |
        +--> parent consumption
        |
        +--> school consumption
        |
        +--> legacy live-route compatibility
```

Once validated, legacy APIs can be deprecated later.

---

# 76. GPS SERVICE CLASS DESIGN

Use clear responsibilities.

Example:

```text
GpsTrackingService
    -> collects GPS

GpsPermissionService
    -> permissions

GpsUploadService
    -> HTTP upload

GpsOfflineQueue
    -> persistence

GpsSessionManager
    -> session lifecycle

LocationGateway
    -> backend source abstraction

LocationValidator
    -> validation

TrackingEngine
    -> business processing

GeofenceService
    -> geofence state

ETAService
    -> ETA

RouteDeviationService
    -> route deviation

AlertService
    -> safety alerts

GpsNotificationService
    -> notifications

GpsBroadcaster
    -> realtime events
```

Do not combine all responsibilities into one class.

---

# 77. NO BUSINESS LOGIC IN UI

Flutter widgets should NOT calculate:

```text
ETA
route deviation
geofence
overspeed
trip authorization
```

Widgets display state.

Cubits/repositories/services handle application behavior.

Laravel handles domain/business rules.

---

# 78. GPS STATE MACHINE

Implement explicit driver GPS states:

```text
IDLE
PERMISSION_REQUIRED
STARTING
WAITING_FOR_FIRST_FIX
TRACKING
OFFLINE
STALE
ERROR
STOPPING
STOPPED
```

Transitions must be deterministic.

Invalid transitions should be ignored or logged safely.

---

# 79. NETWORK STATE MACHINE

Handle:

```text
ONLINE
OFFLINE
RECOVERING
SYNCING
ONLINE
```

Do not create multiple simultaneous upload loops.

There must be exactly one active synchronization worker per trip.

---

# 80. CONCURRENCY

Prevent:

```text
two GPS listeners
two upload loops
two retry workers
two active sessions
```

when the app rebuilds/restarts widgets.

Use singleton/service lifecycle management consistent with the existing Flutter architecture.

---

# 81. MEMORY MANAGEMENT

GPS streams must be disposed.

Timers must be cancelled.

Subscriptions must be cancelled.

Network workers must stop.

Do not leak:

```text
StreamSubscription
Timer
LocationSubscription
WebSocket
```

---

# 82. BATTERY MANAGEMENT

Avoid excessive GPS usage.

Do not:

```text
request high accuracy every 1 second
```

unless explicitly required.

Use:

```text
distance filter
time interval
accuracy policy
```

to balance tracking quality and battery.

---

# 83. SECURITY — CLIENT

Do not store:

```text
JWT
GPS history
trip secrets
```

in insecure plain files.

Use the existing secure authentication mechanism.

Follow the application's existing token storage strategy.

---

# 84. SECURITY — SERVER

GPS APIs must use authentication middleware.

Add rate limiting.

Validate request size.

Validate payload count for batch uploads.

Reject excessively large batches.

---

# 85. SECURITY — REPLAY PROTECTION

If the same client submits an old GPS packet repeatedly, it should not continually alter current state.

Use timestamp/order/idempotency checks.

---

# 86. SCHOOL TIMEZONE

Use the application's configured school timezone.

Do not compare timestamps using mixed local/server timezone assumptions.

Persist timestamps consistently.

Convert only at presentation boundaries.

---

# 87. CLOCK SKEW

Mobile device clocks may be wrong.

Do not reject every slightly future timestamp.

Use configurable clock skew tolerance.

Example:

```text
MAX_CLOCK_SKEW_SECONDS
```

---

# 88. GPS ACCURACY POLICY

A point with extremely poor accuracy should not immediately become the authoritative vehicle state.

Example policy:

```text
good accuracy -> accept
poor accuracy -> mark degraded
extreme accuracy -> reject or store as low-confidence
```

Use configuration.

---

# 89. CURRENT STATE UPDATE RULE

Only update:

```text
VehicleCurrentState
```

with a point that passes the configured validation policy.

Historical storage may retain rejected/suspicious points if required for auditing, but business state must remain trustworthy.

---

# 90. ETA FALLBACK

If live GPS is unavailable:

```text
ETA = unavailable
```

or use a documented fallback.

Do not show a stale ETA as current.

---

# 91. PARENT REFRESH

The existing parent live route screen has a manual refresh.

Keep manual refresh.

Also support automatic refresh or WebSocket updates.

The refresh must not create duplicate requests.

---

# 92. MAP MARKER UPDATE

When new coordinates arrive:

```text
old marker
    |
    v
new marker
```

Prefer smooth marker animation where practical.

Do not rebuild the entire screen for every coordinate.

---

# 93. CURRENT STOP

The backend should determine the current/next stop.

Do not calculate the current stop independently in multiple Flutter screens.

There must be one authoritative interpretation.

---

# 94. DRIVER TRIP SCREEN

Add a GPS health indicator to the active trip UI.

Example:

```text
● GPS Connected
Last update: 5 sec ago
```

When offline:

```text
● GPS Offline
Queued points: 18
```

This makes operational problems visible immediately.

---

# 95. START TRIP USER EXPERIENCE

Expected flow:

```text
Tap Start Trip
      |
      v
Select pickup/drop
      |
      v
Backend starts trip
      |
      v
Request GPS permission if needed
      |
      v
Start GPS
      |
      v
Get first valid fix
      |
      v
Show "GPS Tracking Active"
```

If GPS fails:

```text
GPS Tracking Failed
Reason: Permission denied / GPS disabled / timeout
```

---

# 96. STOP TRIP USER EXPERIENCE

Expected:

```text
Complete Trip
      |
      v
backend confirms completion
      |
      v
flush GPS queue
      |
      v
stop GPS
      |
      v
show completed
```

---

# 97. ERROR RECOVERY

If GPS upload fails:

```text
do not stop GPS collection
```

Instead:

```text
collect
queue
retry
```

If GPS hardware/location fails:

```text
show warning
retry location acquisition
```

Do not crash the trip screen.

---

# 98. SERVER GPS PROCESSING PERFORMANCE

Avoid expensive work synchronously for every point if unnecessary.

For example:

```text
store location
update current state
```

can be immediate.

More expensive:

```text
notifications
large route calculations
analytics
```

may be queued if the existing infrastructure supports queues.

Do not introduce queues blindly; inspect current Laravel queue configuration first.

---

# 99. OBSERVABILITY

Record metrics such as:

```text
GPS points received
GPS points rejected
GPS validation failures
average processing time
active GPS sessions
offline sessions
upload failures
queue size
```

Do not log every coordinate indefinitely.

---

# 100. ADMIN GPS HEALTH

School dashboard should eventually show:

```text
Total active vehicles
GPS online
GPS stale
GPS offline
Active trips
Vehicles deviating
Overspeed alerts
Emergency alerts
```

This is an operational dashboard, not just a map.

---

# 101. DEFINITION OF DONE

The GPS implementation is NOT complete until ALL of the following are true.

## Driver

- [ ] GPS permission flow works.
- [ ] Background location works.
- [ ] GPS starts after successful trip start.
- [ ] GPS stops after successful trip end.
- [ ] GPS continues while app is backgrounded.
- [ ] GPS works with screen locked where platform permits.
- [ ] Location upload works.
- [ ] Offline queue works.
- [ ] Retry works.
- [ ] Batch sync works.
- [ ] GPS health status is visible.
- [ ] Mock location is detected/flagged.
- [ ] App restart recovery works.
- [ ] No duplicate tracking workers.

## Backend

- [ ] Driver authorization verified.
- [ ] Trip authorization verified.
- [ ] Vehicle authorization verified.
- [ ] School isolation verified.
- [ ] Location validation complete.
- [ ] Current state updates correctly.
- [ ] Historical locations stored.
- [ ] Geofence state machine works.
- [ ] ETA works.
- [ ] Route deviation uses point-to-segment distance.
- [ ] Overspeed alert works.
- [ ] Long-stop alert works.
- [ ] GPS-offline alert works.
- [ ] Notifications are deduplicated.
- [ ] SOS works.
- [ ] Incidents persist.
- [ ] WebSocket/realtime works or documented polling fallback works.

## Parent

- [ ] New GPS backend is consumed.
- [ ] Live map works.
- [ ] Bus marker updates.
- [ ] Child stop shown.
- [ ] ETA shown.
- [ ] GPS freshness shown.
- [ ] Manual refresh works.
- [ ] Automatic updates work.
- [ ] Parent authorization works.

## School

- [ ] Fleet live map works.
- [ ] Vehicle filters work.
- [ ] Route filters work.
- [ ] Trip telemetry works.
- [ ] Trip replay works.
- [ ] GPS health works.
- [ ] Alerts work.
- [ ] History works.

## Testing

- [ ] Backend unit tests.
- [ ] Backend feature tests.
- [ ] Authorization tests.
- [ ] Geofence tests.
- [ ] Route deviation tests.
- [ ] Alert tests.
- [ ] Flutter unit tests.
- [ ] Flutter service tests.
- [ ] GPS queue tests.
- [ ] Integration test.
- [ ] Static analysis passes.
- [ ] Flutter analyzer passes.
- [ ] PHP syntax passes.
- [ ] Existing transport tests remain green.

---

# 102. AGENT EXECUTION RULES

The AI coding agent MUST follow these rules.

## Rule 1 — Inspect before editing

Never assume a file does not exist.

Search first.

## Rule 2 — Preserve existing functionality

Do not rewrite working transport modules unnecessarily.

## Rule 3 — No duplicate systems

Extend the existing GPS addon.

## Rule 4 — No fake completion

Do not mark a feature complete until it is actually implemented and tested.

## Rule 5 — No placeholder implementations

Do not leave:

```text
TODO
FIXME
return true
return []
mock GPS
fake coordinates
fake ETA
```

in production GPS logic.

## Rule 6 — No random behavior

GPS processing must be deterministic.

## Rule 7 — Server is authoritative

Never trust the mobile client for:

```text
driver identity
vehicle identity
trip authorization
school identity
```

## Rule 8 — Test every critical path

A GPS feature is not done merely because it compiles.

## Rule 9 — Do not break legacy APIs

Use compatibility/adapters where necessary.

## Rule 10 — Verify after every major phase

Run:

```text
PHP syntax/static checks
Laravel tests
Flutter analyze
Flutter tests
```

where supported by the repository.

---

# 103. IMPLEMENTATION ORDER

The agent MUST implement in this order.

## PHASE 1 — AUDIT

1. Inspect GPS addon.
2. Inspect all GPS routes.
3. Inspect all GPS models.
4. Inspect migrations.
5. Inspect driver Flutter transport flow.
6. Inspect parent Flutter live tracking.
7. Inspect school GPS UI.
8. Inspect dependencies.
9. Identify exact gaps.

Do not edit during the audit unless a blocking compile issue must be corrected.

---

## PHASE 2 — DRIVER GPS FOUNDATION

Implement:

```text
GPS dependency
permissions
GPS service
GPS state
location model
background tracking
```

Then test independently.

---

## PHASE 3 — UPLOAD PIPELINE

Implement:

```text
GPS upload
offline queue
batch upload
retry
idempotency
```

Then test with mocked GPS points.

---

## PHASE 4 — TRIP INTEGRATION

Connect:

```text
start trip
    -> start GPS

end trip
    -> flush queue
    -> stop GPS
```

Test the complete flow.

---

## PHASE 5 — BACKEND HARDENING

Implement:

```text
authorization
rate limiting
validation
school isolation
clock skew
duplicate handling
```

---

## PHASE 6 — TRACKING LOGIC

Complete:

```text
geofence
ETA
route deviation
overspeed
long stop
offline alert
```

---

## PHASE 7 — PARENT APP

Replace/bridge old live-route data with the new GPS source.

Implement:

```text
map
vehicle marker
ETA
GPS freshness
realtime updates
```

---

## PHASE 8 — SCHOOL DASHBOARD

Complete:

```text
live fleet
vehicle telemetry
alerts
GPS health
trip replay
```

---

## PHASE 9 — REALTIME

Complete:

```text
WebSocket authentication
channels
parent subscriptions
school subscriptions
reconnect
fallback polling
```

---

## PHASE 10 — TESTING

Run all tests.

Fix all failures.

Then run the full application build/analyzer checks.

---

# 104. FINAL VERIFICATION COMMANDS

Use the repository's actual configured commands.

At minimum attempt:

```bash
php -l
```

for changed PHP files.

Run:

```bash
composer test
```

or the project's configured Laravel/PHP test command.

For Flutter:

```bash
flutter analyze
flutter test
```

and build the relevant applications if the environment supports it.

Do not report success if these checks fail.

---

# 105. FINAL AGENT REPORT

After implementation, the agent MUST report:

```text
GPS IMPLEMENTATION STATUS

Driver GPS:
PASS/FAIL

Background GPS:
PASS/FAIL

Offline Queue:
PASS/FAIL

GPS Upload:
PASS/FAIL

Trip Integration:
PASS/FAIL

Backend Validation:
PASS/FAIL

Authorization:
PASS/FAIL

Geofence:
PASS/FAIL

ETA:
PASS/FAIL

Route Deviation:
PASS/FAIL

Overspeed:
PASS/FAIL

Long Stop:
PASS/FAIL

GPS Offline:
PASS/FAIL

Parent Live Tracking:
PASS/FAIL

School Live Tracking:
PASS/FAIL

WebSocket:
PASS/FAIL

SOS:
PASS/FAIL

Trip Replay:
PASS/FAIL

Automated Tests:
PASS/FAIL

Flutter Analyze:
PASS/FAIL

PHP/Laravel Tests:
PASS/FAIL
```

Then list:

```text
Files created
Files modified
Database migrations added
API endpoints added/changed
Tests added
Known limitations
```

Do NOT claim 100% completion if any required item remains unfinished.

---

# 106. MOST IMPORTANT SUCCESS CRITERIA

The final real-world flow MUST work:

```text
DRIVER

Login
  ↓
Assigned trip
  ↓
Start Trip
  ↓
GPS permission
  ↓
GPS service starts
  ↓
First location obtained
  ↓
GPS status = CONNECTED
  ↓
Driver starts moving
  ↓
GPS points every configured interval
  ↓
Upload to backend
  ↓
Backend validates
  ↓
VehicleCurrentState updated
  ↓
Geofence/ETA/alerts processed
  ↓
Parent sees bus
  ↓
School sees bus
  ↓
Network disconnects
  ↓
GPS points stored locally
  ↓
Network returns
  ↓
Queue synchronizes
  ↓
Trip ends
  ↓
Final GPS queue flushed
  ↓
GPS stops
  ↓
Session closed
```

If this flow does not work end-to-end, the GPS integration is NOT complete.

---

# 107. IMPORTANT CURRENT REPOSITORY GAPS TO FIX FIRST

The agent should prioritize these exact gaps:

1. **Driver Flutter GPS collection is missing.**
2. **Driver Flutter background GPS is missing.**
3. **Driver Flutter GPS upload is missing.**
4. **Driver offline GPS queue is missing.**
5. **Trip start is not connected to the new GPS session.**
6. **Trip completion is not connected to GPS shutdown.**
7. **Parent Flutter still relies on the older live-route API instead of the new GPS API.**
8. **Parent live map is not fully connected to the new GPS telemetry.**
9. **WebSocket client integration is incomplete.**
10. **Route deviation must use point-to-segment distance.**
11. **Long-stop alert is missing.**
12. **GPS-offline alert is incomplete.**
13. **Driver/trip/vehicle authorization must be hardened.**
14. **GPS-specific automated tests are missing.**
15. **Android driver location permissions are missing.**
16. **Background location configuration is incomplete.**
17. **iOS driver background location configuration must be verified against the actual GPS implementation.**

These are the highest-priority implementation items.

---

# 108. FINAL INSTRUCTION TO THE AI AGENT

Implement the GPS system completely.

Do not merely create files.

Do not merely create APIs.

Do not merely make the UI look complete.

The GPS system must actually operate from:

```text
physical driver device
        ↓
real GPS
        ↓
background service
        ↓
network/offline queue
        ↓
Laravel backend
        ↓
validated vehicle state
        ↓
geofence/ETA/alerts
        ↓
parent
        ↓
school
```

Every hard requirement must be implemented.

Every critical path must be tested.

Existing transport functionality must remain operational.

Any architectural conflict must be resolved in favor of a single authoritative GPS domain.

The final implementation must be deterministic, secure, tenant-isolated, battery-conscious, fault-tolerant, and observable.

**Do not declare completion until the end-to-end driver → server → parent/school GPS flow has been verified.**
