import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/tenant_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantsScreen extends StatefulWidget {
  const TenantsScreen({super.key});

  @override
  State<TenantsScreen> createState() => _TenantsScreenState();
}

class _TenantsScreenState extends State<TenantsScreen> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _showTenantDetails(dynamic tenant) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return Container(
          decoration: const BoxDecoration(
            color: Color(0xFF1C1917),
            borderRadius: BorderRadius.only(
              topLeft: Radius.circular(28),
              topRight: Radius.circular(28),
            ),
          ),
          padding: const EdgeInsets.all(24.0),
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
              Row(
                children: [
                  CircleAvatar(
                    radius: 30,
                    backgroundColor: const Color(0xFFEAB308),
                    child: Text(
                      tenant.fullName.isNotEmpty ? tenant.fullName.substring(0, 1).toUpperCase() : 'T',
                      style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917), fontSize: 24),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          tenant.fullName,
                          style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 20),
                        ),
                        const SizedBox(height: 4),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: tenant.isActive ? Colors.green.withOpacity(0.2) : Colors.red.withOpacity(0.2),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            tenant.statusLabel,
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              color: tenant.isActive ? Colors.green : Colors.redAccent,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const Divider(height: 32, color: Colors.white12),
              _buildBottomSheetRow(Icons.phone_outlined, 'Số điện thoại:', tenant.phone),
              _buildBottomSheetRow(Icons.email_outlined, 'Email:', tenant.email.isNotEmpty ? tenant.email : 'Chưa cập nhật'),
              _buildBottomSheetRow(Icons.wc_outlined, 'Giới tính:', tenant.genderLabel),
              _buildBottomSheetRow(Icons.home_outlined, 'Quê quán:', tenant.permanentAddress ?? 'Chưa cập nhật'),
              _buildBottomSheetRow(Icons.meeting_room_outlined, 'Phòng:', tenant.roomNumber != null ? 'Phòng ${tenant.roomNumber}' : 'Chưa có phòng'),
              _buildBottomSheetRow(Icons.business_outlined, 'Tòa nhà:', tenant.buildingName ?? 'Chưa cấu hình'),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: () => Navigator.pop(context),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFEAB308),
                  foregroundColor: const Color(0xFF1C1917),
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('ĐÓNG', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildBottomSheetRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12.0),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFFEAB308), size: 20),
          const SizedBox(width: 12),
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13)),
          const Spacer(),
          Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tenantController = context.watch<TenantController>();
    final tenants = tenantController.tenants;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Danh sách Khách thuê', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
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
                  onChanged: (val) => tenantController.search(val),
                  style: const TextStyle(color: Color(0xFF1C1917)),
                  decoration: InputDecoration(
                    hintText: 'Tìm theo tên, sđt hoặc số phòng...',
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

              // List View
              Expanded(
                child: tenantController.isLoading
                    ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
                    : tenants.isEmpty
                        ? const Center(child: Text('Không tìm thấy khách thuê nào.', style: TextStyle(color: Colors.grey)))
                        : ListView.builder(
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: tenants.length,
                            itemBuilder: (context, index) {
                              final tenant = tenants[index];
                              return Card(
                                color: Colors.white,
                                margin: const EdgeInsets.only(bottom: 12),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(16),
                                  side: const BorderSide(color: Color(0xFFE4E2D7)),
                                ),
                                elevation: 0,
                                child: ListTile(
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                                  title: Text(
                                    tenant.fullName,
                                    style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                                  ),
                                  subtitle: Text(
                                    'SĐT: ${tenant.phone} | Phòng: ${tenant.roomNumber ?? 'Chưa có phòng'}',
                                    style: const TextStyle(fontSize: 12, color: Colors.grey),
                                  ),
                                  trailing: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Switch(
                                        value: tenant.isActive,
                                        activeThumbColor: Colors.green,
                                        activeTrackColor: Colors.green.withValues(alpha: 0.2),
                                        inactiveThumbColor: Colors.grey,
                                        onChanged: (val) {
                                          tenantController.updateStatus(tenant.id, val ? 1 : 2);
                                        },
                                      ),
                                      IconButton(
                                        icon: const Icon(Icons.info_outline, color: Color(0xFF1C1917)),
                                        onPressed: () => _showTenantDetails(tenant),
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
}
