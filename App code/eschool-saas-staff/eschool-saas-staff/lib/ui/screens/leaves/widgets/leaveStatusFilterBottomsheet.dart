import 'package:zolo_smart_school_staff/ui/widgets/customBottomsheet.dart';
import 'package:zolo_smart_school_staff/ui/widgets/filterSelectionTile.dart';
import 'package:zolo_smart_school_staff/utils/labelKeys.dart';
import 'package:zolo_smart_school_staff/utils/utils.dart';
import 'package:flutter/material.dart';

class LeaveStatusFilterBottomsheet extends StatelessWidget {
  final String? leaveStatus;
  const LeaveStatusFilterBottomsheet({super.key, this.leaveStatus});

  @override
  Widget build(BuildContext context) {
    return CustomBottomsheet(
      titleLabelKey:
          "${Utils.getTranslatedLabel(filterByKey)} : ${Utils.getTranslatedLabel(statusKey)}",
      child: Column(
        children: [
          const SizedBox(
            height: 25,
          ),
          FilterSelectionTile(
            isSelected: true,
            title: allKey,
            onTap: () {},
          ),
          FilterSelectionTile(
            isSelected: false,
            title: pendingKey,
            onTap: () {},
          ),
          FilterSelectionTile(
            isSelected: false,
            title: approvedKey,
            onTap: () {},
          ),
        ],
      ),
    );
  }
}
