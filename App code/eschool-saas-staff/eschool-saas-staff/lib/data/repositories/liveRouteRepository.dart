import 'package:eschool_saas_staff/data/models/liveRoute.dart';
import 'package:eschool_saas_staff/utils/api.dart';

class LiveRouteRepository {
  Future<LiveRouteResponse> getLiveRoute({
    required int userId,
  }) async {
    try {
      final result = await Api.post(
        url: Api.getLiveRoute,
        useAuthToken: true,
        body: {
          'user_id': userId.toString(),
        },
      );

      return LiveRouteResponse.fromJson(result);
    } catch (e, st) {
      print("Live Route Error: $e");
      print("Stack trace: $st");
      throw ApiException(e.toString());
    }
  }
}
