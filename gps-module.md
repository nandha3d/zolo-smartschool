import pypandoc, os, textwrap

md = r"""# School Management System — Mobile GPS Transport Module
## Phase 1 Technical & Product Specification

**Document Version:** 1.0  
**GPS Strategy:** Mobile-phone GPS first  
**Future Strategy:** Dedicated vehicle GPS devices  
**Platforms:** School Web Portal + Driver Mobile App + Parent Mobile App

---

# 1. Purpose

This module provides complete school transportation tracking using the **driver's mobile phone GPS** as the initial location source.

The system must support:

- Real-time school bus tracking
- Driver trip management
- Parent live bus tracking
- Student pickup/drop tracking
- Route and stop management
- ETA
- Geofencing
- Route deviation detection
- Speed monitoring
- Trip history
- Trip replay
- Emergency/SOS
- Vehicle breakdown reporting
- GPS/offline handling
- Notifications
- Transport analytics
- School transport control center

The architecture must be designed so that a **dedicated GPS device can later replace or supplement the driver's phone without changing the parent, school, route, trip, attendance, or reporting layers.**

---

# 2. Phase 1 GPS Principle

## Current implementation

```text
Driver Mobile Phone
       |
       | GPS
       v
Driver Mobile App
       |
       | HTTPS / WebSocket
       v
GPS Tracking API
       |
       v
Tracking Engine
       |
       +---- Redis / Real-time State
       |
       +---- Database / History
       |
       +---- Event Engine
       |
       +---- ETA Engine
       |
       +---- Notification Engine
       |
       +---- WebSocket
              |
              +---- School Portal
              |
              +---- Parent App
```

The driver's mobile phone is the **GPS source** during Phase 1.

Do not make the database or business logic depend directly on "driver phone GPS". Instead, introduce a generic concept:

```text
Location Source
```

Phase 1:

```text
Location Source = MOBILE_APP
```

Future:

```text
Location Source = GPS_DEVICE
```

Potential future:

```text
Location Source = MOBILE_APP + GPS_DEVICE
```

---

# 3. Critical Architectural Rule

The transport system must not contain logic such as:

```text
if driver_phone_gps then ...
```

Instead:

```text
Location Provider
        |
        v
Location Gateway
        |
        v
Normalized Location
        |
        v
Tracking Engine
```

This makes the future device integration much easier.

---

# 4. User Roles

## 4.1 School Administrator

Full transport access:

- Vehicles
- Drivers
- Routes
- Stops
- Student assignments
- Trips
- Live tracking
- GPS status
- Alerts
- Emergency
- Reports
- Settings

## 4.2 Transport Manager

Transport-focused access:

- Live fleet
- Active trips
- Routes
- Drivers
- Vehicles
- Student transport
- GPS status
- Alerts
- Trip history
- Reports

## 4.3 Driver

Mobile application access:

- Assigned vehicle
- Assigned route
- Today's trips
- Start trip
- GPS tracking
- Stops
- Student attendance
- SOS
- Breakdown
- Trip completion

## 4.4 Parent

Mobile application access:

- Child transport status
- Assigned bus
- Live bus location
- ETA
- Pickup/drop stop
- Boarding status
- Notifications
- Transport history

## 4.5 Teacher / Staff

Optional restricted access:

- Assigned students
- Transport status
- Route information

---

# 5. Main Transport Architecture

```text
School
 |
 +-- Vehicles
 |
 +-- Drivers
 |
 +-- Routes
 |    |
 |    +-- Stops
 |    +-- Schedules
 |
 +-- Students
 |    |
 |    +-- Pickup Stop
 |    +-- Drop Stop
 |
 +-- Trips
 |
 +-- Attendance
 |
 +-- GPS Tracking
 |
 +-- Alerts
 |
 +-- Notifications
 |
 +-- Reports
```

---

# 6. Vehicle Module

Each vehicle must have:

```text
Vehicle
-----------------------------
id
school_id
vehicle_number
registration_number
vehicle_type
capacity
status
active
current_driver_id
current_route_id
location_source
gps_tracking_enabled
created_at
updated_at
```

Example:

```text
Vehicle:
BUS-24

Registration:
TN-38-AB-1234

Capacity:
40

Location Source:
MOBILE_APP

GPS:
ACTIVE
```

## Vehicle Status

```text
ACTIVE
INACTIVE
MAINTENANCE
OUT_OF_SERVICE
```

## Real-time Vehicle Status

This should be calculated dynamically:

```text
MOVING
STOPPED
OFFLINE
ON_TRIP
AT_SCHOOL
AT_DEPOT
EMERGENCY
```

---

# 7. Driver Module

Driver profile:

```text
drivers

id
school_id
user_id
employee_id
name
phone
license_number
license_expiry
status
photo
created_at
updated_at
```

Driver assignment:

```text
driver_vehicle_assignments

id
school_id
driver_id
vehicle_id
start_date
end_date
active
```

A driver should be able to drive different vehicles on different days.

Do not permanently couple a driver to one vehicle.

---

# 8. Mobile GPS Source

The driver app is the initial GPS tracker.

## Driver GPS Requirements

The app should:

- Request location permission
- Request background location permission where required
- Track GPS during active trips
- Send location periodically
- Continue tracking when the screen is locked
- Handle temporary network loss
- Queue unsent locations
- Sync when network returns
- Detect GPS permission changes
- Detect location services being disabled
- Show GPS status to driver

---

# 9. GPS Permission States

The driver app should distinguish:

```text
LOCATION_PERMISSION_GRANTED
LOCATION_PERMISSION_DENIED
LOCATION_PERMISSION_RESTRICTED
BACKGROUND_LOCATION_ALLOWED
BACKGROUND_LOCATION_NOT_ALLOWED
LOCATION_SERVICES_DISABLED
```

Before starting a trip:

```text
GPS Ready
Internet Ready
Location Permission Ready
```

If any critical requirement is missing:

```text
Cannot start tracking

Please enable:
Background Location
```

---

# 10. Mobile Location Update Strategy

Do not continuously send GPS data every second.

Use configurable tracking intervals.

Example:

### Vehicle moving

```text
Location update:
Every 5–10 seconds
```

### Vehicle stopped

```text
Location update:
Every 20–30 seconds
```

### Poor network

```text
Store locally
Sync later
```

The exact interval should be configurable.

Example configuration:

```text
moving_interval_seconds = 10
stopped_interval_seconds = 30
offline_sync_batch_size = 50
```

---

# 11. Location Data

Every received location should be normalized into:

```text
vehicle_locations

id
school_id
vehicle_id
driver_id
trip_id
source_type
source_id
latitude
longitude
accuracy
altitude
speed
heading
timestamp
received_at
battery_level
is_mock_location
network_type
created_at
```

## source_type

Phase 1:

```text
MOBILE_APP
```

Future:

```text
GPS_DEVICE
```

Potential:

```text
MOBILE_APP
GPS_DEVICE
```

---

# 12. GPS Data Validation

Never blindly accept mobile GPS coordinates.

Validate:

- Latitude range
- Longitude range
- Timestamp
- Accuracy
- Speed
- Heading
- Duplicate location
- Unrealistic movement
- Stale GPS timestamp

Example:

```text
Previous:
Lat A
Lng A
Time 08:10

New:
Lat B
Lng B
Time 08:10:05
```

If the implied speed is impossible, mark the point as suspicious.

Do not necessarily delete it immediately. Store an event/quality flag.

---

# 13. Mock GPS Detection

The mobile app should attempt to identify mock/fake location conditions where the operating system provides the relevant information.

Store:

```text
is_mock_location
```

If suspicious:

```text
GPS Integrity Warning
```

The system should not treat this as absolute proof of misconduct because mobile OS capabilities and device behavior vary.

---

# 14. Trip Architecture

A GPS session must be associated with a **Trip**.

```text
Trip
 |
 +-- School
 +-- Vehicle
 +-- Driver
 +-- Route
 +-- Schedule
 +-- Students
 +-- GPS Session
 +-- Stops
 +-- Attendance
 +-- Events
```

Example:

```text
Trip #TRP-20260929-001

Type:
MORNING

Vehicle:
BUS-24

Driver:
Ramesh

Route:
R-12

Start:
07:00

Status:
ACTIVE
```

---

# 15. Trip States

```text
SCHEDULED
READY
STARTING
ACTIVE
PAUSED
COMPLETED
CANCELLED
ABORTED
EMERGENCY
```

Recommended normal flow:

```text
SCHEDULED
    |
    v
READY
    |
    v
ACTIVE
    |
    v
COMPLETED
```

---

# 16. Start Trip Workflow

Driver opens the app.

```text
Today's Trips
       |
       v
Select Trip
       |
       v
Vehicle Check
       |
       +-- Vehicle assigned
       +-- Route assigned
       +-- GPS ready
       +-- Location permission
       +-- Internet available
       |
       v
START TRIP
       |
       v
GPS Tracking Active
```

Before allowing trip start, validate critical conditions.

---

# 17. Driver Trip Dashboard

Example:

```text
--------------------------------
MORNING PICKUP
--------------------------------

Bus:
BUS-24

Route:
R-12

GPS:
● Connected

Internet:
● Connected

Students:
18 / 32

Next Stop:
Gandhipuram

ETA:
07:48 AM

--------------------------------

[ VIEW ROUTE ]

[ STUDENT ATTENDANCE ]

[ SOS ]

[ BREAKDOWN ]

[ END TRIP ]
--------------------------------
```

The driver UI must remain extremely simple while driving.

Avoid unnecessary interactions.

---

# 18. Driver Background Tracking

Once the trip becomes ACTIVE:

```text
Driver App
     |
     +-- Start GPS tracking
     |
     +-- Background location service
     |
     +-- Local queue
     |
     +-- Upload service
```

Tracking must continue when:

- Screen is locked
- App is minimized
- Driver changes apps

Subject to Android/iOS background-location rules and permissions.

The application must clearly communicate why background location is required.

---

# 19. Network Offline Mode

If mobile internet disappears:

```text
GPS
 |
 v
Local Storage
 |
 v
Queue
```

Example:

```text
Network:
OFFLINE

GPS:
ACTIVE

Pending:
23 locations
```

When network returns:

```text
OFFLINE
   |
   v
NETWORK RESTORED
   |
   v
SYNC QUEUE
   |
   v
SERVER
```

Do not lose the trip because of temporary network failure.

---

# 20. Real-Time Tracking

After receiving GPS:

```text
Mobile GPS
    |
    v
Tracking API
    |
    v
Location Validation
    |
    v
Tracking Engine
    |
    +--> Redis current position
    |
    +--> Database history
    |
    +--> Geofence engine
    |
    +--> Route engine
    |
    +--> ETA engine
    |
    +--> Alert engine
    |
    +--> WebSocket
```

---

# 21. Current Vehicle State

Maintain a fast-access current state.

Example:

```text
vehicle_current_state

vehicle_id
trip_id
driver_id
latitude
longitude
speed
heading
status
next_stop_id
eta
last_location_at
gps_status
updated_at
```

Redis is recommended for this real-time state.

The historical database should not be queried every time a parent opens the map.

---

# 22. WebSocket / Real-Time Updates

Parent and school portals should receive real-time updates.

Example:

```text
vehicle.24.location.updated
```

Payload:

```json
{
  "vehicle_id": 24,
  "latitude": 11.0168,
  "longitude": 76.9558,
  "speed": 32,
  "heading": 90,
  "status": "MOVING",
  "eta": "08:12"
}
```

The client should update the marker without refreshing the page.

---

# 23. Route Module

Route:

```text
routes

id
school_id
name
code
direction
active
```

Route stops:

```text
route_stops

id
route_id
stop_id
sequence
planned_arrival_time
planned_departure_time
pickup_enabled
drop_enabled
```

---

# 24. Stop Module

Stop:

```text
stops

id
school_id
name
latitude
longitude
geofence_radius
active
```

Example:

```text
Gandhipuram Stop

Latitude:
11.xxxxxx

Longitude:
76.xxxxxx

Radius:
100m
```

---

# 25. Route Builder

School admin can create routes on a map.

Features:

- Add route
- Add stop
- Move stop
- Delete stop
- Reorder stop
- Set stop radius
- Set expected arrival
- Set expected departure
- Assign students
- Assign vehicle
- Assign driver

---

# 26. Route Polyline

Store the planned route geometry.

```text
route_geometry
```

The map should display:

```text
Planned Route
──────────────

Actual Vehicle
       🚌
```

This is required for route deviation detection.

---

# 27. Geofencing

Phase 1 should support:

### Stop geofences

```text
Stop
  ●
 (100m)
```

### School geofence

```text
School
+----------------+
|                |
|       ●        |
|                |
+----------------+
```

### Optional custom geofences

- Depot
- Parking area
- Restricted area

---

# 28. Geofence Events

Generate events:

```text
GEOFENCE_ENTER
GEOFENCE_EXIT
STOP_ARRIVED
STOP_DEPARTED
SCHOOL_ENTERED
SCHOOL_EXITED
```

Example:

```text
Bus
 |
 | enters 100m stop radius
 v
STOP_ARRIVED
 |
 v
Parent notification
```

---

# 29. Stop State Machine

Each stop can have:

```text
UPCOMING
APPROACHING
ARRIVED
WAITING
DEPARTED
SKIPPED
```

Example:

```text
500m
 |
 v
APPROACHING
 |
 v
100m
 |
 v
ARRIVED
 |
 v
Students handled
 |
 v
DEPARTED
```

---

# 30. Student Transport Assignment

Each student can have independent morning/evening assignments.

```text
student_transport_assignments

id
school_id
student_id
trip_type
route_id
stop_id
active_from
active_to
active
```

Example:

```text
Student:
Arjun

Morning:
Route R-12
Pickup: Stop 4

Evening:
Route R-15
Drop: Stop 7
```

---

# 31. Student Boarding

Driver can see students expected at each stop.

Example:

```text
STOP 4 — GANDHIPURAM

Expected Pickup: 4

[✓] Arjun
[✓] Priya
[ ] Rahul
[✓] Sanjay
```

Statuses:

```text
EXPECTED
BOARDED
ABSENT
NOT_BOARDED
DROPPED
```

---

# 32. Boarding Event

Every boarding action should create an immutable event.

```text
boarding_events

id
school_id
trip_id
vehicle_id
driver_id
student_id
stop_id
event_type
latitude
longitude
accuracy
timestamp
created_at
```

Examples:

```text
BOARDING
DROPPED
ABSENT
MANUAL_OVERRIDE
```

---

# 33. Parent Notification Flow

Example:

```text
Student Boards
      |
      v
Attendance Event
      |
      v
Notification Engine
      |
      +---- Push
      |
      +---- WhatsApp
      |
      +---- SMS
```

Example notification:

```text
Arjun has boarded the school bus.

Bus:
BUS-24

Time:
08:12 AM
```

---

# 34. Parent Tracking Portal

Parent home:

```text
--------------------------------
Arjun
--------------------------------

BUS-24

● ON THE WAY

ETA:
08:12 AM

Next Stop:
Gandhipuram

[ TRACK BUS ]

--------------------------------
Driver
Ramesh

Route
R-12
--------------------------------
```

---

# 35. Parent Live Map

Show only authorized information:

- Child's assigned vehicle
- Vehicle location
- Assigned route
- Relevant stops
- Child pickup/drop location
- ETA
- Trip status

Do not expose:

- Other students
- Other parents
- Unrelated vehicles
- Driver personal information
- Historical vehicle data outside the permitted context

---

# 36. Multiple Children

Parent may have multiple children.

Example:

```text
MY CHILDREN

Arjun
BUS-24
● On the way

Ananya
BUS-31
● Arrived at school
```

Each child's transport authorization must be evaluated independently.

---

# 37. ETA Engine

Initial ETA can use:

```text
Remaining route distance
+
Current/estimated travel speed
+
Expected stop time
```

Later improve with:

```text
Traffic
Historical trip duration
Time of day
Day of week
Stop dwell time
```

Architecture:

```text
GPS
 |
 v
ETA Engine
 |
 +-- Next Stop ETA
 +-- Child Stop ETA
 +-- School ETA
```

---

# 38. Delay Detection

Compare:

```text
Actual arrival
vs
Planned arrival
```

Example:

```text
Expected:
08:10

Actual:
08:15

Delay:
+5 minutes
```

Configurable delay threshold:

```text
delay_warning_minutes = 5
```

---

# 39. Route Deviation

Compare actual GPS against the planned route geometry.

Example:

```text
Planned:
───────────────

Actual:
             \
              \
```

Configuration:

```text
route_deviation_threshold_meters = 300
route_deviation_duration_seconds = 60
```

Do not trigger on a single inaccurate GPS point.

Require deviation to persist for a configurable period.

---

# 40. Speed Monitoring

Use GPS speed when reliable.

Optional fallback:

```text
distance / time
```

Store:

```text
speed
speed_limit
speed_event
```

Example:

```text
Speed limit:
40 km/h

Actual:
48 km/h

Duration:
35 seconds

Event:
OVERSPEED
```

---

# 41. Important Mobile GPS Limitation

GPS-based speed and location can be noisy.

Do not treat every GPS point as exact.

Use:

- Accuracy threshold
- Smoothing
- Duplicate detection
- Outlier filtering
- Time validation
- Movement validation

The system should display approximate locations where appropriate.

---

# 42. Driver Safety Events

Phase 1 can support:

```text
OVERSPEED
ROUTE_DEVIATION
LONG_STOP
GPS_DISABLED
LOCATION_PERMISSION_LOST
NETWORK_OFFLINE
MOCK_LOCATION_WARNING
```

Future hardware can add:

```text
HARSH_BRAKING
HARSH_ACCELERATION
IGNITION
ENGINE_STATUS
ACCIDENT_SENSOR
```

These hardware-dependent events should not be designed as mandatory Phase 1 features.

---

# 43. SOS / Emergency

Driver gets:

```text
+---------------------+
|                     |
|      🚨 SOS         |
|                     |
+---------------------+
```

On confirmation:

```text
SOS
 |
 +-- Current GPS
 +-- Vehicle
 +-- Driver
 +-- Trip
 +-- Time
 +-- Student count
 |
 v
Emergency Center
```

---

# 44. Emergency Types

```text
ACCIDENT
MEDICAL_EMERGENCY
VEHICLE_BREAKDOWN
STUDENT_INCIDENT
SECURITY_INCIDENT
OTHER
```

Optional:

- Photo
- Description
- Voice note
- Emergency call

---

# 45. Emergency Center

School web portal:

```text
-----------------------------------------
🚨 ACTIVE EMERGENCY
-----------------------------------------

Vehicle:
BUS-24

Driver:
Ramesh

Route:
R-12

Students:
24

Location:
Live GPS

Time:
08:24 AM

-----------------------------------------

[ CONTACT DRIVER ]
[ CALL EMERGENCY CONTACT ]
[ NOTIFY PARENTS ]
[ ASSIGN REPLACEMENT BUS ]
-----------------------------------------
```

Emergency notification behavior must be configurable by the school.

---

# 46. Breakdown Workflow

Driver:

```text
BREAKDOWN
   |
   v
Select Problem
   |
   v
Send Location
   |
   v
Transport Manager
```

Example:

```text
Engine Problem
Tyre Problem
Battery Problem
Mechanical Problem
Other
```

---

# 47. Replacement Vehicle

Example:

```text
BUS-24
   |
   X
BREAKDOWN
   |
   v
Replacement Required
   |
   v
BUS-31
   |
   v
New Driver
   |
   v
Continue Trip
```

Parent app should receive the updated vehicle information if the school enables that notification.

---

# 48. Child Still Onboard Safety Check

When driver attempts to complete a trip:

```text
Expected Students:
28

Dropped:
27

Remaining:
1
```

Show:

```text
⚠ 1 STUDENT STILL REGISTERED ONBOARD
```

Require resolution before final trip completion.

Possible resolution:

```text
DROPPED
ABSENT
TRANSFERRED
MANUAL OVERRIDE
```

Every override should be logged.

---

# 49. School Arrival

When the vehicle enters the school geofence:

```text
SCHOOL_ENTERED
       |
       v
Trip arrival
       |
       v
Student drop workflow
       |
       v
Attendance reconciliation
```

The system should not assume every student has been dropped solely from the GPS geofence. Student attendance/status must be reconciled.

---

# 50. Trip Completion

Normal flow:

```text
All stops complete
      |
      v
School/Final stop reached
      |
      v
Student reconciliation
      |
      v
Driver completes trip
      |
      v
GPS session stops
      |
      v
Trip COMPLETED
```

---

# 51. Trip History

Admin can search:

```text
Date
Vehicle
Driver
Route
Trip
```

Display:

```text
Start:
07:02 AM

End:
08:11 AM

Duration:
69 min

Distance:
28.4 km

Stops:
12

Students:
28

Delayed:
+4 min
```

---

# 52. Trip Replay

Store enough historical GPS points to replay the route.

Controls:

```text
|<  <  ▶  >  >|
1x 2x 5x 10x
```

Show:

- Vehicle movement
- Time
- Speed
- Stops
- Geofence events
- Boarding events
- Route deviations

---

# 53. Transport Dashboard

School portal dashboard:

```text
------------------------------------------------
TRANSPORT DASHBOARD
------------------------------------------------

Total Vehicles       32
Active Trips         18
Completed Trips       9
Delayed Trips         3
GPS Offline           2
Emergencies           0

------------------------------------------------

                 LIVE MAP

      🚌 BUS 01

             🚌 BUS 04

   🚌 BUS 12

                     🚌 BUS 24

------------------------------------------------

Alerts:
⚠ Bus 04 delayed
⚠ Bus 12 GPS offline
------------------------------------------------
```

---

# 54. Fleet Status

```text
MOVING
STOPPED
OFFLINE
AT_SCHOOL
AT_DEPOT
ON_TRIP
EMERGENCY
MAINTENANCE
```

The UI should use consistent status indicators across web and mobile.

---

# 55. GPS Health Dashboard

Show:

```text
GPS CONNECTED
GPS OFFLINE
PERMISSION LOST
STALE LOCATION
LOW ACCURACY
MOCK LOCATION WARNING
NETWORK OFFLINE
```

Example:

```text
BUS-24

GPS:
Connected

Last update:
5 seconds ago

Accuracy:
8m
```

If no location is received for a configured duration:

```text
GPS STALE
```

---

# 56. Parent Notifications

Supported events:

```text
TRIP_STARTED
BUS_APPROACHING
BUS_ARRIVED_AT_STOP
STUDENT_BOARDED
STUDENT_DROPPED
TRIP_DELAYED
VEHICLE_CHANGED
TRIP_COMPLETED
```

Optional:

```text
ROUTE_CHANGE
EMERGENCY
```

Notification preferences should be configurable.

---

# 57. Notification Engine

Use one centralized notification service.

```text
Event
 |
 v
Notification Rules
 |
 +---- Push
 +---- WhatsApp
 +---- SMS
 +---- Email
```

Do not implement notification logic separately inside every module.

---

# 58. Mobile App Navigation — Driver

Recommended:

```text
Driver App

Home
 |
 +-- Today's Trips
 |
 +-- Active Trip
 |     |
 |     +-- Live Route
 |     +-- Stops
 |     +-- Students
 |     +-- Attendance
 |     +-- Trip Summary
 |
 +-- Notifications
 |
 +-- Emergency
 |
 +-- Profile
```

During an active trip, keep the interface focused on:

```text
Next Stop
GPS Status
Trip Status
Student Count
SOS
```

---

# 59. Mobile App Navigation — Parent

```text
Parent App

Home
 |
 +-- Children
 |
 +-- Track Bus
 |
 +-- Live Map
 |
 +-- ETA
 |
 +-- Boarding Status
 |
 +-- Notifications
 |
 +-- Transport History
 |
 +-- Profile
```

---

# 60. School Web Navigation

```text
Transport
 |
 +-- Dashboard
 +-- Live Tracking
 +-- Vehicles
 +-- Drivers
 +-- Routes
 +-- Stops
 +-- Student Transport
 +-- Trips
 +-- GPS Health
 +-- Alerts
 +-- Emergency Center
 +-- Trip History
 +-- Trip Replay
 +-- Reports
 +-- Settings
```

---

# 61. Database Structure

## Vehicles

```text
vehicles
vehicle_documents
vehicle_driver_assignments
```

## Drivers

```text
drivers
driver_documents
driver_vehicle_assignments
```

## Routes

```text
routes
route_stops
route_schedules
route_geometry
stops
```

## Students

```text
student_transport_assignments
```

## Trips

```text
trips
trip_stops
trip_students
trip_events
```

## GPS

```text
location_sources
vehicle_locations
vehicle_current_states
gps_sessions
```

## Geofencing

```text
geofences
geofence_events
```

## Attendance

```text
boarding_events
student_trip_attendance
```

## Alerts

```text
alert_rules
alerts
alert_events
```

## Notifications

```text
notification_rules
notification_logs
```

## Emergency

```text
emergency_incidents
emergency_actions
```

---

# 62. location_sources

This table is important for future hardware support.

```text
location_sources

id
school_id
vehicle_id
type
source_identifier
status
last_seen_at
metadata
created_at
updated_at
```

Phase 1:

```text
type = MOBILE_APP
source_identifier = driver_device_id
```

Future:

```text
type = GPS_DEVICE
source_identifier = IMEI
```

---

# 63. gps_sessions

Each trip should have a GPS session.

```text
gps_sessions

id
school_id
trip_id
vehicle_id
driver_id
location_source_id
started_at
ended_at
last_location_at
status
```

Example:

```text
Trip TRP-001
       |
       v
GPS Session GPS-001
       |
       +-- Location 1
       +-- Location 2
       +-- Location 3
       +-- ...
```

---

# 64. vehicle_current_states

Use a fast state table/cache.

```text
vehicle_current_states

vehicle_id
trip_id
driver_id
latitude
longitude
speed
heading
status
next_stop_id
eta
gps_status
last_location_at
updated_at
```

This is for live tracking.

Historical locations remain in:

```text
vehicle_locations
```

---

# 65. Event Engine

Do not hardcode business rules into the GPS controller.

Use:

```text
Location Received
       |
       v
Tracking Event Processor
       |
       +-- Geofence
       +-- Route
       +-- Speed
       +-- ETA
       +-- Trip
       +-- Attendance
       +-- Alert
       +-- Notification
```

Events:

```text
LOCATION_RECEIVED
VEHICLE_MOVED
VEHICLE_STOPPED

STOP_APPROACHING
STOP_ARRIVED
STOP_DEPARTED

SCHOOL_ENTERED
SCHOOL_EXITED

ROUTE_DEVIATION
OVERSPEED

GPS_OFFLINE
GPS_ONLINE
GPS_STALE

TRIP_STARTED
TRIP_COMPLETED

STUDENT_BOARDED
STUDENT_DROPPED

SOS_TRIGGERED
BREAKDOWN_REPORTED
```

---

# 66. Alert Rule Engine

Make thresholds configurable.

Example:

```text
Rule:
Overspeed

IF:
speed > configured_limit

AND:
duration > configured_duration

THEN:
create alert
```

Route deviation:

```text
IF:
distance_from_route > 300m

AND:
duration > 60 seconds

THEN:
ROUTE_DEVIATION
```

GPS offline:

```text
IF:
last_location > 5 minutes

AND:
trip = ACTIVE

THEN:
GPS_OFFLINE
```

---

# 67. Permissions

## School Admin

```text
ALL_TRANSPORT
```

## Transport Manager

```text
VIEW_FLEET
MANAGE_VEHICLES
MANAGE_DRIVERS
MANAGE_ROUTES
VIEW_TRACKING
MANAGE_TRIPS
VIEW_REPORTS
MANAGE_ALERTS
MANAGE_EMERGENCIES
```

## Driver

```text
VIEW_OWN_ASSIGNMENT
START_TRIP
TRACK_LOCATION
MANAGE_ATTENDANCE
REPORT_BREAKDOWN
TRIGGER_SOS
COMPLETE_TRIP
```

## Parent

```text
VIEW_OWN_CHILD_TRANSPORT
VIEW_ASSIGNED_VEHICLE
VIEW_LIVE_LOCATION
VIEW_ETA
VIEW_ATTENDANCE
VIEW_NOTIFICATIONS
```

---

# 68. Privacy and Authorization

The server must enforce access control.

Never rely only on frontend hiding.

A parent request such as:

```text
GET /vehicles/24/location
```

must verify:

```text
Parent
   |
   v
Child
   |
   v
Transport Assignment
   |
   v
Vehicle 24
```

Only then return the location.

---

# 69. Data Retention

Location history can become very large.

Define configurable retention:

```text
Raw GPS:
90–180 days

Trip summaries:
Longer retention

Attendance:
According to school policy

Emergency records:
Longer retention if required
```

The exact period should be configurable according to the school's operational, contractual, and legal requirements.

---

# 70. Performance Architecture

Do not query millions of GPS records for live tracking.

Use:

```text
Redis
   |
   +-- Current vehicle state
   +-- Active trip state
   +-- Short-lived ETA state
```

Database:

```text
MySQL/PostgreSQL
   |
   +-- Vehicles
   +-- Routes
   +-- Trips
   +-- Students
   +-- Historical GPS
```

WebSocket:

```text
Live location broadcasting
```

Queue:

```text
Notifications
Events
Heavy processing
Reports
```

---

# 71. Recommended GPS Flow

Complete Phase 1 flow:

```text
DRIVER MOBILE
     |
     | GPS
     v
Location Collector
     |
     | Location Point
     v
Local Queue
     |
     | Internet
     v
Tracking API
     |
     v
Validation
     |
     v
Tracking Engine
     |
     +------------------+
     |                  |
     v                  v
Current State        History
Redis/Cache          Database
     |
     v
Event Engine
     |
     +---- Geofence
     +---- Route
     +---- Speed
     +---- ETA
     +---- Alerts
     +---- Notifications
     |
     v
WebSocket
     |
     +---- School Portal
     |
     +---- Parent App
```

---

# 72. Future Dedicated GPS Device Architecture

Do not implement this now, but design for it.

Future:

```text
Dedicated GPS Device
       |
       | 4G
       v
GPS Provider
       |
       | API/Webhook
       v
GPS Gateway
       |
       v
Location Normalizer
       |
       v
Tracking Engine
       |
       +---- Same Event Engine
       +---- Same ETA Engine
       +---- Same Geofence Engine
       +---- Same Parent App
       +---- Same School Portal
```

The major advantage is:

```text
                 LOCATION
                    |
         +----------+----------+
         |                     |
   MOBILE APP             GPS DEVICE
         |                     |
         +----------+----------+
                    |
             Location Gateway
                    |
             Tracking Engine
                    |
       Everything else remains same
```

---

# 73. Future Device Migration

When dedicated GPS hardware is introduced:

### Phase 1

```text
Vehicle
  |
  +-- Driver Phone GPS
```

### Phase 2

```text
Vehicle
  |
  +-- Driver Phone GPS
  +-- Dedicated GPS
```

### Phase 3

```text
Vehicle
  |
  +-- Dedicated GPS
```

The application should not need to redesign:

- Routes
- Stops
- Trips
- Parents
- Student assignments
- Attendance
- Notifications
- Reports

Only the **location source** changes.

---

# 74. Location Source Priority

When both sources exist:

```text
GPS_DEVICE
     |
     | preferred
     v
MOBILE_APP
     |
     | fallback
     v
No Location
```

Example:

```text
Dedicated GPS:
Online

Driver Phone:
Online

Use:
Dedicated GPS
```

If device goes offline:

```text
Dedicated GPS:
Offline

Driver Phone:
Online

Use:
Mobile GPS
```

This should be configurable.

---

# 75. Mobile GPS Phase 1 Scope

## Must Have

```text
✓ Driver authentication
✓ Vehicle assignment
✓ Route assignment
✓ Trip management
✓ Mobile GPS
✓ Background tracking
✓ Offline location queue
✓ Live school map
✓ Live parent map
✓ WebSocket updates
✓ Route stops
✓ Geofencing
✓ ETA
✓ Route deviation
✓ GPS health
✓ Student boarding
✓ Student drop
✓ Parent notifications
✓ SOS
✓ Breakdown
✓ Trip history
✓ Basic analytics
```

---

# 76. Phase 1.5

After initial deployment:

```text
✓ Trip replay
✓ Advanced ETA
✓ Driver behavior reports
✓ Vehicle utilization
✓ Route performance
✓ Advanced notification rules
✓ Transport control center
✓ Better offline synchronization
✓ GPS quality analytics
```

---

# 77. Phase 2 — Dedicated GPS

Later:

```text
✓ GPS device registry
✓ IMEI management
✓ Device-provider integrations
✓ Device heartbeat
✓ Ignition status
✓ External power status
✓ Device battery
✓ Tamper alerts
✓ Hardware-based speed
✓ Device-based location
```

The existing Tracking Engine should consume normalized location data exactly as it does with mobile GPS.

---

# 78. Recommended Development Order

## Step 1 — Transport Foundation

Build:

```text
Vehicles
Drivers
Routes
Stops
Student Transport Assignments
```

## Step 2 — Trips

Build:

```text
Trip scheduling
Trip states
Driver assignments
Vehicle assignments
```

## Step 3 — Mobile GPS

Build:

```text
GPS permission
Background GPS
Location collection
Local queue
Sync
Location API
```

## Step 4 — Live Tracking

Build:

```text
Redis
Current vehicle state
WebSocket
School live map
Parent live map
```

## Step 5 — Geofence

Build:

```text
Stop geofence
School geofence
Enter/exit events
Stop arrival/departure
```

## Step 6 — ETA

Build:

```text
Current position
Remaining route
Next stop
Child stop
School ETA
```

## Step 7 — Student Transport

Build:

```text
Boarding
Drop
Absent
Trip attendance
Parent notification
```

## Step 8 — Safety

Build:

```text
SOS
Breakdown
Route deviation
Overspeed
GPS offline
GPS permission lost
Child still onboard
```

## Step 9 — Analytics

Build:

```text
Trip reports
Vehicle reports
Driver reports
Route reports
GPS reports
```

## Step 10 — Dedicated GPS Abstraction

Prepare:

```text
Location Provider Interface
GPS Gateway
Device Registry
Provider Adapters
```

Do not connect hardware until the mobile implementation is stable.

---

# 79. Recommended Core Services

Backend services/modules:

```text
TransportService
VehicleService
DriverService
RouteService
StopService
TripService

LocationService
TrackingService
GeofenceService
ETAService

StudentTransportService
BoardingService

AlertService
NotificationService
EmergencyService

TransportReportService
```

GPS-specific:

```text
LocationProviderInterface
MobileLocationProvider
FutureGpsDeviceProvider
LocationNormalizer
LocationValidator
```

---

# 80. Critical Interfaces

Design the future abstraction around:

```text
interface LocationProvider
{
    receiveLocation();
    getCurrentLocation();
    getProviderStatus();
}
```

Conceptually:

```text
MobileLocationProvider
        |
        implements
        |
LocationProvider
        ^
        |
GpsDeviceProvider
```

The Tracking Engine should depend on the interface, not the mobile app implementation.

---

# 81. Recommended Phase 1 Technology Pattern

For a modern web + mobile system:

```text
Driver Mobile App
        |
        | HTTPS
        v
REST API
        |
        +---- Auth
        +---- Location ingestion
        +---- Trips
        +---- Attendance
        |
        v
Tracking Service
        |
        +---- Redis
        +---- Database
        +---- Queue
        |
        v
WebSocket
        |
        +---- React/Web
        +---- Parent Mobile
```

The exact framework can vary. The important part is the separation of:

```text
Location ingestion
Tracking
Business events
Notifications
Presentation
```

---

# 82. Security Requirements

GPS is sensitive data.

Implement:

- Authentication
- Authorization
- School/tenant isolation
- Driver authorization
- Parent authorization
- API rate limiting
- Input validation
- Location payload validation
- Secure WebSocket authorization
- Audit logging
- Encryption in transit
- Secure token handling
- No GPS data in unnecessary logs
- Access-controlled historical tracking

Never expose an unprotected endpoint such as:

```text
/api/location?vehicle_id=24
```

without authorization.

---

# 83. Audit Logging

Log important actions:

```text
Trip Started
Trip Completed
Vehicle Assignment Changed
Route Changed
Student Assignment Changed
Manual Attendance Override
SOS Triggered
Emergency Closed
Location Source Changed
Driver Changed
```

Example:

```text
User:
Transport Manager

Action:
MANUAL_ATTENDANCE_OVERRIDE

Student:
Arjun

Trip:
TRP-001

Reason:
Parent pickup

Time:
08:42 AM
```

---

# 84. Admin Settings

Transport settings:

```text
GPS

Moving update interval
Stopped update interval
GPS stale threshold
GPS accuracy threshold

Route

Route deviation distance
Route deviation duration

Speed

Default speed limit
Overspeed duration

Geofence

Default stop radius
School radius

ETA

Delay warning threshold

Notifications

Parent notification rules
Emergency notification rules
```

Do not hardcode these values throughout the application.

---

# 85. Final Architecture

The recommended Phase 1 architecture is:

```text
                         SCHOOL MANAGEMENT SYSTEM
                                  |
                         TRANSPORT MODULE
                                  |
          +-----------------------+-----------------------+
          |                       |                       |
      SCHOOL WEB             DRIVER APP             PARENT APP
          |                       |                       |
          |                 MOBILE GPS                 |
          |                       |                       |
          +-----------------------+-----------------------+
                                  |
                            LOCATION API
                                  |
                         LOCATION VALIDATOR
                                  |
                         LOCATION NORMALIZER
                                  |
                         TRACKING ENGINE
                                  |
              +-------------------+-------------------+
              |                   |                   |
            Redis             Database             Queue
              |                   |                   |
       Current State        GPS History        Async Events
              |                   |                   |
              +-------------------+-------------------+
                                  |
                            EVENT ENGINE
                                  |
       +------------+-------------+-------------+------------+
       |            |             |             |            |
    Geofence       ETA         Route         Speed       Alerts
       |            |             |             |            |
       +------------+-------------+-------------+------------+
                                  |
                         NOTIFICATION ENGINE
                                  |
                    +-------------+-------------+
                    |                           |
                  Parent                      School
                  App                         Portal
```

---

# 86. Future-Proof GPS Architecture

The most important architectural boundary is:

```text
                LOCATION SOURCES
                       |
          +------------+------------+
          |                         |
     MOBILE APP                GPS DEVICE
       Phase 1                  Future
          |                         |
          +------------+------------+
                       |
                LOCATION GATEWAY
                       |
               NORMALIZED LOCATION
                       |
                TRACKING ENGINE
                       |
        +--------------+--------------+
        |              |              |
      Trips         Geofence         ETA
        |              |              |
        +--------------+--------------+
                       |
                 APPLICATION
                       |
          +------------+------------+
          |                         |
      SCHOOL PORTAL             PARENT APP
```

This is the recommended approach for the school-management application:

**Build the complete transport intelligence layer now, but use the driver's phone as the GPS source.**

Later, dedicated GPS hardware becomes only another **location provider**, instead of requiring a second transport system.

---

# 87. Phase 1 Definition of Done

The Mobile GPS Transport Module is considered complete when:

```text
[x] Vehicle can be created
[x] Driver can be assigned
[x] Route can be created
[x] Stops can be created
[x] Students can be assigned to stops
[x] Trip can be scheduled
[x] Driver can see assigned trip
[x] Driver can start trip
[x] Mobile GPS starts
[x] Background GPS works
[x] GPS location reaches server
[x] Location is validated
[x] Location is stored
[x] Current vehicle state is updated
[x] School sees live vehicle
[x] Parent sees authorized vehicle
[x] WebSocket updates work
[x] Offline GPS queue works
[x] GPS sync resumes after network recovery
[x] Stop geofence works
[x] School geofence works
[x] Stop arrival detected
[x] Stop departure detected
[x] ETA calculated
[x] Route deviation detected
[x] Overspeed event detected
[x] GPS offline event detected
[x] Student boarding works
[x] Student drop works
[x] Parent notification works
[x] SOS works
[x] Breakdown workflow works
[x] Trip completion works
[x] Child-still-onboard validation works
[x] Trip history works
[x] Access control works
[x] Audit logs work
[x] Tenant/school isolation works
[x] Location source abstraction exists
[x] Future GPS device provider can be added without changing core tracking
```

---

# 88. Key Principle

**Do not build a "mobile GPS feature".**

Build a:

> **Transport Tracking Platform with Mobile GPS as the first Location Provider.**

That distinction is important.

The first implementation is:

```text
Mobile Phone GPS
```

but the permanent architecture is:

```text
Location Provider
```

This gives the school-management system a clean path from:

```text
10 buses
   ↓
50 buses
   ↓
100 buses
   ↓
500+ buses
```

without replacing the core transport architecture when dedicated GPS devices are introduced.
"""

out = "/mnt/data/School_Management_Mobile_GPS_Transport_Module.md"
with open(out, "w", encoding="utf-8") as f:
    f.write(md)

print(out)
