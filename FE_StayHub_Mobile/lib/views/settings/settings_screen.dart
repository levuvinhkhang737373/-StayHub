import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../services/websocket_service.dart';
import '../auth/login_screen.dart'; // import GridPainter

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  Future<void> _handleLogout() async {
    context.read<WebSocketService>().disconnect();
    final authController = context.read<AuthController>();
    final success = await authController.logout();
    if (success && mounted) {
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13, fontWeight: FontWeight.bold)),
          const SizedBox(width: 16),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: const TextStyle(color: Color(0xFF1C1917), fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  void _showChangePasswordDialog() {
    final formKeyDialog = GlobalKey<FormState>();
    final currentPasswordController = TextEditingController();
    final newPasswordController = TextEditingController();
    final confirmPasswordController = TextEditingController();

    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            final auth = Provider.of<AuthController>(context);
            return AlertDialog(
              backgroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              title: const Text(
                'Thay đổi mật khẩu',
                style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
              ),
              content: Form(
                key: formKeyDialog,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(
                        controller: currentPasswordController,
                        obscureText: true,
                        style: const TextStyle(color: Color(0xFF1C1917)),
                        decoration: InputDecoration(
                          labelText: 'Mật khẩu hiện tại',
                          filled: true,
                          fillColor: const Color(0xFFF9F8F6),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF1C1917)),
                          ),
                        ),
                        validator: (val) => val == null || val.isEmpty ? 'Nhập mật khẩu hiện tại' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: newPasswordController,
                        obscureText: true,
                        style: const TextStyle(color: Color(0xFF1C1917)),
                        decoration: InputDecoration(
                          labelText: 'Mật khẩu mới',
                          filled: true,
                          fillColor: const Color(0xFFF9F8F6),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF1C1917)),
                          ),
                        ),
                        validator: (val) {
                          if (val == null || val.isEmpty) return 'Nhập mật khẩu mới';
                          if (val.length < 6) return 'Mật khẩu mới tối thiểu 6 kí tự';
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: confirmPasswordController,
                        obscureText: true,
                        style: const TextStyle(color: Color(0xFF1C1917)),
                        decoration: InputDecoration(
                          labelText: 'Xác nhận mật khẩu mới',
                          filled: true,
                          fillColor: const Color(0xFFF9F8F6),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFF1C1917)),
                          ),
                        ),
                        validator: (val) {
                          if (val == null || val.isEmpty) return 'Nhập lại mật khẩu mới';
                          if (val != newPasswordController.text) return 'Xác nhận mật khẩu không khớp';
                          return null;
                        },
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('HỦY', style: TextStyle(color: Colors.grey)),
                ),
                TextButton(
                  onPressed: auth.isLoading
                      ? null
                      : () async {
                          if (!formKeyDialog.currentState!.validate()) return;

                          final success = await auth.changePassword(
                            currentPassword: currentPasswordController.text,
                            newPassword: newPasswordController.text,
                            confirmPassword: confirmPasswordController.text,
                          );

                          if (success && mounted) {
                            Navigator.pop(context);
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('Đổi mật khẩu thành công!'), backgroundColor: Colors.green),
                            );
                          } else if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(auth.errorMessage ?? 'Đổi mật khẩu thất bại'),
                                backgroundColor: Colors.redAccent,
                              ),
                            );
                          }
                        },
                  child: auth.isLoading
                      ? const SizedBox(
                          height: 16,
                          width: 16,
                          child: CircularProgressIndicator(color: Color(0xFF1C1917), strokeWidth: 2),
                        )
                      : const Text('CẬP NHẬT', style: TextStyle(color: Color(0xFF1C1917), fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final isAdmin = authController.isAdmin;
    final admin = authController.currentAdmin;
    final tenant = authController.currentTenant;

    final String displayName = isAdmin ? (admin?.fullName ?? 'Admin') : (tenant?.fullName ?? 'Khách thuê');
    final String displaySubtitle = isAdmin 
        ? (admin?.roleLabel ?? 'Quản lý tòa nhà') 
        : 'Khách thuê • Phòng ${tenant?.roomNumber ?? "Chưa có phòng"}';
    final String? avatarUrl = isAdmin ? admin?.avatarUrl : tenant?.avatarUrl;
    final String initial = displayName.isNotEmpty ? displayName.substring(0, 1).toUpperCase() : 'U';

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: Row(
          children: const [
            Icon(
              Icons.home_work_rounded,
              color: Color(0xFFEAB308),
              size: 24,
            ),
            SizedBox(width: 8),
            Text('StayHub Settings', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18)),
          ],
        ),
        backgroundColor: const Color(0xFF1C1917),
        automaticallyImplyLeading: false,
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          SingleChildScrollView(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Profile view card
                const Text(
                  'THÔNG TIN TÀI KHOẢN',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
                ),
                const SizedBox(height: 12),
                Card(
                  color: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: const BorderSide(color: Color(0xFFE4E2D7)),
                  ),
                  elevation: 0,
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Row(
                      children: [
                        CircleAvatar(
                          radius: 28,
                          backgroundColor: const Color(0xFF1C1917),
                          backgroundImage: avatarUrl != null ? NetworkImage(avatarUrl) : null,
                          child: avatarUrl == null
                              ? Text(
                                  initial,
                                  style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white, fontSize: 20),
                                )
                              : null,
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(displayName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917))),
                              const SizedBox(height: 4),
                              Text(displaySubtitle, style: const TextStyle(color: Colors.grey, fontSize: 13)),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),

                // Detailed personal information card
                const Text(
                  'THÔNG TIN CÁ NHÂN',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
                ),
                const SizedBox(height: 12),
                Card(
                  color: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: const BorderSide(color: Color(0xFFE4E2D7)),
                  ),
                  elevation: 0,
                  child: Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: isAdmin
                          ? [
                              _buildInfoRow('Tên đăng nhập', admin?.username ?? ''),
                              _buildInfoRow('Email liên hệ', admin?.email ?? ''),
                              _buildInfoRow('Số điện thoại', admin?.phone ?? 'Chưa cập nhật'),
                              _buildInfoRow('Giới tính', admin?.genderLabel ?? 'Chưa cập nhật'),
                              _buildInfoRow('Vai trò', admin?.roleLabel ?? 'Quản lý tòa nhà'),
                              _buildInfoRow('Địa chỉ', admin?.address ?? 'Chưa cập nhật'),
                            ]
                          : [
                              _buildInfoRow('Tên đăng nhập', tenant?.username ?? ''),
                              _buildInfoRow('Email liên hệ', tenant?.email ?? ''),
                              _buildInfoRow('Số điện thoại', tenant?.phone ?? 'Chưa cập nhật'),
                              _buildInfoRow('Giới tính', tenant?.genderLabel ?? 'Chưa cập nhật'),
                              _buildInfoRow('Ngày sinh', tenant?.dateOfBirth ?? 'Chưa cập nhật'),
                              _buildInfoRow('CCCD/CMND', tenant?.identityNumber ?? 'Chưa cập nhật'),
                              _buildInfoRow('Nơi thường trú', tenant?.permanentAddress ?? 'Chưa cập nhật'),
                              _buildInfoRow('Địa chỉ hiện tại', tenant?.currentAddress ?? 'Chưa cập nhật'),
                            ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),

                // Change password button
                ElevatedButton.icon(
                  onPressed: _showChangePasswordDialog,
                  icon: const Icon(Icons.lock_outline, size: 20),
                  label: const Text('THAY ĐỔI MẬT KHẨU BẢO MẬT', style: TextStyle(fontWeight: FontWeight.bold)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF1C1917),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(height: 16),

                // Logout button
                ElevatedButton.icon(
                  onPressed: _handleLogout,
                  icon: const Icon(Icons.logout, size: 20),
                  label: const Text('ĐĂNG XUẤT TÀI KHOẢN', style: TextStyle(fontWeight: FontWeight.bold)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.redAccent,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

