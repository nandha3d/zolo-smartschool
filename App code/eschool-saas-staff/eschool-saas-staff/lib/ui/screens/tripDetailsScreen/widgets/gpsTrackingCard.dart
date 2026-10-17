import 'dart:async';

import 'package:flutter/material.dart';
import 'package:zolo_smart_school_staff/data/models/gpsTrip.dart';
import 'package:zolo_smart_school_staff/data/repositories/gpsRepository.dart';
import 'package:zolo_smart_school_staff/utils/gps/gpsTrackingService.dart';
import 'package:zolo_smart_school_staff/utils/gps/gpsTripActions.dart';

/// Shows whether this bus is sharing its live position with parents, and lets the
/// driver start / stop it. Hidden when the school has not scheduled a GPS trip for
/// the route (or the GPS addon is not enabled).
class GpsTrackingCard extends StatefulWidget {
  final int? routeId;

  const GpsTrackingCard({super.key, required this.routeId});

  @override
  State<GpsTrackingCard> createState() => GpsTrackingCardState();
}

class GpsTrackingCardState extends State<GpsTrackingCard> {
  GpsTrip? _trip;
  GpsTrackingConfig _config = const GpsTrackingConfig();
  bool _loading = true;
  bool _busy = false;
  String? _loadError;
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    reload();
    // Re-render "last fix 12 s ago" while tracking.
    _ticker = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted && GpsTrackingService.instance.status.value.tracking) setState(() {});
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  Future<void> reload() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      final today = await GpsTripActions.load();
      if (!mounted) return;
      setState(() {
        _trip = GpsTripActions.pick(today.trips, widget.routeId);
        _config = today.config;
        _loading = false;
      });
    } on GpsApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        // 403: not a driver account; 404 / feature off: just hide the card.
        _loadError = (e.statusCode == 403 || e.statusCode == 404) ? null : e.message;
      });
    }
  }

  Future<void> _start() async {
    final trip = _trip;
    if (trip == null) return;
    setState(() => _busy = true);
    final error = await GpsTripActions.start(trip, _config);
    if (!mounted) return;
    setState(() => _busy = false);
    _snack(error ?? 'Sharing the bus position with parents.', error == null);
    await reload();
  }

  Future<void> _stop({String? overrideReason}) async {
    setState(() => _busy = true);
    try {
      final message = await GpsTripActions.end(overrideReason: overrideReason);
      _snack(message.isEmpty ? 'Location sharing stopped.' : message, true);
    } on GpsApiException catch (e) {
      if (e.stillOnboard && mounted) {
        final reason = await _askOverride(e.message);
        if (reason != null && reason.isNotEmpty) {
          setState(() => _busy = false);
          return _stop(overrideReason: reason);
        }
      } else {
        _snack(e.message, false);
      }
    }
    if (!mounted) return;
    setState(() => _busy = false);
    await reload();
  }

  Future<String?> _askOverride(String warning) {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Students still on board'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(warning),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              decoration: const InputDecoration(labelText: 'Reason to end anyway'),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, controller.text.trim()), child: const Text('End trip')),
        ],
      ),
    );
  }

  void _snack(String text, bool ok) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(text), backgroundColor: ok ? Colors.green : Colors.red),
    );
  }

  String _ago(DateTime? at) {
    if (at == null) return '—';
    final seconds = DateTime.now().difference(at).inSeconds;
    if (seconds < 60) return '${seconds}s ago';
    if (seconds < 3600) return '${seconds ~/ 60} min ago';
    return '${seconds ~/ 3600} h ago';
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const SizedBox.shrink();
    }
    if (_trip == null) {
      if (_loadError == null) return const SizedBox.shrink();
      return _shell(
        context,
        icon: Icons.gps_off,
        color: Colors.orange,
        title: 'Live bus location unavailable',
        child: Text(_loadError!, style: Theme.of(context).textTheme.bodySmall),
        action: TextButton(onPressed: reload, child: const Text('Retry')),
      );
    }

    return ValueListenableBuilder<GpsTrackingStatus>(
      valueListenable: GpsTrackingService.instance.status,
      builder: (context, status, _) {
        final tracking = status.tracking && status.tripId == _trip!.id;
        final textStyle = Theme.of(context).textTheme.bodySmall;
        return _shell(
          context,
          icon: tracking ? Icons.gps_fixed : Icons.gps_not_fixed,
          color: tracking ? Colors.green : Theme.of(context).colorScheme.primary,
          title: tracking ? 'Sharing live location' : 'Live location for parents',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${_trip!.vehicleNumber} · ${_trip!.routeName} · ${_trip!.tripType}', style: textStyle),
              if (tracking) ...[
                const SizedBox(height: 4),
                Text(
                  'Last fix ${_ago(status.lastFixAt)}'
                  '${status.lastAccuracy != null ? ' (±${status.lastAccuracy!.round()} m)' : ''}'
                  ' · sent ${_ago(status.lastUploadAt)}'
                  '${status.queued > 0 ? ' · ${status.queued} waiting' : ''}',
                  style: textStyle,
                ),
              ],
              if (status.mockLocation)
                Text('A mock-location app is active; the school will see this.',
                    style: textStyle?.copyWith(color: Colors.red)),
              if (status.error != null)
                Text(status.error!, style: textStyle?.copyWith(color: Colors.orange.shade800)),
            ],
          ),
          action: _busy
              ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2))
              : tracking
                  ? OutlinedButton(onPressed: () => _stop(), child: const Text('Stop'))
                  : FilledButton(onPressed: _start, child: const Text('Start')),
        );
      },
    );
  }

  Widget _shell(BuildContext context,
      {required IconData icon, required Color color, required String title, required Widget child, required Widget action}) {
    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Row(
        children: [
          Icon(icon, color: color),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
                const SizedBox(height: 2),
                child,
              ],
            ),
          ),
          const SizedBox(width: 8),
          action,
        ],
      ),
    );
  }
}
