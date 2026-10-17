import 'package:zolo_smart_school_staff/data/models/gpsTrip.dart';
import 'package:zolo_smart_school_staff/data/repositories/gpsRepository.dart';
import 'package:zolo_smart_school_staff/utils/gps/gpsTrackingService.dart';

/// Glue between the transport trip screen and the GPS addon: find the GPS trip for a
/// route, start / end it on the server, and start / stop the phone's tracking.
class GpsTripActions {
  GpsTripActions._();

  static final GpsRepository _repository = GpsRepository();

  /// Today's GPS trips for this driver, with the server's sampling settings.
  static Future<({List<GpsTrip> trips, GpsTrackingConfig config})> load() => _repository.todayTrips();

  /// The GPS trip that belongs to [routeId]: an active one first, otherwise the next
  /// scheduled one. Null when the office has not scheduled a GPS trip for the route.
  static GpsTrip? pick(List<GpsTrip> trips, int? routeId) {
    final forRoute = trips.where((t) => routeId == null || t.routeId == routeId).toList();
    if (forRoute.isEmpty) return null;
    return forRoute.firstWhere((t) => t.isActive, orElse: () => forRoute.first);
  }

  /// Start sharing the bus position for [trip]. Returns an error message or null.
  static Future<String?> start(GpsTrip trip, GpsTrackingConfig config) async {
    final denied = await GpsTrackingService.instance.ensurePermission();
    if (denied != null) return denied;
    try {
      if (!trip.isActive) {
        await _repository.startTrip(trip.id);
      }
      await GpsTrackingService.instance.start(trip.id, config: config);
      return null;
    } on GpsApiException catch (e) {
      return e.message;
    }
  }

  /// Convenience for the "Start trip" button: start the GPS trip of this route, if any.
  static Future<String?> startForRoute(int? routeId) async {
    try {
      final today = await load();
      final trip = pick(today.trips, routeId);
      if (trip == null) return null; // No GPS trip scheduled for this route: nothing to share.
      if (GpsTrackingService.instance.activeTripId == trip.id) return null;
      return await start(trip, today.config);
    } on GpsApiException catch (e) {
      // 403 = not a driver account (e.g. a helper): GPS sharing is the driver's job.
      return e.statusCode == 403 ? null : e.message;
    }
  }

  /// Stop sharing and end the GPS trip on the server.
  /// Throws [GpsApiException] (with stillOnboard) if students are still marked on board.
  static Future<String> end({String? overrideReason}) async {
    final tripId = GpsTrackingService.instance.activeTripId;
    if (tripId == null) return '';
    final message = await _repository.endTrip(tripId, overrideReason: overrideReason);
    await GpsTrackingService.instance.stop();
    return message;
  }
}
