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
                            } else if (mounted) {
                              final controller = context.read<RoomController>();
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(controller.errorMessage.isNotEmpty
                                      ? controller.errorMessage
                                      : 'Cập nhật trạng thái thất bại'),
                                  backgroundColor: Colors.redAccent,
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

              // Metric cards scroll view
              if (rooms.isNotEmpty)
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Row(
                    children: [
                      _buildMetricCard('TỔNG SỐ PHÒNG', rooms.length, Colors.blueGrey),
                      const SizedBox(width: 10),
                      _buildMetricCard('ĐANG CHO THUÊ', rooms.where((r) => r.currentOccupants > 0 && r.status == 1).length, const Color(0xFFD97706)),
                      const SizedBox(width: 10),
                      _buildMetricCard('PHÒNG TRỐNG', rooms.where((r) => r.currentOccupants == 0 && r.status == 1).length, const Color(0xFF0F766E)),
                      const SizedBox(width: 10),
                      _buildMetricCard('ĐANG BẢO TRÌ', rooms.where((r) => r.status == 2).length, Colors.redAccent),
                    ],
                  ),
                ),

              // Legend indicators
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
                child: Row(
                  children: [
                    Expanded(
                      child: Wrap(
                        alignment: WrapAlignment.spaceBetween,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          _buildLegendItem('Phòng trống', const Color(0xFF0F766E)),
                          _buildLegendItem('Đang ở', const Color(0xFFD97706)),
                          _buildLegendItem('Bảo trì', Colors.redAccent),
                          _buildLegendItem('Tắt', Colors.grey),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),

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
                                  textAlign: TextAlign.center,
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

                                  String statusText = 'Ngừng sử dụng';
                                  Color tagBgColor = Colors.grey[200]!;
                                  Color tagTextColor = Colors.grey[700]!;

                                  if (room.status == 1) {
                                    if (room.currentOccupants == 0) {
                                      statusText = 'Trống';
                                      tagBgColor = const Color(0xFF0F766E).withOpacity(0.12);
                                      tagTextColor = const Color(0xFF0F766E);
                                    } else {
                                      statusText = 'Đang thuê';
                                      tagBgColor = const Color(0xFFD97706).withOpacity(0.12);
                                      tagTextColor = const Color(0xFFD97706);
                                    }
                                  } else if (room.status == 2) {
                                    statusText = 'Đang bảo trì';
                                    tagBgColor = Colors.red[50]!;
                                    tagTextColor = Colors.red[900]!;
                                  }

                                  return Card(
                                    color: Colors.white,
                                    margin: const EdgeInsets.only(bottom: 12),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(16),
                                      side: BorderSide(color: const Color(0xFF3D2A18).withOpacity(0.1), width: 1),
                                    ),
                                    elevation: 0,
                                    child: Padding(
                                      padding: const EdgeInsets.all(16.0),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.stretch,
                                        children: [
                                          Row(
                                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                            children: [
                                              Text(
                                                'Phòng ${room.roomNumber}',
                                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF1C1917)),
                                              ),
                                              Container(
                                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                                decoration: BoxDecoration(
                                                  color: tagBgColor,
                                                  borderRadius: BorderRadius.circular(20),
                                                ),
                                                child: Text(
                                                  statusText,
                                                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w900, color: tagTextColor),
                                                ),
                                              ),
                                            ],
                                          ),
                                          const SizedBox(height: 8),
                                          const Divider(height: 1, color: Color(0xFFF1EFE9)),
                                          const SizedBox(height: 12),
                                          Row(
                                            children: [
                                              Expanded(
                                                child: Column(
                                                  crossAxisAlignment: CrossAxisAlignment.start,
                                                  children: [
                                                    Text(
                                                      'Tầng: ${room.floor}  •  Diện tích: ${room.areaM2}m²',
                                                      style: const TextStyle(fontSize: 12, color: Colors.grey, fontWeight: FontWeight.w500),
                                                    ),
                                                    const SizedBox(height: 4),
                                                    Text(
                                                      'Giá: ${formatMoney(room.basePrice)} / tháng',
                                                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                                                    ),
                                                  ],
                                                ),
                                              ),
                                              Column(
                                                crossAxisAlignment: CrossAxisAlignment.end,
                                                children: [
                                                  Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                                    decoration: BoxDecoration(
                                                      color: const Color(0xFFF7F6F0),
                                                      borderRadius: BorderRadius.circular(10),
                                                    ),
                                                    child: Row(
                                                      children: [
                                                        const Icon(Icons.people_outline, size: 14, color: Color(0xFF1C1917)),
                                                        const SizedBox(width: 4),
                                                        Text(
                                                          '${room.currentOccupants}/${room.maxOccupants}',
                                                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                                                        ),
                                                      ],
                                                    ),
                                                  ),
                                                  const SizedBox(height: 4),
                                                  InkWell(
                                                    onTap: () => _changeStatus(room),
                                                    borderRadius: BorderRadius.circular(20),
                                                    child: Container(
                                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                                      decoration: BoxDecoration(
                                                        border: Border.all(color: const Color(0xFF1C1917).withOpacity(0.15)),
                                                        borderRadius: BorderRadius.circular(20),
                                                      ),
                                                      child: const Row(
                                                        mainAxisSize: MainAxisSize.min,
                                                        children: [
                                                          Icon(Icons.power_settings_new, size: 13, color: Color(0xFF1C1917)),
                                                          SizedBox(width: 4),
                                                          Text('Cập nhật', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF1C1917))),
                                                        ],
                                                      ),
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ],
                                          ),
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

  Widget _buildMetricCard(String label, int value, Color color) {
    return Container(
      width: 120,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFF1C1917),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withOpacity(0.4), width: 1.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 8,
              fontWeight: FontWeight.w900,
              color: Colors.white.withOpacity(0.6),
              letterSpacing: 1.1,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            '$value',
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w900,
              color: Color(0xFFEAB308),
            ),
          ),
        ],
      ),
    );
  }
}
