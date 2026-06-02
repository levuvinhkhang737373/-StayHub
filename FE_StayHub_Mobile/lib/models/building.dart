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
  final BuildingImage? primaryImage;
  final List<BuildingImage>? images;

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
    this.primaryImage,
    this.images,
  });

  factory Building.fromJson(Map<String, dynamic> json) {
    return Building(
      id: json['id'] as int,
      regionId: json['region_id'] as int,
      managerAdminId: json['manager_admin_id'] as int?,
      name: json['name'] as String? ?? '',
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
      primaryImage: json['primary_image'] != null ? BuildingImage.fromJson(json['primary_image'] as Map<String, dynamic>) : null,
      images: json['images'] != null
          ? (json['images'] as List).map((i) => BuildingImage.fromJson(i as Map<String, dynamic>)).toList()
          : null,
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
      'primary_image': primaryImage?.toJson(),
      'images': images?.map((i) => i.toJson()).toList(),
    };
  }

  bool get isActive => status == 1;
}

class BuildingImage {
  final int id;
  final int buildingId;
  final String imagePath;
  final String? imageUrl;
  final bool isPrimary;
  final int sortOrder;
  final int status;
  final int? uploadedBy;
  final String? uploaderName;
  final String? createdAt;
  final String? updatedAt;

  BuildingImage({
    required this.id,
    required this.buildingId,
    required this.imagePath,
    this.imageUrl,
    required this.isPrimary,
    required this.sortOrder,
    required this.status,
    this.uploadedBy,
    this.uploaderName,
    this.createdAt,
    this.updatedAt,
  });

  factory BuildingImage.fromJson(Map<String, dynamic> json) {
    return BuildingImage(
      id: json['id'] as int,
      buildingId: json['building_id'] as int,
      imagePath: json['image_path'] as String? ?? '',
      imageUrl: json['image_url'] as String?,
      isPrimary: json['is_primary'] as bool? ?? false,
      sortOrder: json['sort_order'] as int? ?? 0,
      status: json['status'] as int? ?? 1,
      uploadedBy: json['uploaded_by'] as int?,
      uploaderName: json['uploader_name'] as String?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'building_id': buildingId,
      'image_path': imagePath,
      'image_url': imageUrl,
      'is_primary': isPrimary,
      'sort_order': sortOrder,
      'status': status,
      'uploaded_by': uploadedBy,
      'uploader_name': uploaderName,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }
}
