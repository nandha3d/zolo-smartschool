import 'package:zolo_smart_school_staff/ui/widgets/customBottomsheet.dart';
import 'package:zolo_smart_school_staff/ui/widgets/customTextContainer.dart';
import 'package:zolo_smart_school_staff/utils/constants.dart';
import 'package:zolo_smart_school_staff/utils/labelKeys.dart';
import 'package:flutter/material.dart';

class LeaveReasonBottomsheet extends StatelessWidget {
  final String reason;
  const LeaveReasonBottomsheet({super.key, required this.reason});

  @override
  Widget build(BuildContext context) {
    return CustomBottomsheet(
        titleLabelKey: leaveReasonKey,
        child: Container(
          padding: EdgeInsets.all(appContentHorizontalPadding),
          child: CustomTextContainer(textKey: reason),
        ));
  }
}
