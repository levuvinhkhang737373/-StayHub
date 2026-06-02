import 'building.dart';
import 'room_type.dart';

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
  final String? roomTypeName;
  final Building? building;
  final RoomType? roomType;

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
    this.roomTypeName,
    this.building,
    this.roomType,
  });

  factory Room.fromJson(Map<String, dynamic> json) {
    return Room(
      id: json['id'] as int,
      buildingId: json['building_id'] as int,
      roomTypeId: json['room_type_id'] as int? ?? 1,
      roomNumber: json['room_number'] as String? ?? '',
      slug: json['slug'] as String?,
      floor: json['floor'] as int?,
      areaM2: json['area_m2'] != null ? double.tryParse(json['area_m2'].toString()) : null,
      basePrice: json['base_price'] != null ? (double.tryParse(json['base_price'].toString()) ?? 0.0) : 0.0,
      maxOccupants: json['max_occupants'] as int? ?? 4,
      currentOccupants: json['current_occupants'] as int? ?? 0,
      status: json['status'] as int? ?? 1,
      description: json['description'] as String?,
      createdBy: json['created_by'] as int?,
      buildingName: json['building_name'] as String?,
      roomTypeName: json['room_type_name'] as String?,
      building: json['building'] != null ? Building.fromJson(json['building'] as Map<String, dynamic>) : null,
      roomType: json['room_type'] != null ? RoomType.fromJson(json['room_type'] as Map<String, dynamic>) : null,
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
      'room_type_name': roomTypeName,
      'building': building?.toJson(),
      'room_type': roomType?.toJson(),
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
