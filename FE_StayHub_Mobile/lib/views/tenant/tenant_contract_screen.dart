import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/contract_controller.dart';
import '../../models/contract.dart';
import '../../models/tenant.dart';
import '../../services/websocket_service.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantContractScreen extends StatefulWidget {
  const TenantContractScreen({super.key});

  @override
  State<TenantContractScreen> createState() => _TenantContractScreenState();
}

class _TenantContractScreenState extends State<TenantContractScreen> {
  StreamSubscription? _wsSubscription;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final contractController = context.read<ContractController>();
      contractController.fetchContracts('tenant');

      // Lắng nghe WebSocket để tự động reload khi đóng cọc thành công
      final wsService = context.read<WebSocketService>();
      _wsSubscription = wsService.notificationsStream.listen((event) {
        if (event['type'] == 'contract_deposit_paid') {
          debugPrint('WS Event: Contract deposit paid. Reloading contracts...');
          contractController.fetchContracts('tenant');
        } else if (event['type'] == 'notification_sent') {
          final data = event['data'] as Map<String, dynamic>?;
          if (data != null && (data['title'] == 'Hợp đồng hết hạn' || data['title'] == 'Hợp đồng mới được tạo')) {
            debugPrint('WS Event: Contract update notification received (${data['title']}). Reloading contracts...');
            contractController.fetchContracts('tenant');
          }
        }
      });
    });
  }

  @override
  void dispose() {
    _wsSubscription?.cancel();
    super.dispose();
  }

  String _formatCurrency(double amount) {
    final str = amount.toStringAsFixed(0);
    final reg = RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))');
    final mathFunc = (Match match) => '${match[1]}.';
    return '${str.replaceAllMapped(reg, mathFunc)}đ';
  }

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final tenant = authController.currentTenant;

    final contractController = context.watch<ContractController>();
    final contracts = contractController.contracts;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Hợp đồng của tôi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          if (contractController.isLoading && contracts.isEmpty)
            const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
          else if (contracts.isEmpty)
            Center(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (contractController.errorMessage != null) ...[
                      _buildErrorDisplay(contractController),
                      const SizedBox(height: 24),
                    ],
                    const Icon(Icons.description_outlined, color: Colors.grey, size: 64),
                    const SizedBox(height: 16),
                    Text(
                      contractController.errorMessage != null
                          ? 'Đã xảy ra lỗi khi tải thông tin.'
                          : 'Không tìm thấy thông tin hợp đồng.',
                      style: const TextStyle(color: Colors.grey, fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                    const SizedBox(height: 20),
                    ElevatedButton.icon(
                      onPressed: () => contractController.fetchContracts('tenant'),
                      icon: const Icon(Icons.refresh_rounded),
                      label: const Text('Thử lại'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF1C1917),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                  ],
                ),
              ),
            )
          else
            RefreshIndicator(
              color: const Color(0xFF1C1917),
              onRefresh: () => contractController.fetchContracts('tenant'),
              child: ListView.builder(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
                itemCount: contracts.length,
                itemBuilder: (context, index) {
                  final c = contracts[index];
                  return _buildContractCard(c, tenant, context);
                },
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildErrorDisplay(ContractController controller) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFFCA5A5)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline_rounded, color: Color(0xFFDC2626), size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              controller.errorMessage!,
              style: const TextStyle(
                color: Color(0xFF991B1B),
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close_rounded, color: Color(0xFF991B1B), size: 18),
            onPressed: () => controller.clearError(),
            constraints: const BoxConstraints(),
            padding: EdgeInsets.zero,
          ),
        ],
      ),
    );
  }

  Widget _buildContractCard(Contract contract, Tenant? tenant, BuildContext context) {
    Color statusColor = Colors.grey;
    if (contract.status == Contract.STATUS_ACTIVE) statusColor = const Color(0xFF16A34A);
    if (contract.status == Contract.STATUS_EXPIRED) statusColor = const Color(0xFFD97706);
    if (contract.status == Contract.STATUS_LIQUIDATED) statusColor = const Color(0xFF2563EB);
    if (contract.status == Contract.STATUS_CANCELLED) statusColor = const Color(0xFFDC2626);

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE4E2D7)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => TenantContractDetailScreen(contract: contract, tenant: tenant),
                ),
              );
            },
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          contract.contractCode,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF1C1917),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                        decoration: BoxDecoration(
                          color: statusColor.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: statusColor.withOpacity(0.25)),
                        ),
                        child: Text(
                          contract.statusLabel,
                          style: TextStyle(
                            color: statusColor,
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      const Icon(Icons.home_work_rounded, size: 16, color: Color(0xFF78716C)),
                      const SizedBox(width: 8),
                      Text(
                        'Phòng ${contract.roomNumber}',
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF44403C),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.calendar_month_rounded, size: 16, color: Color(0xFF78716C)),
                      const SizedBox(width: 8),
                      Text(
                        '${contract.startDate} -> ${contract.endDate ?? "Vô thời hạn"}',
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF78716C),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  const Divider(height: 1, color: Color(0xFFF1F0EA)),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'TIỀN THUÊ',
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF78716C),
                              letterSpacing: 1.0,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            _formatCurrency(contract.roomPrice),
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF1C1917),
                            ),
                          ),
                        ],
                      ),
                      Row(
                        children: const [
                          Text(
                            'Xem chi tiết',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                              color: Color(0xFF1C1917),
                            ),
                          ),
                          SizedBox(width: 4),
                          Icon(Icons.arrow_forward_ios_rounded, size: 12, color: Color(0xFF1C1917)),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class TenantContractDetailScreen extends StatefulWidget {
  final Contract contract;
  final Tenant? tenant;

  const TenantContractDetailScreen({
    super.key,
    required this.contract,
    this.tenant,
  });

  @override
  State<TenantContractDetailScreen> createState() => _TenantContractDetailScreenState();
}

class _TenantContractDetailScreenState extends State<TenantContractDetailScreen> {
  String _formatCurrency(double amount) {
    final str = amount.toStringAsFixed(0);
    final reg = RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))');
    final mathFunc = (Match match) => '${match[1]}.';
    return '${str.replaceAllMapped(reg, mathFunc)}đ';
  }

  @override
  Widget build(BuildContext context) {
    final contractController = context.watch<ContractController>();
    final contract = contractController.contracts.firstWhere(
      (c) => c.id == widget.contract.id,
      orElse: () => widget.contract,
    );
    final tenant = widget.tenant;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: Text(contract.contractCode, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          RefreshIndicator(
            color: const Color(0xFF1C1917),
            onRefresh: () => contractController.fetchContracts('tenant'),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (contractController.errorMessage != null) ...[
                    _buildErrorDisplay(contractController),
                    const SizedBox(height: 16),
                  ],
                  // VietQR Deposit Payment Panel (displayed if not paid)
                  if (!contract.isDepositPaid) ...[
                    _buildPaymentPanel(contract, contractController),
                    const SizedBox(height: 8),
                  ],
                  // Header Card
                  _buildHeaderCard(contract, tenant),
                  const SizedBox(height: 24),
                  // General Info
                  _buildGeneralInfoCard(contract, tenant),
                  const SizedBox(height: 24),
                  // Financial Details
                  _buildFinancialCard(contract),
                  const SizedBox(height: 24),
                  // Attachments List
                  _buildAttachmentsSection(contract, context),
                  // Terms Card
                  _buildTermsCard(),
                  const SizedBox(height: 40),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorDisplay(ContractController controller) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFFCA5A5)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline_rounded, color: Color(0xFFDC2626), size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              controller.errorMessage!,
              style: const TextStyle(
                color: Color(0xFF991B1B),
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close_rounded, color: Color(0xFF991B1B), size: 18),
            onPressed: () => controller.clearError(),
            constraints: const BoxConstraints(),
            padding: EdgeInsets.zero,
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentPanel(Contract contract, ContractController controller) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFBEB), // Light amber background
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFFDE68A), width: 1.5), // Amber border
        boxShadow: [
          BoxShadow(
            color: Colors.amber.withOpacity(0.04),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: const [
              Icon(Icons.qr_code_scanner_rounded, color: Color(0xFFD97706), size: 24),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Thanh toán đặt cọc (VietQR)',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF92400E),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Text(
            'Để kích hoạt hợp đồng, vui lòng quét mã VietQR bên dưới hoặc thực hiện chuyển khoản với thông tin:',
            style: TextStyle(
              fontSize: 13,
              color: Color(0xFFB45309),
              height: 1.4,
            ),
          ),
          const SizedBox(height: 16),
          // Details Card
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFFEF3C7)),
            ),
            child: Column(
              children: [
                _buildPaymentDetailRow(
                  'Số tiền:',
                  _formatCurrency(contract.depositAmount),
                  isBold: true,
                  valueColor: const Color(0xFFDC2626),
                ),
                const Divider(height: 20, color: Color(0xFFFEF3C7)),
                _buildPaymentDetailRowWithCopy(
                  'Nội dung chuyển khoản:',
                  'COC ${contract.contractCode}',
                  context,
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          // QR Image if present
          if (contract.depositQrUrl != null && contract.depositQrUrl!.isNotEmpty)
            Center(
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFFDE68A), width: 1),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.04),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    contract.depositQrUrl!,
                    height: 220,
                    width: 220,
                    fit: BoxFit.contain,
                    loadingBuilder: (context, child, loadingProgress) {
                      if (loadingProgress == null) return child;
                      return SizedBox(
                        height: 220,
                        width: 220,
                        child: Center(
                          child: CircularProgressIndicator(
                            color: const Color(0xFFD97706),
                            value: loadingProgress.expectedTotalBytes != null
                                ? loadingProgress.cumulativeBytesLoaded /
                                    loadingProgress.expectedTotalBytes!
                                : null,
                          ),
                        ),
                      );
                    },
                    errorBuilder: (context, error, stackTrace) {
                      return SizedBox(
                        height: 220,
                        width: 220,
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: const [
                            Icon(Icons.broken_image_rounded, color: Colors.grey, size: 48),
                            SizedBox(height: 8),
                            Text(
                              'Không thể tải ảnh QR',
                              style: TextStyle(color: Colors.grey, fontSize: 12),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
              ),
            ),
          const SizedBox(height: 20),
          // Action button
          ElevatedButton.icon(
            onPressed: controller.isLoading
                ? null
                : () async {
                    await controller.fetchContracts('tenant');
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Đã cập nhật trạng thái hợp đồng mới nhất.'),
                          backgroundColor: Color(0xFF1C1917),
                          behavior: SnackBarBehavior.floating,
                        ),
                      );
                    }
                  },
            icon: controller.isLoading
                ? const SizedBox(
                    height: 16,
                    width: 16,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                  )
                : const Icon(Icons.refresh_rounded, size: 18),
            label: const Text(
              'CẬP NHẬT TRẠNG THÁI',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF92400E),
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              elevation: 0,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentDetailRow(String label, String value, {bool isBold = false, Color? valueColor}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: Color(0xFF78716C), fontSize: 13, fontWeight: FontWeight.w500)),
        Text(
          value,
          style: TextStyle(
            fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
            color: valueColor ?? const Color(0xFF1C1917),
            fontSize: 13,
          ),
        ),
      ],
    );
  }

  Widget _buildPaymentDetailRowWithCopy(String label, String value, BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(color: Color(0xFF78716C), fontSize: 13, fontWeight: FontWeight.w500)),
              const SizedBox(height: 4),
              SelectableText(
                value,
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF1C1917),
                  fontSize: 15,
                  letterSpacing: 0.5,
                ),
              ),
            ],
          ),
        ),
        IconButton(
          icon: const Icon(Icons.copy_rounded, color: Color(0xFF92400E), size: 20),
          onPressed: () {
            Clipboard.setData(ClipboardData(text: value));
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(
                content: Text('Đã sao chép nội dung chuyển khoản.'),
                backgroundColor: Color(0xFF1C1917),
                behavior: SnackBarBehavior.floating,
                duration: Duration(seconds: 2),
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _buildHeaderCard(Contract contract, Tenant? tenant) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1C1917), Color(0xFF37312E)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.12),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'MÃ HỢP ĐỒNG',
                      style: TextStyle(
                        color: Color(0xFFEAB308),
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 1.5,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      contract.contractCode,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                        letterSpacing: 0.5,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              _buildStatusBadge(contract.status, contract.statusLabel),
            ],
          ),
          const SizedBox(height: 20),
          const Divider(color: Colors.white24, height: 1),
          const SizedBox(height: 16),
          Row(
            children: [
              const Icon(Icons.home_work_rounded, color: Color(0xFFE4E2D7), size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Phòng ${contract.roomNumber} • ${tenant?.buildingName ?? "StayHub"}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildGeneralInfoCard(Contract contract, Tenant? tenant) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'THÔNG TIN CHI TIẾT',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE4E2D7)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.02),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            children: [
              _buildInfoRow('Khách thuê đại diện:', contract.tenantName, isBold: true),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow('Số điện thoại:', contract.representativeTenant?.phone ?? tenant?.phone ?? ''),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow('Email:', contract.representativeTenant?.email ?? tenant?.email ?? ''),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow('Ngày bắt đầu:', contract.startDate),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow('Ngày hết hạn:', contract.endDate ?? 'Vô thời hạn'),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow('Chu kỳ đóng tiền:', 'Ngày ${contract.billingCycleDay} hàng tháng'),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildFinancialCard(Contract contract) {
    final depositPaid = contract.isDepositPaid;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'THÔNG TIN TÀI CHÍNH',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFE4E2D7)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.02),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            children: [
              _buildInfoRow(
                'Giá thuê phòng:',
                _formatCurrency(contract.roomPrice),
                valueColor: const Color(0xFF1C1917),
                isBold: true,
              ),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow(
                'Tiền đặt cọc:',
                _formatCurrency(contract.depositAmount),
                valueColor: const Color(0xFF1C1917),
                isBold: true,
              ),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow(
                'Trạng thái cọc:',
                depositPaid ? 'Đã nhận cọc' : 'Chưa thanh toán cọc',
                valueColor: depositPaid ? const Color(0xFF16A34A) : const Color(0xFFDC2626),
                isBold: true,
              ),
              if (contract.paymentStatusLabel != null && contract.paymentStatusLabel!.isNotEmpty) ...[
                const Divider(height: 20, color: Color(0xFFF1F0EA)),
                _buildInfoRow(
                  'Trạng thái thanh toán:',
                  contract.paymentStatusLabel!,
                  valueColor: depositPaid ? const Color(0xFF16A34A) : const Color(0xFFD97706),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildAttachmentsSection(Contract contract, BuildContext context) {
    final files = contract.contractFiles ?? [];
    if (files.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'TỆP ĐÍNH KÈM HỢP ĐỒNG',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
        ),
        const SizedBox(height: 12),
        ...files.asMap().entries.map((entry) {
          final index = entry.key;
          final url = entry.value;
          // Clean filename from URL if possible
          String fileName = 'Tài liệu đính kèm ${index + 1}';
          try {
            final uri = Uri.parse(url);
            final name = uri.pathSegments.last;
            if (name.isNotEmpty) {
              fileName = Uri.decodeComponent(name);
            }
          } catch (_) {}

          final isPdf = fileName.toLowerCase().endsWith('.pdf');

          return Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE4E2D7)),
            ),
            child: Row(
              children: [
                Icon(
                  isPdf ? Icons.picture_as_pdf_rounded : Icons.description_rounded,
                  color: isPdf ? const Color(0xFFDC2626) : const Color(0xFF1C1917),
                  size: 24,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        fileName,
                        style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                          color: Color(0xFF1C1917),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      const Text(
                        'Nhấn sao chép liên kết để mở trình duyệt',
                        style: TextStyle(color: Colors.grey, fontSize: 11),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.copy_rounded, color: Color(0xFF1C1917), size: 20),
                  onPressed: () {
                    Clipboard.setData(ClipboardData(text: url));
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Đã sao chép liên kết tài liệu.'),
                        backgroundColor: Color(0xFF1C1917),
                        behavior: SnackBarBehavior.floating,
                        duration: Duration(seconds: 2),
                      ),
                    );
                  },
                ),
              ],
            ),
          );
        }).toList(),
        const SizedBox(height: 16),
      ],
    );
  }

  Widget _buildTermsCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFBEB),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFFDE68A)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.gavel_rounded, color: Color(0xFFD97706), size: 22),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Text(
                  'Lưu ý điều khoản hợp đồng',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                    color: Color(0xFF92400E),
                  ),
                ),
                SizedBox(height: 6),
                Text(
                  'Vui lòng thông báo cho Ban quản lý ít nhất 30 ngày trước khi trả phòng hoặc muốn gia hạn thêm hợp đồng thuê.',
                  style: TextStyle(
                    fontSize: 12.5,
                    color: Color(0xFFB45309),
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(String label, String value, {Color? valueColor, bool isBold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13)),
          Text(
            value,
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
              color: valueColor ?? const Color(0xFF1C1917),
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(int status, String label) {
    Color color = Colors.grey;
    if (status == Contract.STATUS_ACTIVE) color = const Color(0xFF16A34A); // Green
    if (status == Contract.STATUS_EXPIRED) color = const Color(0xFFD97706); // Amber
    if (status == Contract.STATUS_LIQUIDATED) color = const Color(0xFF2563EB); // Blue
    if (status == Contract.STATUS_CANCELLED) color = const Color(0xFFDC2626); // Red
    if (status == Contract.STATUS_DRAFT) color = const Color(0xFF4B5563); // Gray-600

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withOpacity(0.25), width: 1),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.bold),
      ),
    );
  }
}
