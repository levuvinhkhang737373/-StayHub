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
import 'sign_contract_screen.dart';

bool _isContractRealtimeEvent(String? type, dynamic data) {
  if (type == 'contract_deposit_paid' ||
      type == 'invoice_paid' ||
      type == 'invoice_issued' ||
      type == 'invoice_reissued') {
    return true;
  }

  if (type != 'notification_sent' || data is! Map) {
    return false;
  }

  final title = data['title']?.toString().toLowerCase() ?? '';
  final content = data['content']?.toString().toLowerCase() ?? '';
  final text = '$title $content';

  return title == 'hợp đồng hết hạn' ||
      title == 'hợp đồng mới được tạo' ||
      text.contains('thanh toán đặt cọc') ||
      text.contains('tiền cọc') ||
      text.contains('chuyển phòng') ||
      text.contains('mã chuyển phòng') ||
      text.contains('thương lượng') ||
      text.contains('chấp thuận') ||
      text.contains('đồng ý') ||
      text.contains('từ chối');
}

class TenantContractScreen extends StatefulWidget {
  const TenantContractScreen({super.key});

  @override
  State<TenantContractScreen> createState() => _TenantContractScreenState();
}

class _TenantContractScreenState extends State<TenantContractScreen> with WidgetsBindingObserver {
  StreamSubscription? _wsSubscription;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final contractController = context.read<ContractController>();
      contractController.fetchContracts('tenant');

      // Lắng nghe WebSocket để tự động reload khi có thay đổi thanh toán/hợp đồng.
      final wsService = context.read<WebSocketService>();
      final tenantId = context.read<AuthController>().currentTenant?.id;
      if (tenantId != null) {
        wsService.ensureTenantNotificationChannel(tenantId);
      }

