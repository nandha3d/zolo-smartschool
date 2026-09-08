import 'package:eschool/utils/labelKeys.dart';

//database urls
//Please add your admin panel url here and make sure you do not add '/' at the end of the url

const String baseUrl = "https://zoloschools.com";

// Point baseUrl at your own deployment. It must be the site root, without a
// trailing slash; databaseUrl below appends /api/.
const String databaseUrl = "$baseUrl/api/";

// our Socket url
const String socketUrl = "ws://127.0.0.1:8090";

//error message display duration
const Duration errorMessageDisplayDuration = Duration(milliseconds: 3000);

//Web socket ping interval
const Duration socketPingInterval = Duration(seconds: 275);

//home menu bottom sheet animation duration
const Duration homeMenuBottomSheetAnimationDuration = Duration(
  milliseconds: 300,
);

//Change slider duration
const Duration changeSliderDuration = Duration(seconds: 5);

//Number of latest notices to show in home container
const int numberOfLatestNoticesInHomeScreen = 3;

//Maximum characters to show in announcement description before "Read More"
const int maxAnnouncementDescriptionLength = 100;

//notification channel keys
const String notificationChannelKey = "basic_channel";

//to enable and disable default credentials in login page
const bool showDefaultCredentials = true;
//default credentials of student
const String defaultStudentGRNumber = "DEMO2026001";
const String defaultStudentPassword = "Demo@12345";
//default credentials of parent
const String defaultParentEmail = "parent@demo.test";
const String defaultParentPassword = "Demo@12345";
// Default school code
const String defaultSchoolCode = "DEMO001";

//animations configuration
//if this is false all item appearance animations will be turned off
const bool isApplicationItemAnimationOn = true;
//note: do not add Milliseconds values less then 10 as it'll result in errors
const int listItemAnimationDelayInMilliseconds = 100;
const int itemFadeAnimationDurationInMilliseconds = 250;
const int itemZoomAnimationDurationInMilliseconds = 200;
const int itemBouncScaleAnimationDurationInMilliseconds = 200;
const double appContentHorizontalPadding = 15.0;
double bottomsheetBorderRadius = 15.0;
double topPaddingOfErrorAndLoadingContainer = 150;

String getExamStatusTypeKey(String examStatus) {
  if (examStatus == "0") {
    return upComingKey;
  }
  if (examStatus == "1") {
    return onGoingKey;
  }
  return completedKey;
}

List<String> examFilters = [allExamsKey, upComingKey, onGoingKey, completedKey];

int getExamStatusBasedOnFilterKey({required String examFilter}) {
  ///[Exam status: 0- Upcoming, 1-On Going, 2-Completed, 3-All Details]
  if (examFilter == upComingKey) {
    return 0;
  }

  if (examFilter == onGoingKey) {
    return 1;
  }

  if (examFilter == completedKey) {
    return 2;
  }

  return 3;
}

const int minimumPasswordLength = 6;

const String stripePaymentMethodKey = "Stripe";
const String razorpayPaymentMethodKey = "Razorpay";
const String flutterwavePaymentMethodKey = "Flutterwave";
const String paystackPaymentMethodKey = "Paystack";

///[Payment transaction status this must be in sync with backend]
const String pendingTransactionStatusKey = "pending";
const String failedTransactionStatusKey = "failed";
const String succeedTransactionStatusKey = "succeed";

///[Socket events]
enum SocketEvent { register, message }

List<String> months = [
  januaryKey,
  februaryKey,
  marchKey,
  aprilKey,
  mayKey,
  juneKey,
  julyKey,
  augustKey,
  septemberKey,
  octoberKey,
  novemberKey,
  decemberKey,
];
