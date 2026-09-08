import 'package:eschool_saas_staff/data/models/holiday.dart';
import 'package:eschool_saas_staff/data/models/sessionYear.dart';
import 'package:eschool_saas_staff/data/models/teacherAttendance.dart';
import 'package:eschool_saas_staff/data/repositories/teacherAttendanceRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class TeacherAttendanceState {}

class TeacherAttendanceInitial extends TeacherAttendanceState {}

class TeacherAttendanceFetchInProgress extends TeacherAttendanceState {}

class TeacherAttendanceFetchSuccess extends TeacherAttendanceState {
  final List<TeacherAttendance> attendance;
  final List<Holiday> holidays;
  final SessionYear sessionYear;

  TeacherAttendanceFetchSuccess({
    required this.attendance,
    required this.holidays,
    required this.sessionYear,
  });

  TeacherAttendanceFetchSuccess copyWith({
    List<TeacherAttendance>? attendance,
    List<Holiday>? holidays,
    SessionYear? sessionYear,
  }) {
    return TeacherAttendanceFetchSuccess(
      attendance: attendance ?? this.attendance,
      holidays: holidays ?? this.holidays,
      sessionYear: sessionYear ?? this.sessionYear,
    );
  }
}

class TeacherAttendanceFetchFailure extends TeacherAttendanceState {
  final String errorMessage;

  TeacherAttendanceFetchFailure(this.errorMessage);
}

class TeacherAttendanceCubit extends Cubit<TeacherAttendanceState> {
  final TeacherAttendanceRepository _teacherAttendanceRepository =
      TeacherAttendanceRepository();

  TeacherAttendanceCubit() : super(TeacherAttendanceInitial());

  Future<void> fetchTeacherAttendance({
    required int month,
    required int year,
  }) async {
    emit(TeacherAttendanceFetchInProgress());
    try {
      final result = await _teacherAttendanceRepository.getTeacherAttendance(
        month: month,
        year: year,
      );

      emit(
        TeacherAttendanceFetchSuccess(
          attendance: result.attendance,
          holidays: result.holidays,
          sessionYear: result.sessionYear,
        ),
      );
    } catch (e) {
      emit(TeacherAttendanceFetchFailure(e.toString()));
    }
  }

  /// Get present days from attendance
  List<TeacherAttendance> getPresentDays() {
    if (state is TeacherAttendanceFetchSuccess) {
      return (state as TeacherAttendanceFetchSuccess)
          .attendance
          .where((attendance) => attendance.isPresent())
          .toList();
    }
    return [];
  }

  /// Get absent days from attendance
  List<TeacherAttendance> getAbsentDays() {
    if (state is TeacherAttendanceFetchSuccess) {
      return (state as TeacherAttendanceFetchSuccess)
          .attendance
          .where((attendance) => attendance.isAbsent())
          .toList();
    }
    return [];
  }

  /// Get holidays
  List<Holiday> getHolidays() {
    if (state is TeacherAttendanceFetchSuccess) {
      return (state as TeacherAttendanceFetchSuccess).holidays;
    }
    return [];
  }

  /// Get session year
  SessionYear? getSessionYear() {
    if (state is TeacherAttendanceFetchSuccess) {
      return (state as TeacherAttendanceFetchSuccess).sessionYear;
    }
    return null;
  }
}
