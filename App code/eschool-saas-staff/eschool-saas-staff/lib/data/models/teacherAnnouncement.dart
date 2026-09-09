import 'package:zolo_smart_school_staff/data/models/studyMaterial.dart';
import 'package:zolo_smart_school_staff/utils/utils.dart';

class TeacherAnnouncement {
  TeacherAnnouncement({
    required this.id,
    required this.title,
    required this.description,
    required this.createdAt,
  });
  late final int id;
  late final DateTime createdAt;
  late final String title;
  late final String description;
  late final List<StudyMaterial> files;

  TeacherAnnouncement.fromJson(Map<String, dynamic> json) {
    id = json['id'] ?? 0;
    title = json['title'] ?? "";
    createdAt = json['created_at'] == null
        ? DateTime.now()
        : Utils.parseDateSafely(json['created_at']) ?? DateTime.now();
    description = json['description'] ?? "";
    files = ((json['file'] ?? []) as List)
        .map((file) => StudyMaterial.fromJson(Map.from(file)))
        .toList();
  }
}
