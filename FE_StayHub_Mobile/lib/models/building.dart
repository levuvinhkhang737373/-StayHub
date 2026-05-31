import 'region.dart';
import 'admin.dart';

class Building {
  final int id;
  final int regionId;
  final int? managerAdminId;
  final String name;
  final String? slug;
  final String? address;
  final int? totalFloors;
  final int genderPolicy;
  final String? description;
  final int status;
  final int? createdBy;
  final String? createdAt;
  final String? updatedAt;
  final Region? region;
  final Admin? manager;

  Building({
    required this.id,
    required this.regionId,
    this.managerAdminId,
    required this.name,
    this.slug,
    this.address,
    this.totalFloors,
    required this.genderPolicy,
    this.description,
    required this.status,
    this.createdBy,
    this.createdAt,
    this.updatedAt,
    this.region,
    this.manager,
  });

  factory Building.fromJson(Map<String, dynamic> json) {
    return Building(
      id: json['id'] as int,
      regionId: json['region_id'] as int,
      managerAdminId: json['manager_admin_id'] as int?,
      name: json['name'] as String,
      slug: json['slug'] as String?,
      address: json['address'] as String?,
      totalFloors: json['total_floors'] as int?,
      genderPolicy: json['gender_policy'] as int? ?? 1,
      description: json['description'] as String?,
      status: json['status'] as int? ?? 1,
      createdBy: json['created_by'] as int?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
      region: json['region'] != null ? Region.fromJson(json['region'] as Map<String, dynamic>) : null,
      manager: json['manager'] != null ? Admin.fromJson(json['manager'] as Map<String, dynamic>) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'region_id': regionId,
      'manager_admin_id': managerAdminId,
      'name': name,
      'slug': slug,
      'address': address,
      'total_floors': totalFloors,
      'gender_policy': genderPolicy,
      'description': description,
      'status': status,
      'created_by': createdBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
      'region': region?.toJson(),
      'manager': manager?.toJson(),
    };
  }

  bool get isActive => status == 1;
}
