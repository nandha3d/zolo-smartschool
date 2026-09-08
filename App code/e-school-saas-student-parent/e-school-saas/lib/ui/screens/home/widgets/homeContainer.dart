import 'package:eschool/app/routes.dart';
import 'package:eschool/cubits/noticeBoardCubit.dart';
import 'package:eschool/cubits/schoolConfigurationCubit.dart';
import 'package:eschool/cubits/studentProfileCubit.dart';
import 'package:eschool/cubits/studentSubjectAndSlidersCubit.dart';
import 'package:eschool/ui/screens/home/widgets/homeContainerTopProfileContainer.dart';
import 'package:eschool/ui/screens/home/widgets/homeScreenDataLoadingContainer.dart';
import 'package:eschool/ui/widgets/errorContainer.dart';
import 'package:eschool/ui/widgets/latestNoticesContainer.dart';
import 'package:eschool/ui/widgets/noDataContainer.dart';
import 'package:eschool/ui/widgets/schoolGalleryContainer.dart';
import 'package:eschool/ui/widgets/slidersContainer.dart';
import 'package:eschool/ui/widgets/studentSubjectsContainer.dart';
import 'package:eschool/utils/labelKeys.dart';
import 'package:eschool/utils/systemModules.dart';
import 'package:eschool/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class HomeContainer extends StatefulWidget {
  //Need this flag in order to show the homeContainer
  //in background when bottom menu is open

  //If it is just for background showing purpose then it will not reactive or not making any api call
  final bool isForBottomMenuBackground;
  const HomeContainer({Key? key, required this.isForBottomMenuBackground})
      : super(key: key);

  @override
  State<HomeContainer> createState() => _HomeContainerState();
}

class _HomeContainerState extends State<HomeContainer> {
  @override
  void initState() {
    super.initState();
    if (!widget.isForBottomMenuBackground) {
      Future.delayed(Duration.zero, () {
        fetchSubjectSlidersAndNoticeBoardDetails();
      });
    }
  }

  void fetchSubjectSlidersAndNoticeBoardDetails() {
    context.read<StudentSubjectsAndSlidersCubit>().fetchSubjectsAndSliders(
        useParentApi: false,
        isSliderModuleEnable: Utils.isModuleEnabled(
            context: context, moduleId: sliderManagementModuleId.toString()));

    if (Utils.isModuleEnabled(
        context: context,
        moduleId: announcementManagementModuleId.toString())) {
      context
          .read<NoticeBoardCubit>()
          .fetchNoticeBoardDetails(useParentApi: false);
    }
  }

  Widget _buildAdvertisemntSliders() {
    final sliders = context.read<StudentSubjectsAndSlidersCubit>().getSliders();
    if (sliders.isEmpty) {
      return const SizedBox();
    }
    return SlidersContainer(sliders: sliders);
  }

  Widget _buildSlidersSubjectsAndLatestNotcies() {
    return BlocConsumer<StudentSubjectsAndSlidersCubit,
        StudentSubjectsAndSlidersState>(
      listener: (context, state) {
        if (state is StudentSubjectsAndSlidersFetchSuccess) {
          if (state.doesClassHaveElectiveSubjects &&
              state.electiveSubjects.isEmpty) {
            if (Get.currentRoute == Routes.selectSubjects) {
              return;
            }
            Get.toNamed(Routes.selectSubjects);
          }
        }
      },
      builder: (context, state) {
        if (state is StudentSubjectsAndSlidersFetchSuccess) {
          final subjects =
              context.read<StudentSubjectsAndSlidersCubit>().getSubjects();
          final sliders =
              context.read<StudentSubjectsAndSlidersCubit>().getSliders();

          // Show NoDataContainer only when both subjects and sliders are empty
          final hasNoData = subjects.isEmpty && sliders.isEmpty;

          if (hasNoData) {
            return Center(
                child: NoDataContainer(
              titleKey: nohomescreendatafoundKey,
            ));
          }

          return RefreshIndicator(
            displacement: Utils.getScrollViewTopPadding(
              context: context,
              appBarHeightPercentage: Utils.appBarBiggerHeightPercentage,
            ),
            color: Theme.of(context).colorScheme.primary,
            onRefresh: () async {
              // Refresh school configuration
              context
                  .read<SchoolConfigurationCubit>()
                  .fetchSchoolConfiguration(useParentApi: false);

              // Refresh student profile data
              context.read<StudentProfileCubit>().refreshStudentProfile(
                    useParentApi: false,
                  );
            },
            child: SingleChildScrollView(
              physics: AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.only(
                top: Utils.getScrollViewTopPadding(
                  context: context,
                  appBarHeightPercentage: Utils.appBarBiggerHeightPercentage,
                ),
                bottom: Utils.getScrollViewBottomPadding(context),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildAdvertisemntSliders(),
                  SizedBox(
                    height: MediaQuery.of(context).size.height * (0.025),
                  ),
                  StudentSubjectsContainer(
                    subjects: subjects,
                    subjectsTitleKey: mySubjectsKey,
                    animate: !widget.isForBottomMenuBackground,
                  ),
                  Utils.isModuleEnabled(
                          context: context,
                          moduleId: announcementManagementModuleId.toString())
                      ? Column(
                          children: [
                            SizedBox(
                              height:
                                  MediaQuery.of(context).size.height * (0.025),
                            ),
                            LatestNoticiesContainer(
                              animate: !widget.isForBottomMenuBackground,
                            ),
                          ],
                        )
                      : const SizedBox(),
                  Utils.isModuleEnabled(
                          context: context,
                          moduleId: galleryManagementModuleId.toString())
                      ? BlocBuilder<StudentProfileCubit, StudentProfileState>(
                          builder: (context, profileState) {
                            final student =
                                profileState is StudentProfileFetchSuccess
                                    ? profileState.student
                                    : context
                                        .read<StudentProfileCubit>()
                                        .getCurrentStudentProfile();
                            return SchoolGalleryContainer(student: student);
                          },
                        )
                      : const SizedBox(),
                ],
              ),
            ),
          );
        }

        if (state is StudentSubjectsAndSlidersFetchFailure) {
          return Center(
            child: ErrorContainer(
              onTapRetry: () {
                context
                    .read<StudentSubjectsAndSlidersCubit>()
                    .fetchSubjectsAndSliders(
                        useParentApi: false,
                        isSliderModuleEnable: Utils.isModuleEnabled(
                            context: context,
                            moduleId: sliderManagementModuleId.toString()));
              },
              errorMessageCode: state.errorMessage,
            ),
          );
        }

        return HomeScreenDataLoadingContainer(
          addTopPadding: true,
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Align(
          alignment: Alignment.topCenter,
          child: _buildSlidersSubjectsAndLatestNotcies(),
        ),
        Align(
          alignment: Alignment.topCenter,
          child: HomeContainerTopProfileContainer(),
        ),
      ],
    );
  }
}
