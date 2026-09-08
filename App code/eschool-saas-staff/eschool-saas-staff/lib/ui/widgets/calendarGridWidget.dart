import 'package:eschool_saas_staff/data/models/teacherAttendance.dart';
import 'package:flutter/material.dart';

class CalendarGridWidget extends StatelessWidget {
  final DateTime selectedDate;
  final List<TeacherAttendance> attendance;

  const CalendarGridWidget({
    Key? key,
    required this.selectedDate,
    required this.attendance,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    // Get first day of the month and number of days
    final firstDayOfMonth = DateTime(selectedDate.year, selectedDate.month, 1);
    final lastDayOfMonth =
        DateTime(selectedDate.year, selectedDate.month + 1, 0);
    final daysInMonth = lastDayOfMonth.day;
    final firstWeekday = firstDayOfMonth.weekday % 7; // Sunday = 0

    // Create a map of attendance by day
    Map<int, TeacherAttendance> attendanceMap = {};
    for (var att in attendance) {
      final date = att.getAttendanceDate();
      if (date != null &&
          date.month == selectedDate.month &&
          date.year == selectedDate.year) {
        attendanceMap[date.day] = att;
      }
    }

    List<Widget> calendarDays = [];

    // Add previous month's last few days (faded)
    final previousMonth =
        DateTime(selectedDate.year, selectedDate.month - 1, 0);
    final daysInPreviousMonth = previousMonth.day;
    for (int i = firstWeekday - 1; i >= 0; i--) {
      final day = daysInPreviousMonth - i;
      calendarDays.add(
        Container(
          margin: const EdgeInsets.all(2),
          child: Center(
            child: Text(
              day.toString(),
              style: TextStyle(
                fontSize: 16,
                color: Theme.of(context)
                    .colorScheme
                    .onSurface
                    .withValues(alpha: 0.3),
              ),
            ),
          ),
        ),
      );
    }

    // Add days of the current month
    for (int day = 1; day <= daysInMonth; day++) {
      final hasAttendance = attendanceMap.containsKey(day);
      final isPresent = hasAttendance ? attendanceMap[day]!.isPresent() : false;
      final isAbsent = hasAttendance ? attendanceMap[day]!.isAbsent() : false;
      final isToday = DateTime.now().day == day &&
          DateTime.now().month == selectedDate.month &&
          DateTime.now().year == selectedDate.year;

      // Determine if this date should be bold
      final shouldBeBold =
          day == 16 || day % 7 == 0; // Every Sunday or specific dates

      // Determine background color using if-else
      Color backgroundColor;
      if (isPresent) {
        backgroundColor = Color(0xff57CC99); // Green for present
      } else if (isAbsent) {
        backgroundColor = Color(0xffFF6768); // Red for absent
      } else if (isToday) {
        backgroundColor = Theme.of(context)
            .colorScheme
            .primary
            .withValues(alpha: 0.8); // Highlight today
      } else {
        backgroundColor = Colors.transparent;
      }

      // Determine text color using if-else
      Color textColor;
      if (hasAttendance) {
        textColor = Colors.white;
      } else if (isToday) {
        textColor = Colors.white;
      } else {
        textColor = Theme.of(context).colorScheme.onSurface;
      }

      calendarDays.add(
        Container(
          margin: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: backgroundColor,
          ),
          child: Center(
            child: Text(
              day.toString(),
              style: TextStyle(
                fontSize: 16,
                fontWeight: shouldBeBold ? FontWeight.bold : FontWeight.normal,
                color: textColor,
              ),
            ),
          ),
        ),
      );
    }

    // Add next month's first few days (faded)
    final remainingCells =
        42 - calendarDays.length; // 6 rows * 7 days = 42 total cells
    for (int i = 1; i <= remainingCells; i++) {
      calendarDays.add(
        Container(
          margin: const EdgeInsets.all(2),
          child: Center(
            child: Text(
              i.toString(),
              style: TextStyle(
                fontSize: 16,
                color: Theme.of(context)
                    .colorScheme
                    .onSurface
                    .withValues(alpha: 0.3),
              ),
            ),
          ),
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(10),
      child: GridView.count(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisCount: 7,
        childAspectRatio: 1,
        children: calendarDays,
      ),
    );
  }
}
