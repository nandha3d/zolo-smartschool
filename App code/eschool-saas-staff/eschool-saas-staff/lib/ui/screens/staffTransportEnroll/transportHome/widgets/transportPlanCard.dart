import 'package:eschool_saas_staff/app/routes.dart';
import 'package:eschool_saas_staff/cubits/transport/routeStopsCubit.dart';
import 'package:eschool_saas_staff/ui/screens/staffTransportEnroll/transportHome/widgets/commonTransportWidgets.dart';
import 'package:eschool_saas_staff/utils/labelKeys.dart';
import 'package:eschool_saas_staff/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class TransportPlanCard extends StatelessWidget {
  const TransportPlanCard({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<RouteStopsCubit, RouteStopsState>(
      builder: (context, state) {
        final cubit = context.read<RouteStopsCubit>();
        final data = cubit.getRouteData();
        final userStop = data?.userStop;

        // Determine status based on data availability
        String statusText = Utils.getTranslatedLabel(activeKey);
        Color statusBackground = const Color(0xFFE8F5E8);
        Color statusForeground = const Color(0xFF2E7D32);

        if (data == null) {
          statusText = Utils.getTranslatedLabel(notAvailableKey);
          statusBackground = const Color(0xFFFFF3E0);
          statusForeground = const Color(0xFFE65100);
        }

        return EnrollCard(
          onTap: () {
            if (data != null) {
              // Pass existing data to avoid API call
              Get.toNamed(
                Routes.transportPlanDetailsScreen,
                arguments: {'routeStopsData': data},
              );
            } else {
              // Fallback to normal navigation if no data
              Get.toNamed(Routes.transportPlanDetailsScreen);
            }
          },
          title: Utils.getTranslatedLabel(transportationPlanKey),
          trailing: EnrollStatusChip(
            title: statusText,
            background: statusBackground,
            foreground: statusForeground,
          ),
          children: [
            LabelValue(
              label: Utils.getTranslatedLabel(routeNameKey),
              value: data?.route.displayName ?? Utils.getTranslatedLabel(notAvailableKey),
            ),
            LabelValue(
              label: Utils.getTranslatedLabel(vehicleKey),
              value: data != null
                  ? '${data.route.vehicleDisplayName} (${data.route.registrationDisplay})'
                  : Utils.getTranslatedLabel(notAvailableKey),
            ),
            LabelValue(
              label: Utils.getTranslatedLabel(pickupPointKey),
              value: userStop?.displayName ?? Utils.getTranslatedLabel(notAvailableKey),
            ),
            LabelValue(
              label: Utils.getTranslatedLabel(pickupTimeKey),
              value: userStop?.timeDisplay ?? Utils.getTranslatedLabel(notAvailableKey),
            ),
          ],
        );
      },
    );
  }
}
