import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantBuildingSettingsScreen extends StatefulWidget {
  const TenantBuildingSettingsScreen({super.key});

  @override
  State<TenantBuildingSettingsScreen> createState() => _TenantBuildingSettingsScreenState();
}

class _TenantBuildingSettingsScreenState extends State<TenantBuildingSettingsScreen> {
  bool _isLoading = true;
  String _errorMessage = '';
  List<Map<String, dynamic>> _settings = [];

  @override
  void initState() {
    super.initState();
    _fetchSettings();
  }

  Future<void> _fetchSettings() async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    try {
      final apiService = ApiService();
      final response = await apiService.get<List<dynamic>>(
        'tenant/building-settings',
        fromJsonT: (json) => json as List<dynamic>,
      );

      if (response.status && response.result != null) {
        if (mounted) {
          setState(() {
            _settings = response.result!
                .map((item) => Map<String, dynamic>.from(item as Map))
                .toList();
            _isLoading = false;
          });
        }
      } else {
        if (mounted) {
          setState(() {
            _errorMessage = response.message;
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Không thể tải cấu hình tòa nhà: $e';
          _isLoading = false;
        });
      }
    }
  }

  IconData _getIconForLabel(String label) {
    final lower = label.toLowerCase();
    if (lower.contains('hotline') || lower.contains('điện thoại') || lower.contains('phone')) {
      return Icons.phone_in_talk_outlined;
    }
    if (lower.contains('email') || lower.contains('thư')) {
      return Icons.mail_outline_rounded;
    }
    if (lower.contains('giờ') || lower.contains('yên tĩnh') || lower.contains('khung giờ') || lower.contains('khách')) {
      return Icons.access_time_rounded;
    }
    if (lower.contains('ngày') || lower.contains('tiền phòng') || lower.contains('thanh toán')) {
      return Icons.calendar_month_outlined;
    }
    return Icons.settings_suggest_outlined;
  }

  Color _getColorForLabel(String label) {
    final lower = label.toLowerCase();
    if (lower.contains('hotline')) return const Color(0xFFEAB308); // Gold
    if (lower.contains('email')) return Colors.blueAccent;
    if (lower.contains('yên tĩnh')) return Colors.teal;
    if (lower.contains('ngày')) return Colors.deepOrangeAccent;
    return const Color(0xFF78716C); // Stone/grey
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text(
          'Quy định & Hỗ trợ',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: const Color(0xFF1C1917),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Colors.white),
            onPressed: _fetchSettings,
          ),
        ],
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          RefreshIndicator(
            onRefresh: _fetchSettings,
            color: const Color(0xFF1C1917),
            child: _buildBody(),
          ),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(
          valueColor: AlwaysStoppedAnimation<Color>(Color(0xFFEAB308)),
        ),
      );
    }

    if (_errorMessage.isNotEmpty) {
      return SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const SizedBox(height: 80),
              const Icon(Icons.error_outline_rounded, size: 60, color: Colors.redAccent),
              const SizedBox(height: 16),
              Text(
                _errorMessage,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Color(0xFF1C1917), fontSize: 14, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 24),
              ElevatedButton.icon(
                onPressed: _fetchSettings,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Thử lại'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1C1917),
                  foregroundColor: Colors.white,
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (_settings.isEmpty) {
      return SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(24),
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: const [
              SizedBox(height: 120),
              Icon(Icons.settings, size: 80, color: Colors.grey),
              SizedBox(height: 16),
              Text(
                'Tòa nhà của bạn chưa có cấu hình cài đặt nào được công khai.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.grey, fontSize: 14, fontWeight: FontWeight.bold),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
      itemCount: _settings.length,
      itemBuilder: (context, index) {
        final setting = _settings[index];
        final label = setting['setting_label'] ?? 'Cấu hình';
        final value = setting['setting_value'] ?? '';
        final description = setting['description'] ?? 'Chưa có mô tả chi tiết.';
        final buildingName = setting['building_name'];
        final isGlobal = buildingName == null;
        
        final icon = _getIconForLabel(label);
        final color = _getColorForLabel(label);

        return Card(
          color: Colors.white,
          margin: const EdgeInsets.only(bottom: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
            side: const BorderSide(color: Color(0xFFE4E2D7)),
          ),
          elevation: 0,
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: color.withOpacity(0.08),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Icon(icon, color: color, size: 24),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            label,
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF1C1917),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: isGlobal ? const Color(0xFFE7E5E4) : const Color(0xFFFEF08A),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              isGlobal ? 'Hệ thống chung' : buildingName,
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: isGlobal ? const Color(0xFF57534E) : const Color(0xFF854D0E),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const Divider(height: 24, color: Color(0xFFE4E2D7)),
                const Text(
                  'GIÁ TRỊ CẤU HÌNH',
                  style: TextStyle(
                    fontSize: 9,
                    fontWeight: FontWeight.bold,
                    color: Colors.grey,
                    letterSpacing: 1.0,
                  ),
                ),
                const SizedBox(height: 6),
                SelectableText(
                  value,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: color,
                  ),
                ),
                if (description.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  const Text(
                    'MÔ TẢ / HƯỚNG DẪN',
                    style: TextStyle(
                      fontSize: 9,
                      fontWeight: FontWeight.bold,
                      color: Colors.grey,
                      letterSpacing: 1.0,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    description,
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF44403C),
                      height: 1.4,
                    ),
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}
