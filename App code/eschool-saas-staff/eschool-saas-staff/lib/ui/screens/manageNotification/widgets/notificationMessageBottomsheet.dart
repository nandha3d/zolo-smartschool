import 'package:zolo_smart_school_staff/ui/widgets/customBottomsheet.dart';
import 'package:zolo_smart_school_staff/ui/widgets/customTextContainer.dart';
import 'package:zolo_smart_school_staff/utils/constants.dart';
import 'package:zolo_smart_school_staff/utils/labelKeys.dart';
import 'package:flutter/material.dart';

class NotificationMessageBottomsheet extends StatelessWidget {
  final String text;
  const NotificationMessageBottomsheet({super.key, required this.text});

  @override
  Widget build(BuildContext context) {
    return CustomBottomsheet(
        titleLabelKey: messageKey,
        child: Padding(
          padding: EdgeInsets.all(appContentHorizontalPadding),
          child: CustomTextContainer(textKey: text),
        ));
  }
}
