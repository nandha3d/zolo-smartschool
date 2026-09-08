import 'package:flutter/material.dart';
import 'package:eschool_saas_staff/data/models/transportRequest.dart'
    as TransportRequestModel;
import 'package:eschool_saas_staff/ui/widgets/customAppbar.dart';
import 'package:eschool_saas_staff/ui/widgets/customTextContainer.dart';
import 'package:eschool_saas_staff/ui/widgets/customRoundedButton.dart';
import 'package:eschool_saas_staff/utils/constants.dart';
import 'package:eschool_saas_staff/utils/labelKeys.dart';
import 'package:eschool_saas_staff/utils/utils.dart';
import 'package:eschool_saas_staff/ui/screens/staffTransportEnroll/transportHome/widgets/statusTag.dart';

class TransportRequestDetailsScreen extends StatelessWidget {
  final RequestDetailsArgs args;
  const TransportRequestDetailsScreen({super.key, required this.args});

  static Widget getRouteInstance({required RequestDetailsArgs args}) =>
      TransportRequestDetailsScreen(args: args);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: Column(
        children: [
          CustomAppbar(titleKey: Utils.getTranslatedLabel(requestDetailsKey), showBackButton: true),
          Expanded(
            child: SingleChildScrollView(
              padding: EdgeInsets.symmetric(
                horizontal: appContentHorizontalPadding,
                vertical: 16,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CustomTextContainer(
                    textKey: args.title,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: Theme.of(context).colorScheme.onSurface,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Container(
                    width: double.maxFinite,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.surface,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                          color: Theme.of(context).colorScheme.tertiary),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                          Row(
                          children: [
                            Expanded(
                              child: _labelValue(
                                  context, '${Utils.getTranslatedLabel(requestedOnKey)} :', args.requestedOn),
                            ),
                            StatusTag(
                              text: args.statusText,
                              bg: args.statusBg,
                              fg: args.statusFg,
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        if (args.sections.isNotEmpty)
                          ...args.sections.map((section) => Padding(
                                padding: const EdgeInsets.only(bottom: 12),
                                child: _buildSection(context, section),
                              )),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: Container(
        width: double.maxFinite,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface,
          border: Border.all(color: Theme.of(context).colorScheme.tertiary),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (args.footerNote != null) ...[
              CustomTextContainer(
                textKey: args.footerNote!,
                style: TextStyle(
                  fontSize: 12,
                  color: args.statusText.toLowerCase() == 'rejected'
                      ? Theme.of(context).colorScheme.error
                      : Theme.of(context)
                          .colorScheme
                          .onSurface
                          .withValues(alpha: 0.6),
                ),
              ),
              const SizedBox(height: 12),
            ],
            Row(
              children: [
                if (args.showNewRequest)
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {},
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12)),
                        side: BorderSide(
                            color: Theme.of(context).colorScheme.tertiary),
                      ),
                      child: CustomTextContainer(
                        textKey: Utils.getTranslatedLabel(newRequestKey),
                        style: const TextStyle(
                            fontSize: 15, fontWeight: FontWeight.w600),
                      ),
                    ),
                  ),
                if (args.showNewRequest) const SizedBox(width: 12),
                Expanded(
                  child: CustomRoundedButton(
                    textSize: 15,
                    onTap: () {
                      if (args.transportRequest?.contactDetails.schoolPhone
                              .isNotEmpty ==
                          true) {
                        Utils.launchCallLog(
                            mobile: args
                                .transportRequest!.contactDetails.schoolPhone);
                      } else {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(Utils.getTranslatedLabel(phoneNumberNotAvailableKey)),
                            backgroundColor: Colors.red,
                          ),
                        );
                      }
                    },
                    backgroundColor: Theme.of(context).colorScheme.primary,
                    buttonTitle: contactSupportKey,
                    showBorder: false,
                    widthPercentage: 1.0,
                    height: 50,
                    radius: 12,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _labelValue(BuildContext context, String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CustomTextContainer(
          textKey: label,
          style: const TextStyle(fontSize: 12, color: Color(0xFF6D6E6F)),
        ),
        const SizedBox(height: 2),
        CustomTextContainer(
          textKey: value,
          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
        ),
      ],
    );
  }

  Widget _buildSection(BuildContext context, Map<String, dynamic> section) {
    final String title = section['title'] ?? '';
    final List<Map<String, dynamic>> items = section['items'] ?? [];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (title.isNotEmpty) ...[
          CustomTextContainer(
            textKey: title,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Theme.of(context).colorScheme.onSurface,
            ),
          ),
          const SizedBox(height: 8),
        ],
        Container(
          width: double.maxFinite,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: Theme.of(context).colorScheme.tertiary),
          ),
          child: Column(
            children: items.asMap().entries.map((entry) {
              final int index = entry.key;
              final Map<String, dynamic> item = entry.value;
              final String label = item['label'] ?? '';
              final String value = item['value'] ?? '';
              return Column(
                children: [
                  _labelValue(context, label, value),
                  if (index < items.length - 1) const SizedBox(height: 12),
                ],
              );
            }).toList(),
          ),
        ),
      ],
    );
  }
}

class RequestDetailsArgs {
  final String title;
  final String requestedOn;
  final String statusText;
  final Color statusBg;
  final Color statusFg;
  final List<Map<String, dynamic>> sections;
  final String? footerNote;
  final bool showNewRequest;
  final TransportRequestModel.TransportRequest? transportRequest;

  const RequestDetailsArgs({
    required this.title,
    required this.requestedOn,
    required this.statusText,
    required this.statusBg,
    required this.statusFg,
    required this.sections,
    this.footerNote,
    this.showNewRequest = false,
    this.transportRequest,
  });
}
