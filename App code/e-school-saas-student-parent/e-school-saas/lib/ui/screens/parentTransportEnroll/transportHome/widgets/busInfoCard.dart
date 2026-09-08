import 'package:eschool/data/models/transportDashboard.dart';
import 'package:eschool/ui/screens/parentTransportEnroll/transportHome/widgets/commonTransportWidgets.dart';
import 'package:eschool/ui/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';

class BusInfoCard extends StatelessWidget {
  final BusInfo? busInfo;

  const BusInfoCard({super.key, this.busInfo});

  @override
  Widget build(BuildContext context) {
    return EnrollCard(
      title: 'Bus Info',
      trailing: const SizedBox(),
      children: [
        CustomTextContainer(
          textKey:
              busInfo != null ? 'Bus No : ${busInfo!.registration}' : 'N/A',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w500,
        ),
        ),
        PersonRow(
          label: 'Driver',
          name: busInfo?.driver?.name ?? 'N/A',
          phone: busInfo?.driver?.phone,
          avatar: busInfo?.driver?.avatar,
          userId: busInfo?.driver?.id,
        ),
        PersonRow(
          label: 'Attender',
          name: busInfo?.attender?.name ?? 'N/A',
          phone: busInfo?.attender?.phone,
          avatar: busInfo?.attender?.avatar,
          userId: busInfo?.attender?.id,
        ),
      ],
    );
  }
}
