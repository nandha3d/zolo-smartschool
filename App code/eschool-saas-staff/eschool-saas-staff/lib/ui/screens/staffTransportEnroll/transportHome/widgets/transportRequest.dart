import 'package:eschool_saas_staff/cubits/authentication/authCubit.dart';
import 'package:eschool_saas_staff/cubits/transport/transportRequestCubit.dart';
import 'package:eschool_saas_staff/data/models/transportRequest.dart'
    as TransportRequestModel;
import 'package:eschool_saas_staff/ui/screens/staffTransportEnroll/transportHome/widgets/commonTransportWidgets.dart';
import 'package:eschool_saas_staff/ui/widgets/customCircularProgressIndicator.dart';
import 'package:eschool_saas_staff/ui/widgets/customTextContainer.dart';
import 'package:eschool_saas_staff/ui/widgets/errorContainer.dart';
import 'package:eschool_saas_staff/utils/labelKeys.dart';
import 'package:eschool_saas_staff/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import 'package:eschool_saas_staff/app/routes.dart';
import 'package:eschool_saas_staff/ui/screens/staffTransportEnroll/transportHome/transportRequestDetailsScreen.dart';

class TransportRequestWidget extends StatefulWidget {
  const TransportRequestWidget({super.key});

  @override
  State<TransportRequestWidget> createState() => _TransportRequestWidgetState();
}

class _TransportRequestWidgetState extends State<TransportRequestWidget> {
  @override
  void initState() {
    super.initState();
    _fetchTransportRequests();
  }

  void _fetchTransportRequests() {
    final userId = context.read<AuthCubit>().getUserDetails().id;
    if (userId != null && userId > 0) {
      context
          .read<TransportRequestCubit>()
          .fetchTransportRequests(userId: userId);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CustomTextContainer(
          textKey: 'Transportation Request',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: Theme.of(context).colorScheme.onSurface,
          ),
        ),
        const SizedBox(height: 8),
        BlocBuilder<TransportRequestCubit, TransportRequestState>(
          builder: (context, state) {
            if (state is TransportRequestFetchInProgress) {
              return Container(
                height: 120,
                child: Center(
                  child: CustomCircularProgressIndicator(
                    indicatorColor: Theme.of(context).colorScheme.primary,
                  ),
                ),
              );
            } else if (state is TransportRequestFetchFailure) {
              return Container(
                height: 120,
                child: Center(
                  child: ErrorContainer(
                    errorMessage: state.errorMessage,
                    onTapRetry: _fetchTransportRequests,
                  ),
                ),
              );
            } else if (state is TransportRequestFetchSuccess) {
              if (state.requests.isEmpty) {
                return _buildNoRequestsCard(context);
              }
              // Show the most recent request
              final request = state.requests.first;
              return _buildRequestCard(context, request);
            }

            return _buildNoRequestsCard(context);
          },
        ),
      ],
    );
  }

  Widget _buildRequestCard(
      BuildContext context, TransportRequestModel.TransportRequest request) {
    final status = request.status.toLowerCase();
    final statusText = request.statusDisplay;
    final statusColors = _getStatusColors(status);

    return GestureDetector(
      onTap: () {
        Get.toNamed(
          Routes.transportRequestDetailsScreen,
          arguments: RequestDetailsArgs(
            title: 'Transportation Request',
            requestedOn: request.requestedOn,
            statusText: statusText,
            statusBg: statusColors['background']!,
            statusFg: statusColors['foreground']!,
            sections: _buildRequestDetailSections(request),
            footerNote: _getFooterNote(status),
            showNewRequest: request.isRejected,
            transportRequest: request, // Pass the request data
          ),
        );
      },
      child: RequestCard(
        title: 'Transportation Request',
        statusText: statusText,
        statusBg: statusColors['background']!,
        requestedRoute: request.details.plan.duration.isNotEmpty
            ? request.details.plan.duration
            : Utils.getTranslatedLabel(notAvailableKey),
        requestedPickupPoint: request.details.pickupStop.name.isNotEmpty
            ? request.details.pickupStop.name
            : Utils.getTranslatedLabel(notAvailableKey),
      ),
    );
  }

  Widget _buildNoRequestsCard(BuildContext context) {
    return Container(
      width: double.maxFinite,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Theme.of(context).colorScheme.tertiary),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomTextContainer(
            textKey: Utils.getTranslatedLabel(noActiveRequestsKey),
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: Theme.of(context).colorScheme.onSurface,
            ),
          ),
          const SizedBox(height: 8),
          CustomTextContainer(
            textKey:
                Utils.getTranslatedLabel(noPendingTransportationRequestsKey),
            style: TextStyle(
              fontSize: 12,
              color: Theme.of(context)
                  .colorScheme
                  .onSurface
                  .withValues(alpha: 0.7),
            ),
          ),
        ],
      ),
    );
  }

  Map<String, Color> _getStatusColors(String status) {
    switch (status) {
      case 'accepted':
        return {
          'background': const Color(0xFFE8F5E8),
          'foreground': const Color(0xFF2E7D32),
        };
      case 'rejected':
        return {
          'background': const Color(0xFFF9D2D2),
          'foreground': const Color(0xFFB71C1C),
        };
      case 'pending':
        return {
          'background': const Color(0xFFFEEED7),
          'foreground': const Color(0xFF9E6C2C),
        };
      default:
        return {
          'background': const Color(0xFFF5F5F5),
          'foreground': const Color(0xFF9E9E9E),
        };
    }
  }

  String _getFooterNote(String status) {
    switch (status) {
      case 'accepted':
        return 'Your transportation request has been approved. The new plan is now active.';
      case 'rejected':
        return 'Your request was rejected. Please contact the transport department for alternate arrangements or refund.';
      case 'pending':
        return 'Approval usually takes 1–2 working days. If urgent, please contact your school transport in-charge.';
      default:
        return 'Please contact the transport department for more information.';
    }
  }

  List<Map<String, dynamic>> _buildRequestDetailSections(
      TransportRequestModel.TransportRequest request) {
    return [
      {
        'title': 'Request Information',
        'items': [
          {'label': 'Request ID', 'value': '#${request.id}'},
          {'label': 'Requested On', 'value': request.requestedOn},
          {'label': 'Status', 'value': request.statusDisplay},
          {'label': 'Payment Mode', 'value': request.mode.toUpperCase()},
          if (request.review != null)
            {'label': 'Responded On', 'value': request.review!.respondedOn},
        ],
      },
      {
        'title': 'Plan Details',
        'items': [
          {'label': 'Duration', 'value': request.details.plan.duration},
          {'label': 'Validity Period', 'value': request.details.plan.validity},
          {
            'label': 'Pickup Location',
            'value': request.details.pickupStop.name
          },
          {
            'label': 'Pickup Stop ID',
            'value': '#${request.details.pickupStop.id}'
          },
        ],
      },
      {
        'title': 'Student Information',
        'items': [
          {'label': 'Student Name', 'value': request.requestedBy.name},
          {'label': 'Student ID', 'value': '#${request.requestedBy.studentId}'},
        ],
      },
      {
        'title': 'Contact Details',
        'items': [
          {
            'label': 'School Email',
            'value': request.contactDetails.schoolEmail
          },
          {
            'label': 'School Phone',
            'value': request.contactDetails.schoolPhone
          },
        ],
      },
    ];
  }
}
