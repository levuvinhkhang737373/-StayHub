import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantUtilityScreen extends StatelessWidget {
  const TenantUtilityScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final tenant = authController.currentTenant;
    final roomNumber = tenant?.roomNumber ?? '101';

    // Mock history data based on room number
    final List<Map<String, dynamic>> electricHistory = _getElectricHistoryForRoom(roomNumber);
    final List<Map<String, dynamic>> waterHistory = _getWaterHistoryForRoom(roomNumber);

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F6F0),
        appBar: AppBar(
          title: const Text('Chỉ số Điện Nước', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          backgroundColor: const Color(0xFF1C1917),
          bottom: const TabBar(
            indicatorColor: Color(0xFFEAB308),
            labelColor: Color(0xFFEAB308),
            unselectedLabelColor: Colors.grey,
            tabs: [
              Tab(icon: Icon(Icons.bolt, size: 20), text: 'Điện tiêu thụ'),
              Tab(icon: Icon(Icons.water_drop, size: 20), text: 'Nước tiêu thụ'),
            ],
          ),
        ),
        body: Stack(
          children: [
            Positioned.fill(child: CustomPaint(painter: GridPainter())),
            TabBarView(
              children: [
                _buildUtilityTab(context, electricHistory, isElectric: true),
                _buildUtilityTab(context, waterHistory, isElectric: false),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildUtilityTab(BuildContext context, List<Map<String, dynamic>> history, {required bool isElectric}) {
    if (history.isEmpty) {
      return const Center(child: Text('Không tìm thấy dữ liệu chỉ số.', style: TextStyle(color: Colors.grey)));
    }

    final latest = history.first;
    final unit = isElectric ? 'kWh' : 'm³';
    final rate = isElectric ? 3500 : 15000;
    final themeColor = isElectric ? Colors.amber : Colors.blue;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Current Month Dashboard Card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: const Color(0xFFE4E2D7)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.02),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              children: [
                Text(
                  'CHỈ SỐ THÁNG ${latest['month']}/${latest['year']}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey, letterSpacing: 1.0),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    _buildBigCounter('Chỉ số cũ', '${latest['oldValue']}', Colors.grey),
                    Icon(Icons.arrow_forward_rounded, color: themeColor, size: 24),
                    _buildBigCounter('Chỉ số mới', '${latest['newValue']}', themeColor),
                  ],
                ),
                const Divider(height: 32, color: Color(0xFFE4E2D7)),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Tiêu thụ thực tế', style: TextStyle(color: Colors.grey, fontSize: 12)),
                        const SizedBox(height: 4),
                        Text(
                          '+${latest['consumption']} $unit',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: themeColor),
                        ),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text('Đơn giá', style: TextStyle(color: Colors.grey, fontSize: 12)),
                        const SizedBox(height: 4),
                        Text(
                          '${rate.toStringAsFixed(0)}đ/$unit',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // History List Section
          const Text(
            'LỊCH SỬ TIÊU THỤ CÁC THÁNG',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
          ),
          const SizedBox(height: 12),
          ListView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: history.length,
            itemBuilder: (context, index) {
              final record = history[index];
              final cost = record['consumption'] * rate;

              return Card(
                color: Colors.white,
                margin: const EdgeInsets.only(bottom: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: const BorderSide(color: Color(0xFFE4E2D7)),
                ),
                elevation: 0,
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Tháng ${record['month']}/${record['year']}',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF1C1917)),
                          ),
                          Text(
                            '${cost.toStringAsFixed(0)}đ',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF1C1917)),
                          ),
                        ],
                      ),
                      const Divider(height: 20, color: Color(0xFFE4E2D7)),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Chỉ số: ${record['oldValue']} → ${record['newValue']} ($unit)', style: const TextStyle(color: Colors.grey, fontSize: 13)),
                          Text(
                            'Dùng: ${record['consumption']} $unit',
                            style: TextStyle(fontWeight: FontWeight.w600, color: themeColor, fontSize: 13),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton.icon(
                          onPressed: () => _showEvidenceDialog(context, record, isElectric),
                          icon: Icon(Icons.image_outlined, size: 16, color: themeColor),
                          label: Text(
                            'Xem ảnh minh chứng',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: themeColor),
                          ),
                          style: TextButton.styleFrom(
                            minimumSize: Size.zero,
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _buildBigCounter(String label, String value, Color color) {
    return Column(
      children: [
        Text(label, style: const TextStyle(color: Colors.grey, fontSize: 11)),
        const SizedBox(height: 6),
        Text(
          value,
          style: TextStyle(fontSize: 28, fontWeight: FontWeight.w900, color: color),
        ),
      ],
    );
  }

  void _showEvidenceDialog(BuildContext context, Map<String, dynamic> record, bool isElectric) {
    showDialog(
      context: context,
      builder: (context) {
        return Dialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AppBar(
                title: Text('Minh chứng Tháng ${record['month']}/${record['year']}'),
                backgroundColor: const Color(0xFF1C1917),
                automaticallyImplyLeading: false,
                actions: [
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
                shape: const RoundedRectangleBorder(
                  borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(20.0),
                child: Column(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Image.network(
                        record['imageUrl'],
                        height: 200,
                        width: double.infinity,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) => Container(
                          height: 200,
                          color: const Color(0xFFF9F8F6),
                          child: const Center(child: Icon(Icons.broken_image, size: 50, color: Colors.grey)),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Loại đồng hồ:', style: TextStyle(color: Colors.grey)),
                        Text(
                          isElectric ? 'Đồng hồ điện tử' : 'Đồng hồ nước',
                          style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Ngày ghi nhận:', style: TextStyle(color: Colors.grey)),
                        Text(
                          record['recordedAt'],
                          style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  // Mock data generators
  List<Map<String, dynamic>> _getElectricHistoryForRoom(String roomNumber) {
    if (roomNumber == '101') {
      return [
        {
          'month': 5,
          'year': 2026,
          'oldValue': 1100,
          'newValue': 1240,
          'consumption': 140,
          'recordedAt': '28/05/2026',
          'imageUrl': 'https://images.unsplash.com/photo-1558346490-a72e53ae2d4f?auto=format&fit=crop&q=80&w=400',
        },
        {
          'month': 4,
          'year': 2026,
          'oldValue': 950,
          'newValue': 1100,
          'consumption': 150,
          'recordedAt': '28/04/2026',
          'imageUrl': 'https://images.unsplash.com/photo-1558346490-a72e53ae2d4f?auto=format&fit=crop&q=80&w=400',
        },
      ];
    } else if (roomNumber == '102') {
      return [
        {
          'month': 5,
          'year': 2026,
          'oldValue': 780,
          'newValue': 890,
          'consumption': 110,
          'recordedAt': '28/05/2026',
          'imageUrl': 'https://images.unsplash.com/photo-1558346490-a72e53ae2d4f?auto=format&fit=crop&q=80&w=400',
        }
      ];
    } else {
      return [
        {
          'month': 5,
          'year': 2026,
          'oldValue': 100,
          'newValue': 220,
          'consumption': 120,
          'recordedAt': '28/05/2026',
          'imageUrl': 'https://images.unsplash.com/photo-1558346490-a72e53ae2d4f?auto=format&fit=crop&q=80&w=400',
        }
      ];
    }
  }

  List<Map<String, dynamic>> _getWaterHistoryForRoom(String roomNumber) {
    if (roomNumber == '101') {
      return [
        {
          'month': 5,
          'year': 2026,
          'oldValue': 320,
          'newValue': 342,
          'consumption': 22,
          'recordedAt': '28/05/2026',
          'imageUrl': 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&q=80&w=400',
        },
        {
          'month': 4,
          'year': 2026,
          'oldValue': 302,
          'newValue': 320,
          'recordedAt': '28/04/2026',
          'consumption': 18,
          'imageUrl': 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&q=80&w=400',
        },
      ];
    } else {
      return [
        {
          'month': 5,
          'year': 2026,
          'oldValue': 50,
          'newValue': 65,
          'consumption': 15,
          'recordedAt': '28/05/2026',
          'imageUrl': 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&q=80&w=400',
        }
      ];
    }
  }
}
