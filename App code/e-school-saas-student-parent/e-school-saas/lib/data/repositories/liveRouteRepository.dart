import 'package:zolo_smart_school/data/models/liveRoute.dart';
import 'package:zolo_smart_school/utils/api.dart';

class LiveRouteRepository {
  Future<LiveRouteResponse> getLiveRoute({required int userId}) async {
    try {
      final result = await Api.post(
        url: Api.getLiveRoute,
        useAuthToken: true,
        body: {
          'user_id': userId.toString(),
        },
      );
      return LiveRouteResponse.fromJson(result);
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
