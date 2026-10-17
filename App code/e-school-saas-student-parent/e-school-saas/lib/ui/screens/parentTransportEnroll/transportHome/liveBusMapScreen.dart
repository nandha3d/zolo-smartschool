import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:zolo_smart_school/data/repositories/gpsTrackingRepository.dart';

/// Live school-bus map for parents (GPS Transport addon). Polls the bus position every
/// [_refresh] while open, shows the child's stop, ETA and boarding status, and says
/// plainly when the last position is too old to trust.
class LiveBusMapScreen extends StatefulWidget {
  /// The child to show first, if known (students.id).
  final int? preferredStudentId;

  const LiveBusMapScreen({super.key, this.preferredStudentId});

  static Future<void> open(BuildContext context, {int? preferredStudentId}) {
    return Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => LiveBusMapScreen(preferredStudentId: preferredStudentId),
    ));
  }

  @override
  State<LiveBusMapScreen> createState() => _LiveBusMapScreenState();
}

class _LiveBusMapScreenState extends State<LiveBusMapScreen> {
  static const Duration _refresh = Duration(seconds: 10);
  static const LatLng _fallbackCenter = LatLng(11.0168, 76.9558);

  final GpsTrackingRepository _repository = GpsTrackingRepository();
  final MapController _map = MapController();

