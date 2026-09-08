import 'package:eschool_saas_staff/data/models/holiday.dart';
import 'package:eschool_saas_staff/data/models/sessionYear.dart';
import 'package:eschool_saas_staff/data/models/teacherAttendance.dart';
import 'package:eschool_saas_staff/utils/api.dart';

class TeacherAttendanceRepository {
  Future<
      ({
        List<TeacherAttendance> attendance,
        List<Holiday> holidays,
        SessionYear sessionYear
      })> getTeacherAttendance({
    required int month,
    required int year,
  }) async {
    try {
      final result = await Api.get(
        url: Api.getTeacherAttendance,
        useAuthToken: true,
        queryParameters: {
          "month": month,
          "year": year,
        },
      );

      final data = result['data'] as Map<String, dynamic>;

      return (
        attendance: (data['attendance'] as List)
            .map(
              (attendanceReport) =>
                  TeacherAttendance.fromJson(attendanceReport),
            )
            .toList(),
        holidays: (data['holidays'] as List)
            .map(
              (holiday) => Holiday.fromJson(holiday),
            )
            .toList(),
        sessionYear:
            SessionYear.fromJson(data['session_year'] as Map<String, dynamic>),
      );
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
