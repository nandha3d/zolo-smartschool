class VehicleAssignmentStatus {
  final bool error;
  final String message;
  final String? data;
  final int code;
  final String? details;

  VehicleAssignmentStatus({
    required this.error,
    required this.message,
    this.data,
    required this.code,
    this.details,
  });

  factory VehicleAssignmentStatus.fromJson(Map<String, dynamic> json) {
    return VehicleAssignmentStatus(
      error: json['error'] ?? false,
      message: json['message'] ?? '',
      data: json['data'] as String?,
      code: json['code'] ?? 200,
      details: json['details'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'error': error,
      'message': message,
      'data': data,
      'code': code,
      'details': details,
    };
  }

  // Helper method to check if user is assigned to a vehicle
  bool get isAssigned => !error && data?.toLowerCase() == 'assigned';

  // Helper method to check if transportation should be shown (data: "assigned" means show, anything else means hide)
  bool get shouldShowTransportation => isAssigned;

  // Helper method to check if user exists and API call was successful
  bool get isValidUser => !error;

  // Helper method to get the assignment status
  String get assignmentStatus => data ?? 'unknown';
}
