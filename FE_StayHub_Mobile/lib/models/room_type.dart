class RoomType {
  final int id;
  final int? buildingId;
  final String? buildingName;
  final String name;
  final String? slug;
  final String? description;
  final int status; // 1: Active, 2: Inactive
  final int? roomsCount;
  final int? createdBy;
  final String? createdAt;
  final String? updatedAt;

  RoomType({
    required this.id,
    this.buildingId,
    this.buildingName,
    required this.name,
    this.slug,
    this.description,
    required this.status,
    this.roomsCount,
    this.createdBy,
    this.createdAt,
    this.updatedAt,
  });

  factory RoomType.fromJson(Map<String, dynamic> json) {
    return RoomType(
      id: json['id'] as int,
      buildingId: json['building_id'] as int?,
      buildingName: json['building_name'] as String?,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String?,
      description: json['description'] as String?,
      status: json['status'] as int? ?? 1,
      roomsCount: json['rooms_count'] as int?,
      createdBy: json['created_by'] as int?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'building_id': buildingId,
      'building_name': buildingName,
      'name': name,
      'slug': slug,
      'description': description,
      'status': status,
      'rooms_count': roomsCount,
      'created_by': createdBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  String get statusLabel {
    return status == 1 ? 'Hoạt động' : 'Ngừng hoạt động';
  }

  bool get isActive => status == 1;
}
