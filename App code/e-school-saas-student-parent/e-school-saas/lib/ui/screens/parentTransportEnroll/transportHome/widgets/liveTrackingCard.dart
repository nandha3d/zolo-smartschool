import 'package:zolo_smart_school/data/models/transportDashboard.dart';
import 'package:zolo_smart_school/ui/screens/parentTransportEnroll/transportHome/widgets/commonTransportWidgets.dart';
import 'package:zolo_smart_school/ui/screens/parentTransportEnroll/transportHome/widgets/pickupTimeRow.dart';
import 'package:zolo_smart_school/ui/screens/parentTransportEnroll/transportHome/widgets/liveRouteBottomSheet.dart';
import 'package:zolo_smart_school/data/repositories/authRepository.dart';
import 'package:zolo_smart_school/ui/screens/parentTransportEnroll/transportHome/liveBusMapScreen.dart';
import 'package:zolo_smart_school/utils/labelKeys.dart';
import 'package:zolo_smart_school/utils/utils.dart';
import 'package:flutter/material.dart';

class LiveTrackingCard extends StatelessWidget {
  final LiveSummary? liveSummary;
  final int? studentId;

  const LiveTrackingCard({super.key, this.liveSummary, this.studentId});

  @override
  Widget build(BuildContext context) {
    return EnrollCard(
      title: liveTrackingKey,
      trailing: SizedBox.shrink(),
      children: [
        if (liveSummary != null) ...[
          LiveTrackingContent(liveSummary: liveSummary),
          SizedBox(height: 8),
          PickupTimeRow(
            pickupTime: liveSummary?.pickupTime,
            onTap: () {
            _showLiveRouteBottomSheet(context);
          }),
          if (!AuthRepository.getIsStudentLogIn()) ...[
            SizedBox(height: 8),
            _liveMapButton(context),
          ],
        ] else ...[
          if (!AuthRepository.getIsStudentLogIn()) _liveMapButton(context),
          Center(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 20),
              child: Column(
                children: [
                  Icon(
                    Icons.directions_bus_outlined,
                    size: 48,
                    color: Theme.of(context)
                        .colorScheme
                        .onSurface
                        .withValues(alpha: 0.3),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    Utils.getTranslatedLabel(noOngoingTripKey),
                    style: TextStyle(
                      fontSize: 14,
                      color: Theme.of(context)
                          .colorScheme
                          .onSurface
                          .withValues(alpha: 0.6),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ]
      ],
    );
  }

  /// Opens the GPS map. The screen itself finds which children ride a tracked bus,
  /// so it works whether [studentId] is a user id or a student id.
  Widget _liveMapButton(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: () => LiveBusMapScreen.open(context, preferredStudentId: studentId),
        icon: const Icon(Icons.map_outlined),
        label: const Text('See the bus on the map'),
      ),
    );
  }

  void _showLiveRouteBottomSheet(BuildContext context) {
    // Use the student ID from the widget parameter first, then fallback to auth repository
    int? userId = studentId;

    if (userId == null) {
      final student = AuthRepository.getStudentDetails();
      userId = student.id;
    }

    if (userId == null || userId == 0) {
      print("Error: No valid student ID found for live route tracking");
      return;
    }

    LiveRouteBottomSheet.show(context, userId: userId);
  }
}
