class Tenant {
  final int id;
  final int? buildingId;
  final String fullName;
  final int gender;
  final String? dateOfBirth;
  final String phone;
  final String email;
  final String username;
  final String? permanentAddress;
  final String? currentAddress;
  final String? avatarUrl;
  final int status; // 1: Renting, 2: Stopped Renting
  final String? roomNumber;
  final String? buildingName;
  final int identityType;
  final String identityNumber;
  final String? frontImageUrl;
  final String? backImageUrl;

  Tenant({
    required this.id,
    this.buildingId,
    required this.fullName,
    required this.gender,
    this.dateOfBirth,
    required this.phone,
    required this.email,
    required this.username,
    this.permanentAddress,
    this.currentAddress,
    this.avatarUrl,
    required this.status,
    this.roomNumber,
    this.buildingName,
    required this.identityType,
    required this.identityNumber,
    this.frontImageUrl,
    this.backImageUrl,
  });

  factory Tenant.fromJson(Map<String, dynamic> json) {
    return Tenant(
      id: json['id'] as int,
      buildingId: json['building_id'] as int?,
      fullName: json['full_name'] as String,
      gender: json['gender'] as int? ?? 1,
      dateOfBirth: json['date_of_birth'] as String?,
      phone: json['phone'] as String? ?? '',
      email: json['email'] as String? ?? '',
      username: json['username'] as String? ?? '',
      permanentAddress: json['permanent_address'] as String?,
      currentAddress: json['current_address'] as String?,
      avatarUrl: json['avatar_url'] as String?,
      status: json['status'] as int? ?? 1,
      roomNumber: json['room_number'] as String?,
      buildingName: json['building_name'] as String?,
      identityType: json['identity_type'] as int? ?? 1,
      identityNumber: json['identity_number'] as String? ?? '',
      frontImageUrl: json['front_image_url'] as String?,
      backImageUrl: json['back_image_url'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'building_id': buildingId,
      'full_name': fullName,
      'gender': gender,
      'date_of_birth': dateOfBirth,
      'phone': phone,
      'email': email,
      'username': username,
      'permanent_address': permanentAddress,
      'current_address': currentAddress,
      'avatar_url': avatarUrl,
      'status': status,
      'room_number': roomNumber,
      'building_name': buildingName,
      'identity_type': identityType,
      'identity_number': identityNumber,
      'front_image_url': frontImageUrl,
      'back_image_url': backImageUrl,
    };
  }

  String get genderLabel => gender == 1 ? 'Nam' : 'Nữ';
  String get statusLabel => status == 1 ? 'Đang thuê' : 'Ngừng thuê';
  bool get isActive => status == 1;
}
