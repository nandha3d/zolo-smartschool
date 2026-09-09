import 'package:zolo_smart_school/cubits/appSettingsCubit.dart';
import 'package:zolo_smart_school/data/repositories/systemInfoRepository.dart';
import 'package:zolo_smart_school/ui/widgets/appSettingsBlocBuilder.dart';
import 'package:zolo_smart_school/ui/widgets/customAppbar.dart';
import 'package:zolo_smart_school/utils/labelKeys.dart';
import 'package:zolo_smart_school/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class ContactUsScreen extends StatefulWidget {
  const ContactUsScreen({Key? key}) : super(key: key);

  @override
  State<ContactUsScreen> createState() => _ContactUsScreenState();

  static Widget routeInstance() {
    return BlocProvider<AppSettingsCubit>(
      create: (context) => AppSettingsCubit(SystemRepository()),
      child: const ContactUsScreen(),
    );
  }
}

class _ContactUsScreenState extends State<ContactUsScreen> {
  final String contactUsType = "contact_us";
  @override
  void initState() {
    Future.delayed(Duration.zero, () {
      context.read<AppSettingsCubit>().fetchAppSettings(type: contactUsType);
    });
    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          AppSettingsBlocBuilder(appSettingsType: contactUsType),
          CustomAppBar(
            title: Utils.getTranslatedLabel(contactUsKey),
          ),
        ],
      ),
    );
  }
}
