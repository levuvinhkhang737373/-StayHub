import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/currency_formatter.dart';
import '../../controllers/room_controller.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/facility_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class RoomsScreen extends StatefulWidget {
  const RoomsScreen({super.key});

  @override
  State<RoomsScreen> createState() => _RoomsScreenState();
}

class _RoomsScreenState extends State<RoomsScreen> {
  final _searchController = TextEditingController();
  int? _selectedBuildingId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<RoomController>().fetchRooms();
      final authCtrl = context.read<AuthController>();
      final admin = authCtrl.currentAdmin;
      if (admin != null && admin.role == 2) {
        context.read<FacilityController>().fetchBuildings();
      }
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _changeStatus(dynamic room) {
    int selectedStatus = room.status;

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Container(
              decoration: const BoxDecoration(
                color: Color(0xFF1C1917),
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(28),
                  topRight: Radius.circular(28),
                ),
              ),
              padding: const EdgeInsets.all(24.0),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: Colors.white24,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'CẬP NHẬT PHÒNG ${room.roomNumber}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFFEAB308),
                      letterSpacing: 1.5,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 20),
                  
                  // Status Options List
                  _buildStatusOption(
                    title: 'Hoạt động / Sử dụng',
                    description: 'Phòng sẵn sàng đón khách hoặc đang cho thuê',
                    icon: Icons.check_circle_outline,
                    color: Colors.green,
                    isSelected: selectedStatus == 1,
                    onTap: () => setSheetState(() => selectedStatus = 1),
                  ),
                  const SizedBox(height: 12),
                  _buildStatusOption(
                    title: 'Đang bảo trì',
                    description: 'Phòng đang sửa chữa trang thiết bị, sự cố',
                    icon: Icons.handyman_outlined,
                    color: const Color(0xFFEAB308),
                    isSelected: selectedStatus == 2,
                    onTap: () => setSheetState(() => selectedStatus = 2),
                  ),
                  const SizedBox(height: 12),
                  _buildStatusOption(
                    title: 'Ngừng sử dụng',
                    description: 'Phòng tạm ngưng khai thác do lý do khác',
                    icon: Icons.block_outlined,
                    color: Colors.grey,
                    isSelected: selectedStatus == 3,
                    onTap: () => setSheetState(() => selectedStatus = 3),
                  ),
                  