      _wsSubscription = wsService.notificationsStream.listen((event) {
        final type = event['type']?.toString();
        final data = event['data'];

        if (_isContractRealtimeEvent(type, data)) {
          debugPrint(
            'WS Event: Contract payment update received ($type). Reloading contracts...',
          );
          contractController.fetchContracts('tenant');
        }
      });
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _wsSubscription?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && mounted) {
      debugPrint('WS: App resumed, reloading contracts for tenant...');
      context.read<ContractController>().fetchContracts('tenant');
    }
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

    final activeContracts = contracts
        .where(
          (c) =>
              (c.status == Contract.STATUS_ACTIVE && c.isStaying != false) ||
              c.status == Contract.STATUS_DRAFT,
        )
        .toList();
    final inactiveContracts = contracts
        .where(
          (c) =>
              (c.status == Contract.STATUS_ACTIVE && c.isStaying == false) ||
              c.status == Contract.STATUS_EXPIRED ||
              c.status == Contract.STATUS_LIQUIDATED ||
              c.status == Contract.STATUS_CANCELLED,
        )
        .toList();

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F6F0),
        appBar: AppBar(
          title: const Text(
            'Hợp đồng của tôi',
            style: TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 18,
              color: Colors.white,
            ),
          ),
          backgroundColor: const Color(0xFF1C1917),
          elevation: 0,
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(60),
            child: Container(
              margin: const EdgeInsets.only(left: 16, right: 16, bottom: 12),
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.08),
                borderRadius: BorderRadius.circular(12),
              ),
              child: TabBar(
                indicator: BoxDecoration(
                  color: const Color(0xFFEAB308),
                  borderRadius: BorderRadius.circular(8),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.15),
                      blurRadius: 4,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                labelColor: const Color(0xFF1C1917),
                unselectedLabelColor: Colors.white.withOpacity(0.65),
                labelStyle: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 13,
                ),
                unselectedLabelStyle: const TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                ),
                indicatorSize: TabBarIndicatorSize.tab,
                dividerColor: Colors.transparent,
                tabs: const [
                  Tab(text: 'Còn hiệu lực / Chờ ký'),
                  Tab(text: 'Hết hạn / Thanh lý'),
                ],
              ),
            ),
          ),
        ),
        body: Stack(
          children: [
            Positioned.fill(child: CustomPaint(painter: GridPainter())),
            if (contractController.isLoading && contracts.isEmpty)
              const Center(
                child: CircularProgressIndicator(color: Color(0xFF1C1917)),
              )
            else if (contracts.isEmpty &&
                contractController.errorMessage != null)
              Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      _buildErrorDisplay(contractController),
                      const SizedBox(height: 24),
                      ElevatedButton.icon(
                        onPressed: () =>
                            contractController.fetchContracts('tenant'),
                        icon: const Icon(Icons.refresh_rounded),
                        label: const Text('Thử lại'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF1C1917),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 20,
                            vertical: 12,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              )
            else
              TabBarView(
                children: [
                  _buildContractList(
                    activeContracts,
                    tenant,
                    context,
                    contractController,
                  ),
                  _buildContractList(
                    inactiveContracts,
                    tenant,
                    context,
                    contractController,
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildContractList(
    List<Contract> filteredContracts,
    Tenant? tenant,
    BuildContext context,
    ContractController contractController,
  ) {
    if (filteredContracts.isEmpty) {
      return RefreshIndicator(
        color: const Color(0xFF1C1917),
        onRefresh: () => contractController.fetchContracts('tenant'),
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Container(
            height: MediaQuery.of(context).size.height * 0.6,
            alignment: Alignment.center,
            child: const Center(
              child: Text(
                'Không tìm thấy thông tin hợp đồng.',
                style: TextStyle(
                  color: Colors.grey,
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ),
        ),
      );
    }
    return RefreshIndicator(
      color: const Color(0xFF1C1917),
      onRefresh: () => contractController.fetchContracts('tenant'),
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
        itemCount: filteredContracts.length,
        itemBuilder: (context, index) {
          final c = filteredContracts[index];
          return _buildContractCard(c, tenant, context);
        },
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
          const Icon(
            Icons.error_outline_rounded,
            color: Color(0xFFDC2626),
            size: 20,
          ),
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
            icon: const Icon(
              Icons.close_rounded,
              color: Color(0xFF991B1B),
              size: 18,
            ),
            onPressed: () => controller.clearError(),
            constraints: const BoxConstraints(),
            padding: EdgeInsets.zero,
          ),
        ],
      ),
    );
  }

  Widget _buildContractCard(
    Contract contract,
    Tenant? tenant,
    BuildContext context,
  ) {
    Color statusColor = Colors.grey;
    if (contract.status == Contract.STATUS_ACTIVE)
      statusColor = const Color(0xFF16A34A);
    if (contract.status == Contract.STATUS_EXPIRED)
      statusColor = const Color(0xFFD97706);
    if (contract.status == Contract.STATUS_LIQUIDATED)
      statusColor = const Color(0xFF2563EB);
    if (contract.status == Contract.STATUS_CANCELLED)
      statusColor = const Color(0xFFDC2626);
    if (contract.status == Contract.STATUS_DRAFT)
      statusColor = const Color(0xFF4B5563);

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
                  builder: (context) => TenantContractDetailScreen(
                    contract: contract,
                    tenant: tenant,
                  ),
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
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                            color: statusColor.withOpacity(0.25),
                          ),
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
                      const Icon(
                        Icons.home_work_rounded,
                        size: 16,
                        color: Color(0xFF78716C),
                      ),
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
                      const Icon(
                        Icons.calendar_month_rounded,
                        size: 16,
                        color: Color(0xFF78716C),
                      ),
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
                  if (contract.status == Contract.STATUS_DRAFT &&
                      (tenant == null ||
                          tenant.identityNumber.isEmpty ||
                          tenant.identityDate == null ||
                          tenant.identityDate!.isEmpty ||
                          tenant.identityPlace == null ||
                          tenant.identityPlace!.isEmpty ||
                          tenant.permanentAddress == null ||
                          tenant.permanentAddress!.isEmpty)) ...[
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 8,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEF2F2),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFFFCA5A5)),
                      ),
                      child: Row(
                        children: const [
                          Icon(
                            Icons.warning_amber_rounded,
                            color: Color(0xFFDC2626),
                            size: 16,
                          ),
                          SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Thiếu thông tin định danh. Nhấp để bổ sung.',
                              style: TextStyle(
                                color: Color(0xFF991B1B),
                                fontSize: 11.5,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
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
                          Icon(
                            Icons.arrow_forward_ios_rounded,
                            size: 12,
                            color: Color(0xFF1C1917),
                          ),
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
  State<TenantContractDetailScreen> createState() =>
      _TenantContractDetailScreenState();
}

class _TenantContractDetailScreenState
    extends State<TenantContractDetailScreen> with WidgetsBindingObserver {
  StreamSubscription? _wsSubscription;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final contractController = context.read<ContractController>();
      final wsService = context.read<WebSocketService>();
      final tenantId =
          context.read<AuthController>().currentTenant?.id ?? widget.tenant?.id;
      if (tenantId != null) {
        wsService.ensureTenantNotificationChannel(tenantId);
      }

      _wsSubscription = wsService.notificationsStream.listen((event) {
        final type = event['type']?.toString();
        if (!mounted || !_isContractRealtimeEvent(type, event['data'])) return;

        debugPrint(
          'WS Event: Tenant contract detail update received ($type). Reloading contracts...',
        );
        contractController.fetchContracts('tenant');
      });
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _wsSubscription?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && mounted) {
      debugPrint('WS: App resumed in contract detail, reloading contracts for tenant...');
      context.read<ContractController>().fetchContracts('tenant');
    }
  }

  String _formatCurrency(double amount) {
    final str = amount.toStringAsFixed(0);
    final reg = RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))');
    final mathFunc = (Match match) => '${match[1]}.';
    return '${str.replaceAllMapped(reg, mathFunc)}đ';
  }

  void _showSupplementInfoDialog(BuildContext context, Tenant? tenant) {
    final formKey = GlobalKey<FormState>();
    final fullNameController = TextEditingController(
      text: tenant?.fullName ?? widget.contract.tenantName,
    );
    final identityNumberController = TextEditingController(
      text: tenant?.identityNumber ?? '',
    );
    final identityDateController = TextEditingController();
    final identityPlaceController = TextEditingController(
      text: tenant?.identityPlace ?? '',
    );
    final permanentAddressController = TextEditingController(
      text: tenant?.permanentAddress ?? '',
    );
    DateTime? selectedDate;

    final idDate = tenant?.identityDate;
    if (idDate != null && idDate.isNotEmpty) {
      try {
        selectedDate = DateTime.parse(idDate);
        final day = selectedDate.day.toString().padLeft(2, '0');
        final month = selectedDate.month.toString().padLeft(2, '0');
        final year = selectedDate.year.toString();
        identityDateController.text = '$day/$month/$year';
      } catch (_) {}
    }

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setStateDialog) {
            final auth = Provider.of<AuthController>(context);

            Future<void> pickDate() async {
              final DateTime? picked = await showDatePicker(
                context: context,
                initialDate:
                    selectedDate ??
                    DateTime.now().subtract(const Duration(days: 365 * 18)),
                firstDate: DateTime(1900),
                lastDate: DateTime.now(),
              );
              if (picked != null) {
                setStateDialog(() {
                  selectedDate = picked;
                  final day = picked.day.toString().padLeft(2, '0');
                  final month = picked.month.toString().padLeft(2, '0');
                  final year = picked.year.toString();
                  identityDateController.text = '$day/$month/$year';
                });
              }
            }

            return AlertDialog(
              backgroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
              title: const Text(
                'Bổ sung thông tin cá nhân',
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF1C1917),
                  fontSize: 16,
                ),
              ),
              content: Form(
                key: formKey,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      TextFormField(
                        controller: fullNameController,
                        style: const TextStyle(
                          color: Color(0xFF1C1917),
                          fontSize: 14,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Họ và tên',
                        ),
                        validator: (val) => val == null || val.trim().isEmpty
                            ? 'Vui lòng nhập họ và tên'
                            : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: identityNumberController,
                        keyboardType: TextInputType.number,
                        style: const TextStyle(
                          color: Color(0xFF1C1917),
                          fontSize: 14,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Số CMND/CCCD/Hộ chiếu',
                        ),
                        validator: (val) {
                          if (val == null || val.trim().isEmpty)
                            return 'Vui lòng nhập số định danh';
                          if (val.trim().length < 9)
                            return 'Số định danh không hợp lệ';
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      InkWell(
                        onTap: pickDate,
                        child: IgnorePointer(
                          child: TextFormField(
                            controller: identityDateController,
                            style: const TextStyle(
                              color: Color(0xFF1C1917),
                              fontSize: 14,
                            ),
                            decoration: const InputDecoration(
                              labelText: 'Ngày cấp',
                              suffixIcon: Icon(Icons.calendar_today, size: 16),
                            ),
                            validator: (val) => val == null || val.isEmpty
                                ? 'Vui lòng chọn ngày cấp'
                                : null,
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: identityPlaceController,
                        style: const TextStyle(
                          color: Color(0xFF1C1917),
                          fontSize: 14,
                        ),
                        decoration: const InputDecoration(labelText: 'Nơi cấp'),
                        validator: (val) => val == null || val.trim().isEmpty
                            ? 'Vui lòng nhập nơi cấp'
                            : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: permanentAddressController,
                        maxLines: 2,
                        style: const TextStyle(
                          color: Color(0xFF1C1917),
                          fontSize: 14,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Địa chỉ thường trú',
                        ),
                        validator: (val) => val == null || val.trim().isEmpty
                            ? 'Vui lòng nhập địa chỉ thường trú'
                            : null,
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(dialogContext),
                  child: const Text(
                    'HỦY',
                    style: TextStyle(color: Colors.grey),
                  ),
                ),
                TextButton(
                  onPressed: auth.isLoading
                      ? null
                      : () async {
                          if (!formKey.currentState!.validate()) return;
                          if (selectedDate == null) return;

                          final year = selectedDate!.year.toString();
                          final month = selectedDate!.month.toString().padLeft(
                            2,
                            '0',
                          );
                          final day = selectedDate!.day.toString().padLeft(
                            2,
                            '0',
                          );
                          final dateDbStr = '$year-$month-$day';

                          final success = await auth.updateTenantProfile(
                            fullName: fullNameController.text.trim(),
                            identityNumber: identityNumberController.text
                                .trim(),
                            identityType: 1, // CCCD
                            identityDate: dateDbStr,
                            identityPlace: identityPlaceController.text.trim(),
                            permanentAddress: permanentAddressController.text
                                .trim(),
                          );

                          if (success) {
                            Navigator.pop(dialogContext);
                            if (context.mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(
                                  content: Text(
                                    'Bổ sung thông tin cá nhân thành công!',
                                  ),
                                  backgroundColor: Colors.green,
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                              // Refresh auth user session and contract listing
                              context.read<AuthController>().checkSession();
                              context.read<ContractController>().fetchContracts(
                                'tenant',
                              );
                            }
                          } else {
                            if (context.mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(
                                    auth.errorMessage ??
                                        'Cập nhật thất bại. Vui lòng thử lại.',
                                  ),
                                  backgroundColor: Colors.redAccent,
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            }
                          }
                        },
                  child: auth.isLoading
                      ? const SizedBox(
                          height: 16,
                          width: 16,
                          child: CircularProgressIndicator(
                            color: Color(0xFF1C1917),
                            strokeWidth: 2,
                          ),
                        )
                      : const Text(
                          'LƯU LẠI',
                          style: TextStyle(
                            color: Color(0xFF1C1917),
                            fontWeight: FontWeight.bold,
                          ),
                        ),
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
    final contractController = context.watch<ContractController>();
    final contract = contractController.contracts.firstWhere(
      (c) => c.id == widget.contract.id,
      orElse: () => widget.contract,
    );
    final authController = context.watch<AuthController>();
    final tenant = authController.currentTenant ?? widget.tenant;
    final hasPaymentDue =
        contract.status == Contract.STATUS_ACTIVE &&
        contract.paymentDueAmount > 0;

    final isProfileIncomplete =
        tenant == null ||
        tenant.identityNumber.isEmpty ||
        tenant.identityDate == null ||
        tenant.identityDate!.isEmpty ||
        tenant.identityPlace == null ||
        tenant.identityPlace!.isEmpty ||
        tenant.permanentAddress == null ||
        tenant.permanentAddress!.isEmpty;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: Text(
          contract.contractCode,
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: const Color(0xFF1C1917),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      bottomNavigationBar: contract.status == Contract.STATUS_DRAFT
          ? SafeArea(
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 12,
                ),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(
                    top: BorderSide(color: Color(0xFFE4E2D7), width: 1),
                  ),
                ),
                child: SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () async {
                      final signed = await Navigator.push<bool>(
                        context,
                        MaterialPageRoute(
                          builder: (context) =>
                              SignContractScreen(contract: contract),
                        ),
                      );
                      if (signed == true) {
                        contractController.fetchContracts('tenant');
                      }
                    },
                    icon: const Icon(Icons.draw_rounded),
                    label: const Text(
                      'KÝ HỢP ĐỒNG THUÊ PHÒNG',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF1C1917),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(
                        vertical: 16,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                      elevation: 0,
                    ),
                  ),
                ),
          : null,
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
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Yêu cầu thương lượng giá trước đó của bạn đã bị quản lý từ chối. Bạn có thể ký hợp đồng theo giá cũ hoặc tiếp tục đề xuất mức thương lượng khác.',
                            style: TextStyle(
                              fontSize: 12.5,
                              color: Color(0xFFB91C1C),
                              height: 1.45,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  if (contract.status == Contract.STATUS_DRAFT &&
                      isProfileIncomplete) ...[
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFFBEB),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: const Color(0xFFFDE68A),
                          width: 1.5,
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: const [
                              Icon(
                                Icons.warning_amber_rounded,
                                color: Color(0xFFD97706),
                                size: 20,
                              ),
                              SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'Yêu cầu bổ sung thông tin định danh',
                                  style: TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFF92400E),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Hợp đồng này chưa đầy đủ thông tin định danh của bạn. Vui lòng bấm vào nút "KÝ HỢP ĐỒNG THUÊ PHÒNG" ở phía dưới để bổ sung và ký kết.',
                            style: TextStyle(
                              fontSize: 12.5,
                              color: Color(0xFFB45309),
                              height: 1.45,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  if (contract.status == Contract.STATUS_ACTIVE &&
                      isProfileIncomplete) ...[
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEF2F2),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: const Color(0xFFFCA5A5),
                          width: 1.5,
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: const [
                              Icon(
                                Icons.error_outline_rounded,
                                color: Color(0xFFDC2626),
                                size: 20,
                              ),
                              SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  'Hồ sơ hợp đồng chưa đầy đủ thông tin',
                                  style: TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFF991B1B),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'Hợp đồng đã có hiệu lực nhưng hồ sơ định danh của bạn vẫn chưa đầy đủ thông tin (CCCD/CMND, ngày cấp, nơi cấp, địa chỉ thường trú).',
                            style: TextStyle(
                              fontSize: 12.5,
                              color: Color(0xFF7F1D1D),
                              height: 1.45,
                            ),
                          ),
                          const SizedBox(height: 12),
                          SizedBox(
                            width: double.infinity,
                            child: ElevatedButton.icon(
                              onPressed: () =>
                                  _showSupplementInfoDialog(context, tenant),
                              icon: const Icon(Icons.edit_note_rounded),
                              label: const Text('BỔ SUNG THÔNG TIN NGAY'),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFFDC2626),
                                foregroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(
                                  vertical: 12,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                elevation: 0,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  // VietQR Payment Panel: deposit due or room-transfer settlement due.
                  if (hasPaymentDue) ...[
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
          const Icon(
            Icons.error_outline_rounded,
            color: Color(0xFFDC2626),
            size: 20,
          ),
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
            icon: const Icon(
              Icons.close_rounded,
              color: Color(0xFF991B1B),
              size: 18,
            ),
            onPressed: () => controller.clearError(),
            constraints: const BoxConstraints(),
            padding: EdgeInsets.zero,
          ),
        ],
      ),
    );
  }

  Widget _buildPaymentPanel(Contract contract, ContractController controller) {
    final isTransferSettlement = contract.hasTransferSettlementDue;
    final transferSettlement = contract.transferSettlement;
    final paymentTitle = isTransferSettlement
        ? 'Thanh toán khoản chuyển phòng (VietQR)'
        : 'Thanh toán đặt cọc (VietQR)';
    final paymentDescription = isTransferSettlement
        ? 'Khoản chuyển phòng dùng mã TRF-* làm nội dung. Hệ thống sẽ tự tách tiền vào cọc mới trước, phần còn lại ghi nhận phí/khấu trừ.'
        : 'Để kích hoạt hợp đồng, vui lòng quét mã VietQR bên dưới hoặc thực hiện chuyển khoản với thông tin:';
    final paymentAmount = contract.paymentDueAmount;
    final paymentReference = contract.paymentReferenceCode;
    final paymentQrUrl = contract.paymentQrUrl;

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFBEB), // Light amber background
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: const Color(0xFFFDE68A),
          width: 1.5,
        ), // Amber border
        boxShadow: [
          BoxShadow(
            color: Colors.amber.withValues(alpha: 0.04),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Icon(
                Icons.qr_code_scanner_rounded,
                color: Color(0xFFD97706),
                size: 24,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  paymentTitle,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF92400E),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            paymentDescription,
            style: const TextStyle(
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
                  isTransferSettlement
                      ? 'Tổng còn phải thanh toán:'
                      : 'Số tiền:',
                  _formatCurrency(paymentAmount),
                  isBold: true,
                  valueColor: const Color(0xFFDC2626),
                ),
                if (isTransferSettlement && transferSettlement != null) ...[
                  const Divider(height: 20, color: Color(0xFFFEF3C7)),
                  _buildPaymentDetailRow(
                    'Cọc mới còn thiếu:',
                    _formatCurrency(transferSettlement.depositRemainingAmount),
                    valueColor: transferSettlement.depositRemainingAmount > 0
                        ? const Color(0xFFDC2626)
                        : const Color(0xFF16A34A),
                  ),
                  const Divider(height: 20, color: Color(0xFFFEF3C7)),
                  _buildPaymentDetailRow(
                    'Phí/khấu trừ còn thiếu:',
                    _formatCurrency(transferSettlement.extraRemainingAmount),
                    valueColor: transferSettlement.extraRemainingAmount > 0
                        ? const Color(0xFFDC2626)
                        : const Color(0xFF16A34A),
                  ),
                ],
                const Divider(height: 20, color: Color(0xFFFEF3C7)),
                _buildPaymentDetailRowWithCopy(
                  'Nội dung chuyển khoản:',
                  paymentReference,
                  context,
                ),
                if (isTransferSettlement) ...[
                  const Divider(height: 20, color: Color(0xFFFEF3C7)),
                  _buildPaymentDetailRow('Mã hợp đồng:', contract.contractCode),
                ],
              ],
            ),
          ),
          if (isTransferSettlement) ...[
            const SizedBox(height: 12),
            _buildTransferSettlementNote(),
          ],
          const SizedBox(height: 20),
          // QR Image if present
          if (paymentQrUrl != null && paymentQrUrl.isNotEmpty)
            Center(
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFFDE68A), width: 1),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.04),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Image.network(
                    paymentQrUrl,
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
                            Icon(
                              Icons.broken_image_rounded,
                              color: Colors.grey,
                              size: 48,
                            ),
                            SizedBox(height: 8),
                            Text(
                              'Không thể tải ảnh QR',
                              style: TextStyle(
                                color: Colors.grey,
                                fontSize: 12,
                              ),
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
                          content: Text(
                            'Đã cập nhật trạng thái hợp đồng mới nhất.',
                          ),
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

  Widget _buildPaymentDetailRow(
    String label,
    String value, {
    bool isBold = false,
    Color? valueColor,
  }) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(
              color: Color(0xFF78716C),
              fontSize: 13,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
              color: valueColor ?? const Color(0xFF1C1917),
              fontSize: 13,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildPaymentDetailRowWithCopy(
    String label,
    String value,
    BuildContext context,
  ) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  color: Color(0xFF78716C),
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
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
          icon: const Icon(
            Icons.copy_rounded,
            color: Color(0xFF92400E),
            size: 20,
          ),
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

  Widget _buildTransferSettlementNote() {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF7ED),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFFED7AA)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Icon(
            Icons.account_balance_wallet_outlined,
            color: Color(0xFFEA580C),
            size: 18,
          ),
          SizedBox(width: 8),
          Expanded(
            child: Text(
              'Sau khi SePay ghi nhận, backend ưu tiên bù cọc mới còn thiếu; phần dư của giao dịch mới ghi vào phí chuyển phòng/khấu trừ.',
              style: TextStyle(
                fontSize: 12,
                height: 1.4,
                color: Color(0xFF9A3412),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTransferSettlementCard(TransferSettlement settlement) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFBEB),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFFDE68A)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFF59E0B).withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.swap_horiz_rounded,
                  color: Color(0xFFD97706),
                  size: 20,
                ),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Text(
                  'Quyết toán chuyển phòng',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                    color: Color(0xFF92400E),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _buildInfoRow(
            'Mã chuyển phòng:',
            settlement.transferCode.isNotEmpty ? settlement.transferCode : '—',
            valueColor: const Color(0xFF92400E),
            isBold: true,
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Cọc mới phải bù:',
            _formatCurrency(settlement.depositDueAmount),
            valueColor: const Color(0xFF1C1917),
            isBold: true,
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Đã ghi vào cọc:',
            _formatCurrency(settlement.depositPaidAmount),
            valueColor: const Color(0xFF16A34A),
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Cọc mới còn thiếu:',
            _formatCurrency(settlement.depositRemainingAmount),
            valueColor: settlement.depositRemainingAmount > 0
                ? const Color(0xFFDC2626)
                : const Color(0xFF16A34A),
            isBold: true,
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Phí chuyển phòng:',
            _formatCurrency(settlement.transferFee),
            valueColor: const Color(0xFF92400E),
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Khấu trừ:',
            _formatCurrency(settlement.deductionAmount),
            valueColor: const Color(0xFF92400E),
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Phí/khấu trừ phải thu:',
            _formatCurrency(settlement.extraChargeAmount),
            valueColor: const Color(0xFF1C1917),
            isBold: true,
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Đã ghi vào phí/khấu trừ:',
            _formatCurrency(settlement.extraPaidAmount),
            valueColor: const Color(0xFF16A34A),
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Phí/khấu trừ còn thiếu:',
            _formatCurrency(settlement.extraRemainingAmount),
            valueColor: settlement.extraRemainingAmount > 0
                ? const Color(0xFFDC2626)
                : const Color(0xFF16A34A),
            isBold: true,
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Đã thanh toán tổng:',
            _formatCurrency(settlement.settlementPaidAmount),
            valueColor: const Color(0xFF16A34A),
          ),
          const Divider(height: 20, color: Color(0xFFFEF3C7)),
          _buildInfoRow(
            'Còn phải thanh toán:',
            _formatCurrency(settlement.settlementRemainingAmount),
            valueColor: settlement.settlementRemainingAmount > 0
                ? const Color(0xFFDC2626)
                : const Color(0xFF16A34A),
            isBold: true,
          ),
          if (settlement.settlementPaymentStatusLabel != null &&
              settlement.settlementPaymentStatusLabel!.isNotEmpty) ...[
            const Divider(height: 20, color: Color(0xFFFEF3C7)),
            _buildInfoRow(
              'Trạng thái:',
              settlement.settlementPaymentStatusLabel!,
              valueColor: const Color(0xFFD97706),
              isBold: true,
            ),
          ],
          const SizedBox(height: 12),
          _buildTransferSettlementNote(),
        ],
      ),
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
              const Icon(
                Icons.home_work_rounded,
                color: Color(0xFFE4E2D7),
                size: 20,
              ),
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
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.bold,
            color: Color(0xFF78716C),
            letterSpacing: 1.0,
          ),
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
                'Khách thuê đại diện:',
                contract.tenantName,
                isBold: true,
              ),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow(
                'Số điện thoại:',
                contract.representativeTenant?.phone ?? tenant?.phone ?? '',
              ),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow(
                'Email:',
                contract.representativeTenant?.email ?? tenant?.email ?? '',
              ),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow('Ngày bắt đầu:', contract.startDate),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow('Ngày hết hạn:', contract.endDate ?? 'Vô thời hạn'),
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow(
                'Chu kỳ đóng tiền:',
                'Ngày ${contract.billingCycleDay} hàng tháng',
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildFinancialCard(Contract contract) {
    final depositPaid = contract.isDepositPaid;
    final hasDepositDue = contract.depositDueAmount > 0;
    final transferSettlement = contract.transferSettlement;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'THÔNG TIN TÀI CHÍNH',
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.bold,
            color: Color(0xFF78716C),
            letterSpacing: 1.0,
          ),
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
              if (hasDepositDue && transferSettlement == null) ...[
                const Divider(height: 20, color: Color(0xFFF1F0EA)),
                _buildInfoRow(
                  'Cọc còn thiếu:',
                  _formatCurrency(contract.depositDueAmount),
                  valueColor: const Color(0xFFDC2626),
                  isBold: true,
                ),
              ],
              if (transferSettlement != null) ...[
                const Divider(height: 20, color: Color(0xFFF1F0EA)),
                _buildTransferSettlementCard(transferSettlement),
              ],
              const Divider(height: 20, color: Color(0xFFF1F0EA)),
              _buildInfoRow(
                'Trạng thái cọc:',
                depositPaid ? 'Đã nhận cọc' : 'Chưa thanh toán cọc',
                valueColor: depositPaid
                    ? const Color(0xFF16A34A)
                    : const Color(0xFFDC2626),
                isBold: true,
              ),
              if (contract.paymentStatusLabel != null &&
                  contract.paymentStatusLabel!.isNotEmpty) ...[
                const Divider(height: 20, color: Color(0xFFF1F0EA)),
                _buildInfoRow(
                  'Trạng thái thanh toán:',
                  contract.paymentStatusLabel!,
                  valueColor: depositPaid
                      ? const Color(0xFF16A34A)
                      : const Color(0xFFD97706),
                ),
              ],
              if (contract.roomServices != null &&
                  contract.roomServices!.isNotEmpty) ...[
                const Divider(height: 20, color: Color(0xFFF1F0EA)),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: const [
                        Icon(
                          Icons.room_service_rounded,
                          size: 16,
                          color: Color(0xFF78716C),
                        ),
                        SizedBox(width: 8),
                        Text(
                          'CÁC DỊCH VỤ CỦA PHÒNG',
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF78716C),
                            letterSpacing: 0.5,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    ...contract.roomServices!.map((svc) {
                      final priceVal = svc['price'] != null
                          ? double.tryParse(svc['price'].toString()) ?? 0.0
                          : 0.0;
                      return Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              svc['name']?.toString() ?? '',
                              style: const TextStyle(
                                fontSize: 13,
                                color: Color(0xFF44403C),
                              ),
                            ),
                            Text(
                              '${_formatCurrency(priceVal)} / ${svc['unit_name'] ?? 'tháng'}',
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF1C1917),
                              ),
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  ],
                ),
              ],
              // Contract Vehicles Section
              if (contract.contractVehicles != null &&
                  contract.contractVehicles!.isNotEmpty) ...[
                const Divider(height: 20, color: Color(0xFFF1F0EA)),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: const [
                        Icon(
                          Icons.two_wheeler_rounded,
                          size: 16,
                          color: Color(0xFF78716C),
                        ),
                        SizedBox(width: 8),
                        Text(
                          'XE GỬI TRONG HỢP ĐỒNG',
                          style: TextStyle(
                            fontSize: 10.5,
                            fontWeight: FontWeight.bold,
                            color: Color(0xFF78716C),
                            letterSpacing: 0.5,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    ...contract.contractVehicles!
                        .where((v) => v['is_active'] != false)
                        .map((v) {
                      final vehicleData =
                          v['vehicle'] as Map<String, dynamic>?;
                      final licensePlate =
                          vehicleData?['license_plate']?.toString() ??
                              'Xe #${v['vehicle_id']}';
                      final typeLabel =
                          vehicleData?['vehicle_type_label']?.toString() ?? '';
                      final feeVal = v['monthly_fee'] != null
                          ? double.tryParse(v['monthly_fee'].toString()) ?? 0.0
                          : 0.0;
                      return Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Flexible(
                              child: Text(
                                typeLabel.isNotEmpty
                                    ? '$typeLabel: $licensePlate'
                                    : 'Xe: $licensePlate',
                                style: const TextStyle(
                                  fontSize: 13,
                                  color: Color(0xFF44403C),
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            Text(
                              '${_formatCurrency(feeVal)} / tháng',
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF1C1917),
                              ),
                            ),
                          ],
                        ),
                      );
                    }).toList(),
                  ],
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
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.bold,
            color: Color(0xFF78716C),
            letterSpacing: 1.0,
          ),
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
                  isPdf
                      ? Icons.picture_as_pdf_rounded
                      : Icons.description_rounded,
                  color: isPdf
                      ? const Color(0xFFDC2626)
                      : const Color(0xFF1C1917),
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
                  icon: const Icon(
                    Icons.copy_rounded,
                    color: Color(0xFF1C1917),
                    size: 20,
                  ),
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

  Widget _buildInfoRow(
    String label,
    String value, {
    Color? valueColor,
    bool isBold = false,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(color: Colors.grey, fontSize: 13),
            ),
          ),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(
                fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
                color: valueColor ?? const Color(0xFF1C1917),
                fontSize: 13,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(int status, String label) {
    Color color = Colors.grey;
    if (status == Contract.STATUS_ACTIVE)
      color = const Color(0xFF16A34A); // Green
    if (status == Contract.STATUS_EXPIRED)
      color = const Color(0xFFD97706); // Amber
    if (status == Contract.STATUS_LIQUIDATED)
      color = const Color(0xFF2563EB); // Blue
    if (status == Contract.STATUS_CANCELLED)
      color = const Color(0xFFDC2626); // Red
    if (status == Contract.STATUS_DRAFT)
      color = const Color(0xFF4B5563); // Gray-600

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withOpacity(0.25), width: 1),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

}
