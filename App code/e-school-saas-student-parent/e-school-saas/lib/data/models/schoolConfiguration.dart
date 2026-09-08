import 'package:eschool/data/models/paymentGateway.dart';
import 'package:eschool/data/models/schoolSettings.dart';
import 'package:eschool/data/models/semesterDetails.dart';
import 'package:eschool/data/models/sessionYear.dart';
import 'package:eschool/utils/systemModules.dart';

class SchoolConfiguration {
  final SessionYear sessionYear;
  final SchoolSettings schoolSettings;
  final SemesterDetails semesterDetails;
  final Map<String, String> enabledModules;
  final List<PaymentGeteway> enabledPaymentGateways;
  SchoolConfiguration(
      {required this.sessionYear,
      required this.semesterDetails,
      required this.schoolSettings,
      required this.enabledPaymentGateways,
      required this.enabledModules});

  static SchoolConfiguration fromJson(Map<String, dynamic> json) {
    return SchoolConfiguration(
      enabledPaymentGateways: ((json['payment_gateway'] ?? []) as List)
          .map((e) => PaymentGeteway.fromJson(Map.from(e ?? {})))
          .toList(),
      semesterDetails: SemesterDetails.fromJson(json['semester'] ?? {}),
      enabledModules: Map<String, String>.from(json['features'] ?? {}),
      schoolSettings: SchoolSettings.fromJson(Map.from(json['settings'] ?? {})),
      sessionYear: SessionYear.fromJson(Map.from(json['session_year'] ?? {})),
    );
  }

  bool isAssignmentModuleEnabled() {
    return enabledModules.containsKey(assignmentManagementModuleId.toString());
  }

  bool isOnlineFeePaymentEnable() {
    return enabledPaymentGateways.isNotEmpty;
  }
}
