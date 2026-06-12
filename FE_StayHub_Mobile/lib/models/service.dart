class Service {
  final int id;
  final String name;
  final String? slug;
  final int chargeMethod;
  final String? unitName;
  final bool isRequired;
  final double? price; // Optional: Joined from service_prices
  final int status; // 1: Active, 2: Inactive (For backward compatibility)
  final String? description;
  final int? createdBy;
  final String? createdAt;
  final String? updatedAt;

  Service({
    required this.id,
    required this.name,
    this.slug,
    required this.chargeMethod,
    this.unitName,
    required this.isRequired,
    this.price,
    required this.status,
    this.description,
    this.createdBy,
    this.createdAt,
    this.updatedAt,
  });

  // Alias for backward compatibility
  String get unit => unitName ?? '';

  factory Service.fromJson(Map<String, dynamic> json) {
    // Resolve active status
    int resolvedStatus = 1;
    if (json['status'] != null) {
      resolvedStatus = json['status'] as int;
    } else if (json['is_active'] != null) {
      resolvedStatus = (json['is_active'] as bool) ? 1 : 2;
    }

    return Service(
      id: json['id'] as int,
      name: json['name'] as String,
      slug: json['slug'] as String?,
      chargeMethod: json['charge_method'] as int? ?? 1,
      unitName: json['unit_name'] as String? ?? json['unit'] as String?,
      isRequired: json['is_required'] as bool? ?? false,
      price: json['price'] != null ? double.tryParse(json['price'].toString()) : null,
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
      'name': name,
      'slug': slug,
      'charge_method': chargeMethod,
      'unit_name': unitName,
      'is_required': isRequired,
      'is_active': status == 1,
      'price': price,
      'status': status,
      'description': description,
      'created_by': createdBy,
      'created_at': createdAt,
      'updated_at': updatedAt,
    };
  }

  bool get isActive => status == 1;
}