                  const SizedBox(height: 24),
                  Row(
                    children: [
                      Expanded(
                        child: TextButton(
                          onPressed: () => Navigator.pop(context),
                          style: TextButton.styleFrom(
                            foregroundColor: Colors.grey,
                            padding: const EdgeInsets.symmetric(vertical: 16),
                          ),
                          child: const Text('HỦY', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () async {
                            final success = await context.read<RoomController>().updateRoomStatus(room.id, selectedStatus);
                            if (success && mounted) {
                              Navigator.pop(context);
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text('Cập nhật trạng thái thành công'),
                                  backgroundColor: Colors.green,
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            }
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFEAB308),
                            foregroundColor: const Color(0xFF1C1917),
                            padding: const EdgeInsets.symmetric(vertical: 16),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          child: const Text('CẬP NHẬT', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                      ),
                    ],
                  ),
                ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildStatusOption({
    required String title,
    required String description,
    required IconData icon,
    required Color color,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: isSelected ? color.withOpacity(0.15) : Colors.white10,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? color : Colors.white24,
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          children: [
            Icon(icon, color: isSelected ? color : Colors.grey, size: 24),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                      color: isSelected ? Colors.white : Colors.grey[300],
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    description,
                    style: TextStyle(fontSize: 11, color: Colors.grey[400]),
                  ),
                ],
              ),
            ),
            if (isSelected)
              Icon(Icons.check_circle, color: color, size: 20),
          ],
        ),
      ),
    );
  }

  Color _getStatusColor(dynamic room) {
    if (room.status == 2) return Colors.redAccent; // Maintenance
    if (room.status == 3) return Colors.grey; // Inactive
    return room.currentOccupants == 0 ? Colors.green : const Color(0xFFEAB308); // Vacant vs Occupied
  }

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final admin = authController.currentAdmin;
    final isSuperAdmin = admin != null && admin.role == 2;

    final roomController = context.watch<RoomController>();
    final facilityCtrl = context.watch<FacilityController>();

    var rooms = roomController.rooms;

    if (isSuperAdmin) {
      if (_selectedBuildingId != null) {
        rooms = rooms.where((r) => r.buildingId == _selectedBuildingId).toList();
      } else {
        rooms = [];
      }
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Trạng thái phòng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          Column(
            children: [
              // Search Input
              Padding(
                padding: const EdgeInsets.all(16.0),
                child: TextField(
                  controller: _searchController,
                  onChanged: (val) => roomController.search(val),
                  style: const TextStyle(color: Color(0xFF1C1917)),
                  decoration: InputDecoration(
                    hintText: 'Tìm theo số phòng hoặc tòa nhà...',
                    prefixIcon: const Icon(Icons.search, color: Color(0xFF1C1917)),
                    filled: true,
                    fillColor: Colors.white,
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFE4E2D7), width: 1.5),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                    ),
                  ),
                ),
              ),

              // Building Select (only for Super Admin)
              if (isSuperAdmin)
                Padding(
                  padding: const EdgeInsets.only(left: 16.0, right: 16.0, bottom: 12.0),
                  child: DropdownButtonFormField<int>(
                    value: _selectedBuildingId,
                    decoration: InputDecoration(
                      labelText: 'Chọn tòa nhà',
                      prefixIcon: const Icon(Icons.apartment, color: Color(0xFF1C1917)),
                      filled: true,
                      fillColor: Colors.white,
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFE4E2D7), width: 1.5),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                      ),
                    ),
                    items: facilityCtrl.buildings.map((b) {
                      return DropdownMenuItem<int>(
                        value: b.id,
                        child: Text(b.name, overflow: TextOverflow.ellipsis),
                      );
                    }).toList(),
                    onChanged: (value) {
                      setState(() {
                        _selectedBuildingId = value;
                      });
                    },
                  ),
                ),

              // Legend indicators
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 4.0),
                child: Row(
                  children: [
                    Expanded(
                      child: Wrap(
                        alignment: WrapAlignment.spaceBetween,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          _buildLegendItem('Phòng trống', Colors.green),
                          _buildLegendItem('Đang ở', const Color(0xFFEAB308)),
                          _buildLegendItem('Bảo trì', Colors.redAccent),
                          _buildLegendItem('Tắt', Colors.grey),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),

              // Rooms list
              Expanded(
                child: roomController.isLoading
                    ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
                    : (isSuperAdmin && _selectedBuildingId == null)
                        ? const Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.apartment, size: 64, color: Colors.grey),
                                SizedBox(height: 16),
                                Text(
                                  'Vui lòng chọn tòa nhà để xem danh sách phòng',
                                  style: TextStyle(color: Colors.grey, fontSize: 14),
                                ),
                              ],
                            ),
                          )
                        : rooms.isEmpty
                            ? const Center(child: Text('Không tìm thấy phòng nào.', style: TextStyle(color: Colors.grey)))
                        : ListView.builder(
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: rooms.length,
                            itemBuilder: (context, index) {
                              final room = rooms[index];
                              final statusColor = _getStatusColor(room);
                              return Card(
                                color: Colors.white,
                                margin: const EdgeInsets.only(bottom: 12),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(16),
                                  side: BorderSide(color: statusColor.withOpacity(0.5), width: 1.5),
                                ),
                                elevation: 0,
                                child: Padding(
                                  padding: const EdgeInsets.all(16.0),
                                  child: Row(
                                    children: [
                                      // Status Circle
                                      Container(
                                          width: 12,
                                          height: 12,
                                          decoration: BoxDecoration(
                                            color: statusColor,
                                            borderRadius: BorderRadius.circular(6),
                                          ),
                                        ),
                                      const SizedBox(width: 16),
                                      
                                      // Info
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              'Phòng ${room.roomNumber}',
                                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF1C1917)),
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              'Tầng: ${room.floor} | Diện tích: ${room.areaM2}m² | Tòa: ${room.buildingName ?? ""}',
                                              style: const TextStyle(fontSize: 12, color: Colors.grey),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(
                                              'Đơn giá: ${formatMoney(room.basePrice)} / tháng',
                                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                                            ),
                                          ],
                                        ),
                                      ),
                                      
                                      // Occupants & Actions
                                      Column(
                                        crossAxisAlignment: CrossAxisAlignment.end,
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                            decoration: BoxDecoration(
                                              color: const Color(0xFFF7F6F0),
                                              borderRadius: BorderRadius.circular(12),
                                            ),
                                            child: Row(
                                              children: [
                                                const Icon(Icons.people, size: 14, color: Color(0xFF1C1917)),
                                                const SizedBox(width: 4),
                                                Text(
                                                  '${room.currentOccupants}/${room.maxOccupants}',
                                                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                                                ),
                                              ],
                                            ),
                                          ),
                                          IconButton(
                                            icon: const Icon(Icons.edit_note, color: Color(0xFF1C1917)),
                                            onPressed: () => _changeStatus(room),
                                          ),
                                        ],
                                      )
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildLegendItem(String label, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(label, style: const TextStyle(fontSize: 11, color: Colors.grey, fontWeight: FontWeight.bold)),
      ],
    );
  }
}
