class Admin {
  final int id;
  final String username;
  final String fullName;
  final String email;
  final String? phone;
  final int role;
  final String? avatarUrl;
  final String? imagePathFaceid;
  final String? createdFaceidAt;
  final String? updatedFaceidAt;
  final int status;
  final int gender;
  final String? address;

  Admin({
    required this.id,
    required this.username,
    required this.fullName,
    required this.email,
    this.phone,
    required this.role,
    this.avatarUrl,
    this.imagePathFaceid,
    this.createdFaceidAt,
    this.updatedFaceidAt,
    required this.status,
    required this.gender,
    this.address,
  });

  factory Admin.fromJson(Map<String, dynamic> json) {
    return Admin(
      id: json['id'] as int,
      username: json['username'] as String,
      fullName: json['full_name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      role: json['role'] as int,
      avatarUrl: json['avatar_url'] as String?,
      imagePathFaceid: json['image_path_faceid'] as String?,
      createdFaceidAt: json['created_faceid_at'] as String?,
      updatedFaceidAt: json['updated_faceid_at'] as String?,
      status: json['status'] as int,
      gender: json['gender'] as int,
      address: json['address'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'username': username,
      'full_name': fullName,
      'email': email,
      'phone': phone,
      'role': role,
      'avatar_url': avatarUrl,
      'image_path_faceid': imagePathFaceid,
      'created_faceid_at': createdFaceidAt,
      'updated_faceid_at': updatedFaceidAt,
      'status': status,
      'gender': gender,
      'address': address,
    };
  }

  String get roleLabel {
    switch (role) {
      case 1:
        return 'Quản lí tòa nhà';
      case 2:
        return 'Quản trị tổng';
      case 3:
        return 'Kỹ thuật';
      default:
        return 'Không xác định';
    }
  }

  String get statusLabel {
    return status == 1 ? 'Hoạt động' : 'Ngừng hoạt động';
  }

  String get genderLabel {
    return gender == 1 ? 'Nam' : 'Nữ';
  }
}
