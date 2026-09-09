import 'package:zolo_smart_school_staff/ui/widgets/customTextContainer.dart';
import 'package:zolo_smart_school_staff/utils/labelKeys.dart';
import 'package:zolo_smart_school_staff/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

class AppUnderMaintenanceContainer extends StatelessWidget {
  const AppUnderMaintenanceContainer({super.key});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          SvgPicture.asset(
            Utils.getImagePath("maintenance.svg"),
            fit: BoxFit.cover,
          ),
          SizedBox(
            height: MediaQuery.of(context).size.height * (0.0125),
          ),
          const Padding(
            padding: EdgeInsets.all(20),
            child: CustomTextContainer(
              textKey: appUnderMaintenanceKey,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 20,
              ),
            ),
          )
        ],
      ),
    );
  }
}
