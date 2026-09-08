import 'package:eschool_saas_staff/app/routes.dart';
import 'package:eschool_saas_staff/ui/widgets/customMenuTile.dart';
import 'package:eschool_saas_staff/utils/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class TransportNavigationTile extends StatefulWidget {
  const TransportNavigationTile({super.key});

  @override
  State<TransportNavigationTile> createState() =>
      _TransportNavigationTileState();
}

class _TransportNavigationTileState extends State<TransportNavigationTile> {
  void _handleTransportNavigation() {
    // final vehicleAssignmentCubit = context.read<VehicleAssignmentStatusCubit>();

    // if (vehicleAssignmentCubit.isTransportEnrollEnabled()) {
    //   // User is assigned to vehicle, go to home screen
    //   Get.toNamed(Routes.transportEnrollHomeScreen);
    // } else {
    //   // User is not assigned, go to enrollment screen
    //   Get.toNamed(Routes.staffTransportEnrollScreen);

    // }
    Get.toNamed(Routes.transportEnrollHomeScreen);
  }

  @override
  Widget build(BuildContext context) {
    // Since this widget is only shown when vehicle assignment status is successfully fetched,
    // we can directly use the navigation handler
    return CustomMenuTile(
      iconImageName: "transportation.svg",
      titleKey: transportationKey,
      onTap: _handleTransportNavigation,
    );
  }
}
