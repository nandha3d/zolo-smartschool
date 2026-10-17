import 'dart:async';
import 'dart:io';
import 'dart:math' as math;

import 'package:flutter/widgets.dart';
import 'package:geolocator/geolocator.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:zolo_smart_school_staff/data/models/gpsTrip.dart';
import 'package:zolo_smart_school_staff/data/repositories/gpsRepository.dart';

/// Live state of the driver's GPS tracking, for the UI.
@immutable
class GpsTrackingStatus {
  final int? tripId;
  final bool tracking;
  final int queued;
  final DateTime? lastFixAt;
  final double? lastAccuracy;
  final DateTime? lastUploadAt;
  final String? error;
  final bool mockLocation;

  const GpsTrackingStatus({
    this.tripId,
    this.tracking = false,
    this.queued = 0,
    this.lastFixAt,
    this.lastAccuracy,
    this.lastUploadAt,
    this.error,
    this.mockLocation = false,
  });

  GpsTrackingStatus copyWith({
    int? tripId,
    bool? tracking,
    int? queued,
    DateTime? lastFixAt,
    double? lastAccuracy,
    DateTime? lastUploadAt,
    String? error,
    bool clearError = false,
    bool? mockLocation,
    bool clearTrip = false,
  }) {
    return GpsTrackingStatus(
      tripId: clearTrip ? null : (tripId ?? this.tripId),
      tracking: tracking ?? this.tracking,
      queued: queued ?? this.queued,
      lastFixAt: lastFixAt ?? this.lastFixAt,
      lastAccuracy: lastAccuracy ?? this.lastAccuracy,
      lastUploadAt: lastUploadAt ?? this.lastUploadAt,
      error: clearError ? null : (error ?? this.error),
      mockLocation: mockLocation ?? this.mockLocation,
    );
  }
}

/// Collects the driver phone's position for the active GPS trip and delivers it to
/// the server.
///
/// * Positions come from a geolocator stream. On Android it runs inside a foreground
///   service (persistent notification), so tracking continues with the screen off or
///   the app in the background; on iOS background location updates are enabled.
/// * Every fix is written to a Hive box first, so nothing is lost when the bus drives
///   through a dead zone or the app is killed; the queue is flushed in batches with
///   exponential back-off and capped at [_maxQueue] points (oldest dropped first).
/// * The active trip id is persisted; [resumeIfNeeded] restarts tracking after an
///   app restart.
///
/// Geofencing, ETA and alerts stay on the server — this class only reports positions.
class GpsTrackingService with WidgetsBindingObserver {
  GpsTrackingService._();
  static final GpsTrackingService instance = GpsTrackingService._();

  static const String queueBoxKey = 'gpsQueue';
  static const String stateBoxKey = 'gpsState';
  static const String _activeTripKey = 'activeTripId';
  static const int _maxQueue = 5000;

  final GpsRepository _repository = GpsRepository();
  final ValueNotifier<GpsTrackingStatus> status = ValueNotifier(const GpsTrackingStatus());

  GpsTrackingConfig _config = const GpsTrackingConfig();
  StreamSubscription<Position>? _subscription;
  Timer? _uploadTimer;
  bool _uploading = false;
  int _failures = 0;
  int _refusedBatches = 0;
  DateTime? _nextAttempt;
  Position? _lastQueued;
  bool _inBackground = false;
  bool _observing = false;

  Box get _queue => Hive.box(queueBoxKey);
  Box get _state => Hive.box(stateBoxKey);

  static Future<void> openBoxes() async {
    await Hive.openBox(queueBoxKey);
    await Hive.openBox(stateBoxKey);
  }

  int? get activeTripId => _state.get(_activeTripKey) as int?;