  List<GpsChild> _children = [];
  GpsChild? _child;
  BusLocation? _bus;
  String? _error;
  bool _loading = true;
  bool _follow = true;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _loadChildren();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _loadChildren() async {
    try {
      final all = await _repository.children();
      final withTransport = all.where((c) => c.hasTransport).toList();
      GpsChild? pick;
      if (widget.preferredStudentId != null) {
        for (final c in withTransport) {
          if (c.studentId == widget.preferredStudentId) pick = c;
        }
      }
      pick ??= withTransport.isNotEmpty ? withTransport.first : null;
      if (!mounted) return;
      setState(() {
        _children = withTransport;
        _child = pick;
        _loading = pick != null;
        _error = pick == null ? 'None of your children is assigned to a GPS-tracked bus route.' : null;
      });
      if (pick != null) {
        await _poll();
        _timer = Timer.periodic(_refresh, (_) => _poll());
      }
    } on GpsTrackingException catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.statusCode == 403 ? 'Live bus tracking is not available for this account.' : e.message;
      });
    }
  }

  Future<void> _poll() async {
    final child = _child;
    if (child == null) return;
    try {
      final bus = await _repository.trackBus(child.studentId);
      if (!mounted || _child?.studentId != child.studentId) return;
      setState(() {
        _bus = bus;
        _error = null;
        _loading = false;
      });
      if (_follow && bus.hasPosition) {
        _map.move(LatLng(bus.latitude!, bus.longitude!), _map.camera.zoom);
      }
    } on GpsTrackingException catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = e.message;
      });
    }
  }

  void _selectChild(GpsChild child) {
    setState(() {
      _child = child;
      _bus = null;
      _loading = true;
      _follow = true;
    });
    _poll();
  }

  @override
  Widget build(BuildContext context) {
    final bus = _bus;
    final center = bus?.hasPosition == true
        ? LatLng(bus!.latitude!, bus.longitude!)
        : (bus?.hasStop == true ? LatLng(bus!.stopLatitude!, bus.stopLongitude!) : _fallbackCenter);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Live bus location'),
        actions: [
          if (_children.length > 1)
            PopupMenuButton<GpsChild>(
              icon: const Icon(Icons.people_alt_outlined),
              onSelected: _selectChild,
              itemBuilder: (_) => _children
                  .map((c) => PopupMenuItem(value: c, child: Text('${c.name}  ${c.className}')))
                  .toList(),
            ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: Stack(
              children: [
                FlutterMap(
                  mapController: _map,
                  options: MapOptions(
                    initialCenter: center,
                    initialZoom: 15,
                    onPositionChanged: (camera, hasGesture) {
                      if (hasGesture && _follow) setState(() => _follow = false);
                    },
                  ),
                  children: [
                    TileLayer(
                      urlTemplate: bus?.tileUrl ?? BusLocation.defaultTileUrl,
                      userAgentPackageName: 'com.zoloschools.app',
                    ),
                    MarkerLayer(markers: [
                      if (bus?.hasStop == true)
                        Marker(
                          point: LatLng(bus!.stopLatitude!, bus.stopLongitude!),
                          width: 40,
                          height: 40,
                          child: const Icon(Icons.location_on, color: Colors.red, size: 40),
                        ),
                      if (bus?.hasPosition == true)
                        Marker(
                          point: LatLng(bus!.latitude!, bus.longitude!),
                          width: 46,
                          height: 46,
                          child: Opacity(
                            opacity: bus.stale ? 0.45 : 1,
                            child: Transform.rotate(
                              angle: bus.heading * math.pi / 180,
                              child: Container(
                                decoration: BoxDecoration(
                                  color: Theme.of(context).colorScheme.primary,
                                  shape: BoxShape.circle,
                                  border: Border.all(color: Colors.white, width: 3),
                                  boxShadow: const [BoxShadow(blurRadius: 6, color: Colors.black26)],
                                ),
                                child: const Icon(Icons.navigation, color: Colors.white, size: 24),
                              ),
                            ),
                          ),
                        ),
                    ]),
                    RichAttributionWidget(attributions: [TextSourceAttribution(bus?.attribution ?? '© OpenStreetMap contributors')]),
                  ],
                ),
                if (_loading) const Center(child: CircularProgressIndicator()),
                if (bus != null && bus.tripActive && bus.stale)
                  Positioned(
                    top: 12,
                    left: 12,
                    right: 12,
                    child: _banner(context, Icons.signal_wifi_off, Colors.orange,
                        'Last position ${bus.lastUpdate}. The bus may be in an area with no signal.'),
                  ),
                if (!_follow && bus?.hasPosition == true)
                  Positioned(
                    right: 12,
                    bottom: 12,
                    child: FloatingActionButton.small(
                      onPressed: () {
                        setState(() => _follow = true);
                        _map.move(LatLng(bus!.latitude!, bus.longitude!), _map.camera.zoom);
                      },
                      child: const Icon(Icons.my_location),
                    ),
                  ),
              ],
            ),
          ),
          _details(context),
        ],
      ),
    );
  }

  Widget _details(BuildContext context) {
    final bus = _bus;
    final text = Theme.of(context).textTheme;
    if (_error != null && bus == null) {
      return _panel(context, [Text(_error!, style: text.bodyMedium)]);
    }
    if (bus == null) return const SizedBox.shrink();
    if (!bus.tripActive) {
      return _panel(context, [
        Text(bus.childName, style: text.titleMedium),
        const SizedBox(height: 4),
        Text(bus.message ?? 'The bus is not on a trip right now.', style: text.bodyMedium),
      ]);
    }
    final boarding = {
      'BOARDED': 'On the bus',
      'DROPPED': 'Dropped off',
      'ABSENT': 'Marked absent',
      'EXPECTED': 'Waiting to be picked up',
    }[bus.boardingStatus] ?? bus.boardingStatus;

    return _panel(context, [
      Row(
        children: [
          Expanded(child: Text(bus.childName, style: text.titleMedium)),
          Chip(label: Text(boarding), visualDensity: VisualDensity.compact),
        ],
      ),
      Text('${bus.vehicleNumber ?? 'School bus'} · ${bus.speed.round()} km/h · updated ${bus.lastUpdate}', style: text.bodySmall),
      const SizedBox(height: 8),
      Row(
        children: [
          Expanded(child: _fact(context, 'ETA to ${bus.stopName ?? 'your stop'}', bus.eta ?? '—')),
          Expanded(child: _fact(context, 'Next stop', bus.nextStop ?? '—')),
        ],
      ),
      if (bus.driverPhone != null) ...[
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () => launchUrl(Uri(scheme: 'tel', path: bus.driverPhone)),
          icon: const Icon(Icons.call),
          label: Text('Call ${bus.driverName ?? 'driver'}'),
        ),
      ],
      if (_error != null) Text(_error!, style: text.bodySmall?.copyWith(color: Colors.orange.shade800)),
    ]);
  }

  Widget _fact(BuildContext context, String label, String value) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.bodySmall),
          Text(value, style: Theme.of(context).textTheme.titleSmall),
        ],
      );

  Widget _panel(BuildContext context, List<Widget> children) => SafeArea(
        top: false,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 16),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            boxShadow: const [BoxShadow(blurRadius: 8, color: Colors.black12, offset: Offset(0, -2))],
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: children),
        ),
      );

  Widget _banner(BuildContext context, IconData icon, Color color, String text) => Material(
        elevation: 2,
        borderRadius: BorderRadius.circular(10),
        color: color.withValues(alpha: 0.95),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(children: [
            Icon(icon, color: Colors.white, size: 18),
            const SizedBox(width: 8),
            Expanded(child: Text(text, style: const TextStyle(color: Colors.white))),
          ]),
        ),
      );
}
