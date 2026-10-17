/// A trip from the GPS Transport addon (`api/driver/gps/today-trips`). These are the
/// trips whose live position parents see; they are matched to the transport module's
/// trip by route.
class GpsTrip {
  final int id;
  final String tripNumber;
  final String tripType;
  final String status;
  final String vehicleNumber;
  final int? vehicleId;
  final int? routeId;
  final String routeName;
  final String? scheduledTime;
  final int totalStudents;
  final int boardedStudents;

  const GpsTrip({
    required this.id,
    required this.tripNumber,
    required this.tripType,
    required this.status,
    required this.vehicleNumber,
    required this.vehicleId,
    required this.routeId,
    required this.routeName,
    required this.scheduledTime,
    required this.totalStudents,
    required this.boardedStudents,
  });

  bool get isActive => status == 'ACTIVE';

  factory GpsTrip.fromJson(Map<String, dynamic> json) {
    int? asInt(dynamic v) => v == null ? null : int.tryParse(v.toString());
    return GpsTrip(
      id: asInt(json['trip_id']) ?? 0,
      tripNumber: (json['trip_number'] ?? '').toString(),
      tripType: (json['trip_type'] ?? '').toString(),
      status: (json['status'] ?? '').toString(),
      vehicleNumber: (json['vehicle_number'] ?? '').toString(),
      vehicleId: asInt(json['vehicle_id']),
      routeId: asInt(json['route_id']),
      routeName: (json['route_name'] ?? '').toString(),
      scheduledTime: json['scheduled_time']?.toString(),
      totalStudents: asInt(json['total_students']) ?? 0,
      boardedStudents: asInt(json['boarded_students']) ?? 0,
    );
  }
}

/// Server-provided sampling settings.
class GpsTrackingConfig {
  final int movingIntervalSeconds;
  final int stoppedIntervalSeconds;
  final int batchSize;

  const GpsTrackingConfig({
    this.movingIntervalSeconds = 10,
    this.stoppedIntervalSeconds = 30,
    this.batchSize = 50,
  });

  factory GpsTrackingConfig.fromJson(Map<String, dynamic>? json) {
    int read(String key, int fallback) =>
        int.tryParse((json?[key] ?? '').toString()) ?? fallback;
    return GpsTrackingConfig(
      movingIntervalSeconds: read('moving_interval_seconds', 10).clamp(3, 120),
      stoppedIntervalSeconds: read('stopped_interval_seconds', 30).clamp(5, 300),
      batchSize: read('offline_sync_batch_size', 50).clamp(1, 500),
    );
  }
}