  /// Asks for location access the first time a trip is started (not at app launch).
  /// Returns an error message, or null when tracking may start.
  Future<String?> ensurePermission() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return 'Turn on location (GPS) on this phone to share the bus position.';
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied) {
      return 'Location permission is needed to share the bus position with parents.';
    }
    if (permission == LocationPermission.deniedForever) {
      await Geolocator.openAppSettings();
      return 'Location permission is blocked. Allow it in Settings, then start again.';
    }
    // On iOS "while in use" pauses in the background; ask once for "always".
    if (Platform.isIOS && permission == LocationPermission.whileInUse) {
      await Geolocator.requestPermission();
    }
    return null;
  }

  /// Start tracking [tripId] (already started on the server).
  Future<void> start(int tripId, {GpsTrackingConfig? config}) async {
    if (config != null) _config = config;
    await _state.put(_activeTripKey, tripId);
    status.value = status.value.copyWith(tripId: tripId, tracking: true, clearError: true, queued: _queue.length);
    _observe();
    await _subscribe();
    _scheduleUploads();
  }

  /// Called at app start: if a trip was being tracked when the app died, carry on.
  Future<void> resumeIfNeeded() async {
    final tripId = activeTripId;
    if (_queue.isNotEmpty) _scheduleUploads();
    if (tripId == null || _subscription != null) return;
    final permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      status.value = status.value.copyWith(
          tripId: tripId, tracking: false, error: 'Location permission was removed; tracking is paused.');
      return;
    }
    await start(tripId);
  }

  /// Stop collecting, then try to deliver whatever is still queued.
  Future<void> stop() async {
    await _subscription?.cancel();
    _subscription = null;
    await _state.delete(_activeTripKey);
    status.value = status.value.copyWith(tracking: false, clearTrip: true);
    await flush(force: true);
  }

  Future<void> _subscribe() async {
    await _subscription?.cancel();
    final interval = Duration(seconds: _config.movingIntervalSeconds);
    final LocationSettings settings;
    if (Platform.isAndroid) {
      settings = AndroidSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10,
        intervalDuration: interval,
        foregroundNotificationConfig: const ForegroundNotificationConfig(
          notificationTitle: 'Sharing bus location',
          notificationText: 'Parents can see the bus on the map until you end the trip.',
          enableWakeLock: true,
          setOngoing: true,
        ),
      );
    } else if (Platform.isIOS) {
      settings = AppleSettings(
        accuracy: LocationAccuracy.bestForNavigation,
        activityType: ActivityType.automotiveNavigation,
        distanceFilter: 10,
        pauseLocationUpdatesAutomatically: false,
        allowBackgroundLocationUpdates: true,
        showBackgroundLocationIndicator: true,
      );
    } else {
      settings = const LocationSettings(accuracy: LocationAccuracy.high, distanceFilter: 10);
    }

    _subscription = Geolocator.getPositionStream(locationSettings: settings).listen(
      _onPosition,
      onError: (Object e) {
        status.value = status.value.copyWith(error: 'GPS error: $e');
      },
    );
  }

  void _onPosition(Position position) {
    final tripId = activeTripId;
    if (tripId == null) return;

    // Stationary buses report less often (stopped interval) to save battery and data.
    final last = _lastQueued;
    if (last != null) {
      final seconds = position.timestamp.difference(last.timestamp).inSeconds.abs();
      final moved = Geolocator.distanceBetween(last.latitude, last.longitude, position.latitude, position.longitude);
      final stationary = position.speed < 1.0 && moved < 15;
      if (stationary && seconds < _config.stoppedIntervalSeconds) return;
      if (!stationary && seconds < math.max(2, _config.movingIntervalSeconds - 1) && moved < 50) return;
    }
    _lastQueued = position;

    _queue.add({
      'trip_id': tripId,
      'latitude': position.latitude,
      'longitude': position.longitude,
      'accuracy': position.accuracy,
      'altitude': position.altitude,
      // geolocator reports m/s; the server works in km/h.
      'speed': position.speed < 0 ? 0 : position.speed * 3.6,
      'heading': position.heading < 0 ? 0 : position.heading,
      'timestamp': position.timestamp.toUtc().toIso8601String(),
      'is_mock_location': position.isMocked,
      'is_background': _inBackground,
      'os': Platform.operatingSystem,
    });
    while (_queue.length > _maxQueue) {
      _queue.deleteAt(0);
    }

    status.value = status.value.copyWith(
      queued: _queue.length,
      lastFixAt: DateTime.now(),
      lastAccuracy: position.accuracy,
      mockLocation: position.isMocked,
    );
  }

  void _scheduleUploads() {
    _uploadTimer?.cancel();
    _uploadTimer = Timer.periodic(Duration(seconds: _config.movingIntervalSeconds), (_) => flush());
  }

  /// Send queued points in batches until the queue is empty or a send fails.
  Future<void> flush({bool force = false}) async {
    if (_uploading || _queue.isEmpty) {
      if (_queue.isEmpty && activeTripId == null) _uploadTimer?.cancel();
      return;
    }
    if (!force && _nextAttempt != null && DateTime.now().isBefore(_nextAttempt!)) return;

    _uploading = true;
    try {
      while (_queue.isNotEmpty) {
        final count = math.min(_config.batchSize, _queue.length);
        final batch = List.generate(count, (i) => Map<String, dynamic>.from(_queue.getAt(i) as Map));
        final accepted = await _repository.sendLocations(batch);
        // Accepted or rejected by validation, these points are done; drop them.
        await _queue.deleteAll(List.generate(count, (i) => _queue.keyAt(i)));
        // Whole batches refused means the server no longer treats this as our active
        // trip (ended from the office, reassigned …).
        _refusedBatches = accepted == 0 ? _refusedBatches + 1 : 0;
        if (_refusedBatches >= 3 && activeTripId != null) {
          _refusedBatches = 0;
          await _subscription?.cancel();
          _subscription = null;
          await _state.delete(_activeTripKey);
          status.value = status.value.copyWith(
              tracking: false, clearTrip: true, error: 'The server stopped accepting positions for this trip. It may have been ended.');
        }
        _failures = 0;
        _nextAttempt = null;
        status.value = status.value.copyWith(queued: _queue.length, lastUploadAt: DateTime.now(), clearError: true);
      }
    } on GpsApiException catch (e) {
      _failures++;
      // 10 s, 20 s, 40 s … capped at 2 minutes.
      _nextAttempt = DateTime.now().add(Duration(seconds: math.min(120, 10 * math.pow(2, _failures - 1).toInt())));
      status.value = status.value.copyWith(
        queued: _queue.length,
        error: e.isNetwork ? 'Offline — ${_queue.length} points saved, will send when back online.' : e.message,
      );
      // The server no longer accepts this trip (ended elsewhere, reassigned): stop collecting.
      if (e.statusCode == 403 || e.statusCode == 401) {
        await _subscription?.cancel();
        _subscription = null;
        await _state.delete(_activeTripKey);
        await _queue.clear();
        status.value = status.value.copyWith(tracking: false, queued: 0, clearTrip: true, error: e.message);
      }
    } finally {
      _uploading = false;
    }
  }

  void _observe() {
    if (_observing) return;
    WidgetsBinding.instance.addObserver(this);
    _observing = true;
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _inBackground = state != AppLifecycleState.resumed;
    if (state == AppLifecycleState.resumed) flush(force: true);
  }
}
