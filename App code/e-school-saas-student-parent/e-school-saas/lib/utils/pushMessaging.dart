/// A no-op stand-in for `package:firebase_messaging`.
///
/// The apps shipped wired to the original vendor's Firebase project, which we cannot
/// and should not use. Rather than leave the build broken until someone creates a
/// Google project, push messaging is compiled out: this file provides the same API
/// surface the notification code already calls, and every method does nothing.
///
/// Local notifications are unaffected - those go through awesome_notifications and
/// still work, including the chat download progress notifications.
///
/// To turn real push messaging back on: add firebase_core and firebase_messaging to
/// pubspec.yaml, run `flutterfire configure`, restore the
/// `com.google.gms.google-services` Gradle plugin, and change the imports in
/// notificationUtility.dart and authRepository.dart back to
/// `package:firebase_messaging/firebase_messaging.dart`.
library;

/// Mirrors the subset of AuthorizationStatus the app branches on.
enum AuthorizationStatus { authorized, denied, notDetermined, provisional }

class RemoteNotification {
  final String? title;
  final String? body;

  const RemoteNotification({this.title, this.body});
}

class RemoteMessage {
  final Map<String, dynamic> data;
  final RemoteNotification? notification;
  final DateTime? sentTime;
  final String? messageId;

  const RemoteMessage({
    this.data = const {},
    this.notification,
    this.sentTime,
    this.messageId,
  });

  Map<String, dynamic> toMap() => {'data': data};
}

class NotificationSettings {
  final AuthorizationStatus authorizationStatus;

  const NotificationSettings({
    this.authorizationStatus = AuthorizationStatus.denied,
  });
}

/// Signature kept identical to firebase_messaging's background handler.
typedef BackgroundMessageHandler = Future<void> Function(RemoteMessage message);

class FirebaseMessaging {
  const FirebaseMessaging._();

  static const FirebaseMessaging instance = FirebaseMessaging._();

  /// No push transport, so there is no token. Callers already treat an empty or
  /// null token as "device not registered".
  Future<String?> getToken({String? vapidKey}) async => null;

  Future<NotificationSettings> getNotificationSettings() async =>
      const NotificationSettings();

  Future<NotificationSettings> requestPermission({
    bool alert = true,
    bool announcement = false,
    bool badge = true,
    bool carPlay = false,
    bool criticalAlert = false,
    bool provisional = false,
    bool sound = true,
  }) async =>
      const NotificationSettings();

  Future<void> setForegroundNotificationPresentationOptions({
    bool alert = false,
    bool badge = false,
    bool sound = false,
  }) async {}

  Future<RemoteMessage?> getInitialMessage() async => null;

  Future<void> subscribeToTopic(String topic) async {}

  Future<void> unsubscribeFromTopic(String topic) async {}

  /// Streams that never emit, so `.listen(...)` stays valid and simply never fires.
  static Stream<RemoteMessage> get onMessage => const Stream<RemoteMessage>.empty();

  static Stream<RemoteMessage> get onMessageOpenedApp =>
      const Stream<RemoteMessage>.empty();

  static void onBackgroundMessage(BackgroundMessageHandler handler) {}
}
