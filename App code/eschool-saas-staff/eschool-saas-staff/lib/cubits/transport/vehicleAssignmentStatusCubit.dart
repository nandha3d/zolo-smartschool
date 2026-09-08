import 'package:eschool_saas_staff/data/models/vehicleAssignmentStatus.dart';
import 'package:eschool_saas_staff/data/repositories/transportRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class VehicleAssignmentStatusState {}

class VehicleAssignmentStatusInitial extends VehicleAssignmentStatusState {}

class VehicleAssignmentStatusFetchInProgress
    extends VehicleAssignmentStatusState {}

class VehicleAssignmentStatusFetchSuccess extends VehicleAssignmentStatusState {
  final VehicleAssignmentStatus vehicleAssignmentStatus;

  VehicleAssignmentStatusFetchSuccess({required this.vehicleAssignmentStatus});
}

class VehicleAssignmentStatusFetchFailure extends VehicleAssignmentStatusState {
  final String errorMessage;

  VehicleAssignmentStatusFetchFailure(this.errorMessage);
}

class VehicleAssignmentStatusCubit extends Cubit<VehicleAssignmentStatusState> {
  final TransportRepository _transportRepository = TransportRepository();

  VehicleAssignmentStatusCubit() : super(VehicleAssignmentStatusInitial());

  Future<void> fetchVehicleAssignmentStatus({
    required int userId,
  }) async {
    emit(VehicleAssignmentStatusFetchInProgress());

    try {
      final vehicleAssignmentStatus =
          await _transportRepository.getVehicleAssignmentStatus(
        userId: userId,
      );

      emit(VehicleAssignmentStatusFetchSuccess(
          vehicleAssignmentStatus: vehicleAssignmentStatus));
    } catch (e) {
      emit(VehicleAssignmentStatusFetchFailure(e.toString()));
    }
  }

  // Helper method to check if user is assigned to vehicle
  bool isUserAssigned() {
    if (state is VehicleAssignmentStatusFetchSuccess) {
      return (state as VehicleAssignmentStatusFetchSuccess)
          .vehicleAssignmentStatus
          .isAssigned;
    }
    return false;
  }

  // Helper method to check if user exists and API call was successful
  bool isValidUser() {
    if (state is VehicleAssignmentStatusFetchSuccess) {
      return (state as VehicleAssignmentStatusFetchSuccess)
          .vehicleAssignmentStatus
          .isValidUser;
    }
    return false;
  }

  // Helper method to get assignment status
  String getAssignmentStatus() {
    if (state is VehicleAssignmentStatusFetchSuccess) {
      return (state as VehicleAssignmentStatusFetchSuccess)
          .vehicleAssignmentStatus
          .assignmentStatus;
    }
    return 'unknown';
  }

  // Helper method to get status message
  String getStatusMessage() {
    if (state is VehicleAssignmentStatusFetchSuccess) {
      return (state as VehicleAssignmentStatusFetchSuccess)
          .vehicleAssignmentStatus
          .message;
    }
    if (state is VehicleAssignmentStatusFetchFailure) {
      return (state as VehicleAssignmentStatusFetchFailure).errorMessage;
    }
    return 'Loading...';
  }

  // Helper method to determine transport enrollment availability
  bool isTransportEnrollEnabled() {
    if (state is VehicleAssignmentStatusFetchSuccess) {
      return isUserAssigned();
    }
    // Default to false if status is not loaded or failed
    return false;
  }

  // Helper method to check if assignment status has been loaded
  bool isStatusLoaded() {
    return state is VehicleAssignmentStatusFetchSuccess;
  }

  // Helper method to check if there was an error
  bool hasError() {
    return state is VehicleAssignmentStatusFetchFailure;
  }
}
