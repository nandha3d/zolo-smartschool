import 'package:dio/dio.dart';
import 'package:zolo_smart_school/utils/api.dart';
import 'package:zolo_smart_school/utils/constants.dart';

/// A child with school transport, from `api/parent/gps/children-transport`.
class GpsChild {
  final int studentId;
  final String name;
  final String className;
  final bool hasTransport;
  final String? routeName;
  final String? vehicleNumber;
  final String tripStatus;
  final String boardingStatus;

  const GpsChild({
    required this.studentId,
    required this.name,
    required this.className,
    required this.hasTransport,
    this.routeName,
    this.vehicleNumber,
    this.tripStatus = 'NOT_STARTED',
    this.boardingStatus = 'EXPECTED',
  });

  factory GpsChild.fromJson(Map<String, dynamic> j) => GpsChild(
        studentId: int.tryParse('${j['student_id']}') ?? 0,
        name: '${j['name'] ?? ''}',
        className: '${j['class'] ?? ''}'.trim(),
        hasTransport: j['has_transport'] == true,
        routeName: j['route_name']?.toString(),
        vehicleNumber: j['vehicle_number']?.toString(),
        tripStatus: '${j['trip_status'] ?? 'NOT_STARTED'}',
        boardingStatus: '${j['boarding_status'] ?? 'EXPECTED'}',
      );
}

/// Live bus state for one child, from `api/parent/gps/track-bus`.
class BusLocation {
  final bool tripActive;
  final String? message;
  final String childName;
  final String boardingStatus;
  final String? vehicleNumber;
  final double? latitude;
  final double? longitude;
  final double speed;
  final double heading;
  final bool stale;
  final String lastUpdate;
  final String? driverName;
  final String? driverPhone;
  final String? stopName;
  final double? stopLatitude;
  final double? stopLongitude;
  final String? eta;
  final String? nextStop;
  final String tileUrl;
  final String attribution;

  const BusLocation({
    required this.tripActive,
    this.message,
    required this.childName,
    this.boardingStatus = 'EXPECTED',
    this.vehicleNumber,
    this.latitude,
    this.longitude,
    this.speed = 0,
    this.heading = 0,
    this.stale = true,
    this.lastUpdate = '',
    this.driverName,
    this.driverPhone,
    this.stopName,
    this.stopLatitude,
    this.stopLongitude,
    this.eta,
    this.nextStop,
    this.tileUrl = defaultTileUrl,
    this.attribution = '© OpenStreetMap contributors',
  });

  static const String defaultTileUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

  bool get hasPosition => latitude != null && longitude != null && !(latitude == 0 && longitude == 0);
  bool get hasStop => stopLatitude != null && stopLongitude != null && !(stopLatitude == 0 && stopLongitude == 0);

  factory BusLocation.fromJson(Map<String, dynamic> j) {
    double? d(dynamic v) => v == null ? null : double.tryParse('$v');
    final vehicle = Map<String, dynamic>.from(j['vehicle'] ?? {});
    final driver = Map<String, dynamic>.from(j['driver'] ?? {});
    final stop = Map<String, dynamic>.from(j['child_stop'] ?? {});
    final map = Map<String, dynamic>.from(j['map'] ?? {});
    return BusLocation(
      tripActive: j['trip_active'] == true,
      message: j['message']?.toString(),
      childName: '${j['child_name'] ?? ''}',
      boardingStatus: '${j['boarding_status'] ?? 'EXPECTED'}',
      vehicleNumber: vehicle['vehicle_number']?.toString(),
      latitude: d(vehicle['latitude']),
      longitude: d(vehicle['longitude']),
      speed: d(vehicle['speed']) ?? 0,
      heading: d(vehicle['heading']) ?? 0,
      stale: vehicle['stale'] != false,
      lastUpdate: '${vehicle['last_update'] ?? ''}',
      driverName: driver['name']?.toString(),
      driverPhone: (driver['phone']?.toString().isEmpty ?? true) ? null : driver['phone'].toString(),
      stopName: stop['name']?.toString(),
      stopLatitude: d(stop['latitude']),
      stopLongitude: d(stop['longitude']),
      eta: j['eta']?.toString(),
      nextStop: j['next_stop']?.toString(),
      tileUrl: (map['tile_url']?.toString().isNotEmpty ?? false) ? map['tile_url'].toString() : defaultTileUrl,
      attribution: map['attribution']?.toString() ?? '© OpenStreetMap contributors',
    );
  }
}

class GpsTrackingRepository {
  static final String _base = '${databaseUrl}parent/gps/';
  final Dio _dio = Dio(BaseOptions(connectTimeout: const Duration(seconds: 15), receiveTimeout: const Duration(seconds: 20)));

  Future<List<GpsChild>> children() async {
    final data = await _post('children-transport', {});
    return ((data['children'] ?? []) as List).map((e) => GpsChild.fromJson(Map<String, dynamic>.from(e))).toList();
  }

  Future<BusLocation> trackBus(int studentId) async {
    final data = await _post('track-bus', {'student_id': studentId});
    return BusLocation.fromJson(data);
  }

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> body) async {
    try {
      final res = await _dio.post(_base + path, data: body, options: Options(headers: Api.headers()));
      final data = Map<String, dynamic>.from(res.data as Map);
      if (data['error'] == true) throw GpsTrackingException('${data['message'] ?? 'Request failed'}');
      return data;
    } on DioException catch (e) {
      final res = e.response?.data;
      if (res is Map && res['message'] != null) {
        throw GpsTrackingException('${res['message']}', statusCode: e.response?.statusCode);
      }
      throw GpsTrackingException(e.response == null ? 'No connection. Retrying…' : 'Could not load the bus location.',
          statusCode: e.response?.statusCode);
    }
  }
}

class GpsTrackingException implements Exception {
  final String message;
  final int? statusCode;
  GpsTrackingException(this.message, {this.statusCode});
  @override
  String toString() => message;
}
