class Region {
  final int id;
  final int? parentId;
  final String code;
  final String name;
  final String? path;
  final String? slug;
  final bool isActive;
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
    required this.isActive,
    this.description,
    this.createdBy,
    this.createdAt,
    this.updatedAt,
  });

  int get status => isActive ? 1 : 2;

  factory Region.fromJson(Map<String, dynamic> json) {
    bool active = true;
    if (json['is_active'] != null) {
      active = json['is_active'] as bool;
    } else if (json['status'] != null) {
      active = (json['status'] as int) == 1;
    }

    return Region(
      id: json['id'] as int,
      parentId: json['parent_id'] as int?,
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      path: json['path'] as String?,
      slug: json['slug'] as String?,
      isActive: active,
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
      'is_active': isActive,
      'status': status,
      'description': description,
      'created_by': createdBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }
}
