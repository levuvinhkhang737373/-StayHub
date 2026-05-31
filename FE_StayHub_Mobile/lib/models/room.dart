class Room {
  final int id;
  final int buildingId;
  final int roomTypeId;
  final String roomNumber;
  final String? slug;
  final int? floor;
  final double? areaM2;
  final double basePrice;
  final int maxOccupants;
  final int currentOccupants;
  final int status; // 1: Active, 2: Maintenance, 3: Inactive
  final String? description;
  final int? createdBy;
  final String? buildingName;

  Room({
    required this.id,
    required this.buildingId,
    required this.roomTypeId,
    required this.roomNumber,
    this.slug,
    this.floor,
    this.areaM2,
    required this.basePrice,
    required this.maxOccupants,
    required this.currentOccupants,
    required this.status,
    this.description,
    this.createdBy,
    this.buildingName,
  });

  factory Room.fromJson(Map<String, dynamic> json) {
    return Room(
      id: json['id'] as int,
      buildingId: json['building_id'] as int,
      roomTypeId: json['room_type_id'] as int? ?? 1,
      roomNumber: json['room_number'] as String? ?? json['room_number'] as String, // handles possible string keys
      slug: json['slug'] as String?,
      floor: json['floor'] as int?,
      areaM2: json['area_m2'] != null ? (json['area_m2'] as num).toDouble() : null,
      basePrice: (json['base_price'] as num? ?? 0.0).toDouble(),
      maxOccupants: json['max_occupants'] as int? ?? 4,
      currentOccupants: json['current_occupants'] as int? ?? 0,
      status: json['status'] as int? ?? 1,
      description: json['description'] as String?,
      createdBy: json['created_by'] as int?,
      buildingName: json['building_name'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'building_id': buildingId,
      'room_type_id': roomTypeId,
      'room_number': roomNumber,
      'slug': slug,
      'floor': floor,
      'area_m2': areaM2,
      'base_price': basePrice,
      'max_occupants': maxOccupants,
      'current_occupants': currentOccupants,
      'status': status,
      'description': description,
      'created_by': createdBy,
      'building_name': buildingName,
    };
  }

  String get statusLabel {
    switch (status) {
      case 1:
        return currentOccupants == 0 ? 'Phòng trống' : 'Đang ở';
      case 2:
        return 'Đang bảo trì';
      case 3:
      default:
        return 'Ngừng sử dụng';
    }
  }

  bool get isEmpty => currentOccupants == 0 && status == 1;
}
