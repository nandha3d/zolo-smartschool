import 'package:zolo_smart_school_staff/utils/labelKeys.dart';
import 'package:zolo_smart_school_staff/utils/utils.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

class ConfirmDeleteDialog extends StatelessWidget {
  const ConfirmDeleteDialog({super.key});

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      actions: [
        CupertinoButton(
          child: Text(
            Utils.getTranslatedLabel(yesKey),
            style: TextStyle(color: Theme.of(context).colorScheme.primary),
          ),
          onPressed: () {
            Get.back(result: true);
          },
        ),
        CupertinoButton(
          child: Text(
            Utils.getTranslatedLabel(noKey),
            style: TextStyle(color: Theme.of(context).colorScheme.primary),
          ),
          onPressed: () {
            Get.back();
          },
        ),
      ],
      backgroundColor: Colors.white,
      content: Text(Utils.getTranslatedLabel(deleteDialogMessageKey)),
      title: Text(Utils.getTranslatedLabel(deleteDialogTitleKey)),
    );
  }
}
