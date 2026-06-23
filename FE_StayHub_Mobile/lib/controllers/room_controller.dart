import 'package:flutter/material.dart';
import '../models/room.dart';
import '../services/api_service.dart';

class RoomController extends ChangeNotifier {
  final ApiService _apiService = ApiService();

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

  List<Room> _rooms = [];
  List<Room> _filteredRooms = [];
  bool _isLoading = false;
  String _errorMessage = '';
  String _searchQuery = '';

  List<Room> get rooms => _filteredRooms.isEmpty && _searchQuery.isEmpty ? (_rooms.isEmpty ? _mockRooms : _rooms) : _filteredRooms;
  bool get isLoading => _isLoading;
  String get errorMessage => _errorMessage;

  RoomController() {
    _filteredRooms = List.from(_mockRooms);
  }

  /// Get count of empty rooms
  int get emptyRoomsCount {
    final list = _rooms.isEmpty ? _mockRooms : _rooms;
    return list.where((r) => r.isEmpty).length;
  }

  /// Fetch all rooms from API
  Future<void> fetchRooms() async {
    _isLoading = true;
    _errorMessage = '';
    notifyListeners();

    try {
      final response = await _apiService.get<List<dynamic>>(
        '/admin/rooms',
        fromJsonT: (json) => json as List<dynamic>,
      );

      if (response.status && response.result != null) {
        _rooms = response.result!
            .map((item) => Room.fromJson(item as Map<String, dynamic>))
            .toList();
        search(_searchQuery);
      } else {
        _errorMessage = response.message;
      }
    } catch (e) {
      _errorMessage = e.toString();
      // API Offline Fallback
      _rooms = List.from(_mockRooms);
      search(_searchQuery);
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Search/filter rooms by number or building
  void search(String query) {
    _searchQuery = query.toLowerCase();
    final sourceList = _rooms.isEmpty ? _mockRooms : _rooms;
    if (_searchQuery.isEmpty) {
      _filteredRooms = List.from(sourceList);
    } else {
      _filteredRooms = sourceList.where((r) {
        return r.roomNumber.contains(_searchQuery) ||
            (r.buildingName != null && r.buildingName!.toLowerCase().contains(_searchQuery)) ||
            (r.building?.name != null && r.building!.name.toLowerCase().contains(_searchQuery));
      }).toList();
    }
    notifyListeners();
  }

  /// Update room status
  Future<bool> updateRoomStatus(int id, int status) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _apiService.patch<dynamic>(
        '/admin/rooms/$id/status',
        fromJsonT: (json) => json,
      );

      if (response.status) {
        await fetchRooms();
        return true;
      }
    } catch (_) {
      // Offline fallback: Update in mockRooms / rooms list
      final sourceList = _rooms.isEmpty ? _mockRooms : _rooms;
      final index = sourceList.indexWhere((r) => r.id == id);
      if (index != -1) {
        final oldRoom = sourceList[index];
        final updatedRoom = Room(
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
          building: oldRoom.building,
          roomType: oldRoom.roomType,
        );
        sourceList[index] = updatedRoom;
        search(_searchQuery);
        _isLoading = false;
        notifyListeners();
        return true;
      }
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }
}
