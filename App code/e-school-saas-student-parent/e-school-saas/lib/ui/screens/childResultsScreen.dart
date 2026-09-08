import 'package:eschool/cubits/resultsCubit.dart';
import 'package:eschool/data/models/subject.dart';
import 'package:eschool/data/repositories/studentRepository.dart';
import 'package:eschool/ui/widgets/resultsContainer.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class ChildResultsScreen extends StatelessWidget {
  final int childId;
  final List<Subject>? subjects;
  const ChildResultsScreen({
    Key? key,
    required this.childId,
    required this.subjects,
  }) : super(key: key);

  static Widget routeInstance() {
    final arguments = Get.arguments as Map<String, dynamic>;
    return BlocProvider<ResultsCubit>(
      create: (context) => ResultsCubit(StudentRepository()),
      child: ChildResultsScreen(
        childId: arguments['childId'],
        subjects: arguments['subjects'],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: ResultsContainer(
        childId: childId,
        subjects: subjects,
      ),
    );
  }
}
