import 'package:flutter/material.dart';
import '../models/room.dart';

class RoomController extends ChangeNotifier {
  final List<Room> _mockRooms = [
    Room(
      id: 1,
      buildingId: 1,
      roomTypeId: 1,
      roomNumber: '101',
      floor: 1,
      areaM2: 25.0,
      basePrice: 3500000,
      maxOccupants: 4,
      currentOccupants: 3,
      status: 1,
      description: 'Phòng đầu hồi, thoáng mát, ban công riêng',
      buildingName: 'StayHub Sài Gòn Q1',
    ),
    Room(
      id: 2,
      buildingId: 1,
      roomTypeId: 1,
      roomNumber: '102',
      floor: 1,
      areaM2: 25.0,
      basePrice: 3500000,
      maxOccupants: 4,
      currentOccupants: 2,
      status: 1,
      description: 'Phòng giữa tầng, yên tĩnh',
      buildingName: 'StayHub Sài Gòn Q1',
    ),
    Room(
      id: 3,
      buildingId: 1,
      roomTypeId: 1,
      roomNumber: '103',
      floor: 1,
      areaM2: 20.0,
      basePrice: 3000000,
      maxOccupants: 2,
      currentOccupants: 0, // EMPTY ROOM
      status: 1,
      description: 'Phòng đơn, đầy đủ nội thất cơ bản',
      buildingName: 'StayHub Sài Gòn Q1',
    ),
    Room(
      id: 4,
      buildingId: 1,
      roomTypeId: 2,
      roomNumber: '201',
      floor: 2,
      areaM2: 30.0,
      basePrice: 4200000,
      maxOccupants: 4,
      currentOccupants: 0, // EMPTY ROOM
      status: 2, // MAINTENANCE
      description: 'Đang chống thấm nhà vệ sinh',
      buildingName: 'StayHub Sài Gòn Q1',
    ),
    Room(
      id: 5,
      buildingId: 2,
      roomTypeId: 1,
      roomNumber: '201',
      floor: 2,
      areaM2: 25.0,
      basePrice: 3800000,
      maxOccupants: 4,
      currentOccupants: 4,
      status: 1,
      description: 'Phòng lầu 2, StayHub Q3',
      buildingName: 'StayHub Sài Gòn Q3',
    ),
    Room(
      id: 6,
      buildingId: 2,
      roomTypeId: 1,
      roomNumber: '202',
      floor: 2,
      areaM2: 25.0,
      basePrice: 3800000,
      maxOccupants: 4,
      currentOccupants: 0, // EMPTY ROOM
      status: 1,
      description: 'Phòng trống lầu 2',
      buildingName: 'StayHub Sài Gòn Q3',
    ),
  ];

  List<Room> _filteredRooms = [];
  bool _isLoading = false;
  String _searchQuery = '';

  List<Room> get rooms => _filteredRooms.isEmpty && _searchQuery.isEmpty ? _mockRooms : _filteredRooms;
  bool get isLoading => _isLoading;

  RoomController() {
    _filteredRooms = List.from(_mockRooms);
  }

  /// Get count of empty rooms
  int get emptyRoomsCount {
    return _mockRooms.where((r) => r.isEmpty).length;
  }

  /// Search/filter rooms by number or building
  void search(String query) {
    _searchQuery = query.toLowerCase();
    if (_searchQuery.isEmpty) {
      _filteredRooms = List.from(_mockRooms);
    } else {
      _filteredRooms = _mockRooms.where((r) {
        return r.roomNumber.contains(_searchQuery) ||
            (r.buildingName != null && r.buildingName!.toLowerCase().contains(_searchQuery));
      }).toList();
    }
    notifyListeners();
  }

  /// Update room status
  Future<bool> updateRoomStatus(int id, int status) async {
    _isLoading = true;
    notifyListeners();

    await Future.delayed(const Duration(milliseconds: 500));

    try {
      final index = _mockRooms.indexWhere((r) => r.id == id);
      if (index != -1) {
        final oldRoom = _mockRooms[index];
        _mockRooms[index] = Room(
          id: oldRoom.id,
          buildingId: oldRoom.buildingId,
          roomTypeId: oldRoom.roomTypeId,
          roomNumber: oldRoom.roomNumber,
          floor: oldRoom.floor,
          areaM2: oldRoom.areaM2,
          basePrice: oldRoom.basePrice,
          maxOccupants: oldRoom.maxOccupants,
          currentOccupants: oldRoom.currentOccupants,
          status: status,
          description: oldRoom.description,
          buildingName: oldRoom.buildingName,
        );
        search(_searchQuery);
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (_) {}

    _isLoading = false;
    notifyListeners();
    return false;
  }
}
