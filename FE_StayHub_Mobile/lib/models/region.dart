class Region {
  final int id;
  final int? parentId;
  final String code;
  final String name;
  final String? path;
  final String? slug;
  final int status; // 1: Active, 2: Inactive
  final String? description;
  final int? createdBy;
  final String? createdAt;
  final String? updatedAt;

  Region({
    required this.id,
    this.parentId,
    required this.code,
    required this.name,
    this.path,
    this.slug,
    required this.status,
    this.description,
    this.createdBy,
    this.createdAt,
    this.updatedAt,
  });

  factory Region.fromJson(Map<String, dynamic> json) {
    // Determine status from status or is_active boolean
    int resolvedStatus = 1;
    if (json['status'] != null) {
      resolvedStatus = json['status'] as int;
    } else if (json['is_active'] != null) {
      resolvedStatus = (json['is_active'] as bool) ? 1 : 2;
    }

    return Region(
      id: json['id'] as int,
      parentId: json['parent_id'] as int?,
      code: json['code'] as String? ?? '',
      name: json['name'] as String,
      path: json['path'] as String?,
      slug: json['slug'] as String?,
      status: resolvedStatus,
      description: json['description'] as String?,
      createdBy: json['created_by'] as int?,
      createdAt: json['created_at'] as String?,
      updatedAt: json['updated_at'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'parent_id': parentId,
      'code': code,
      'name': name,
      'path': path,
      'slug': slug,
      'status': status,
      'is_active': status == 1,
      'description': description,
      'created_by': createdBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  bool get isActive => status == 1;
}
