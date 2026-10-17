import 'package:dio/dio.dart';
import 'package:zolo_smart_school_staff/data/models/gpsTrip.dart';
import 'package:zolo_smart_school_staff/utils/api.dart';
import 'package:zolo_smart_school_staff/utils/constants.dart';

/// GPS Transport addon endpoints for drivers. Unlike [Api.post] this keeps the
/// server's own error message (e.g. "This trip is assigned to another driver"),
/// which the driver needs to see, and sends location batches as JSON.
class GpsRepository {
  static final String _base = "${databaseUrl}driver/gps/";

  final Dio _dio = Dio(BaseOptions(
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 20),
    sendTimeout: const Duration(seconds: 20),
  ));

  Options get _options => Options(
        headers: {...Api.headers(), 'Accept': 'application/json'},
        contentType: Headers.jsonContentType,
      );

  Future<({List<GpsTrip> trips, GpsTrackingConfig config})> todayTrips() async {
    final data = await _post('today-trips', {});
    final trips = ((data['data'] ?? []) as List)
        .map((e) => GpsTrip.fromJson(Map<String, dynamic>.from(e)))
        .toList();
    return (
      trips: trips,
      config: GpsTrackingConfig.fromJson(
          data['config'] == null ? null : Map<String, dynamic>.from(data['config'])),
    );
  }

  Future<void> startTrip(int tripId) => _post('start-trip', {'trip_id': tripId});

  /// Returns the server message; throws [GpsApiException] with
  /// [GpsApiException.stillOnboard] set when students are still marked on board.
  Future<String> endTrip(int tripId, {String? overrideReason}) async {
    final data = await _post('end-trip', {
      'trip_id': tripId,
      if (overrideReason != null && overrideReason.isNotEmpty)
        'override_reason': overrideReason,
    });
    return (data['message'] ?? '').toString();
  }

  /// Uploads a batch; returns how many points the server accepted.
  Future<int> sendLocations(List<Map<String, dynamic>> points) async {
    final data = await _post('location', {'locations': points});
    return int.tryParse((data['processed_count'] ?? 0).toString()) ?? 0;
  }

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> body) async {
    try {
      final response = await _dio.post(_base + path, data: body, options: _options);
      final data = Map<String, dynamic>.from(response.data as Map);
      if (data['error'] == true) {
        throw GpsApiException((data['message'] ?? 'Request failed').toString());
      }
      return data;
    } on DioException catch (e) {
      final res = e.response?.data;
      if (res is Map && res['message'] != null) {
        throw GpsApiException(res['message'].toString(),
            statusCode: e.response?.statusCode,
            stillOnboard: res['still_onboard'] == true);
      }
      throw GpsApiException(
        e.type == DioExceptionType.connectionError ||
                e.type == DioExceptionType.connectionTimeout ||
                e.type == DioExceptionType.receiveTimeout ||
                e.type == DioExceptionType.sendTimeout
            ? 'No connection to the server.'
            : 'Request failed (${e.response?.statusCode ?? 'network'}).',
        statusCode: e.response?.statusCode,
        isNetwork: e.response == null,
      );
    }
  }
}

class GpsApiException implements Exception {
  final String message;
  final int? statusCode;
  final bool isNetwork;
  final bool stillOnboard;

  GpsApiException(this.message,
      {this.statusCode, this.isNetwork = false, this.stillOnboard = false});

  @override
  String toString() => message;
}
