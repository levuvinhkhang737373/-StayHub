import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../config/currency_formatter.dart';
import '../../controllers/contract_controller.dart';
import '../../models/contract.dart';
import '../auth/login_screen.dart'; // import GridPainter

class ContractsScreen extends StatefulWidget {
  const ContractsScreen({super.key});

  @override
  State<ContractsScreen> createState() => _ContractsScreenState();
}

class _ContractsScreenState extends State<ContractsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ContractController>().fetchContracts('admin');
    });
  }

  String _nextMonthStartDateString() {
    final now = DateTime.now();
    final next = DateTime(now.year, now.month + 1, 1);
    final month = next.month.toString().padLeft(2, '0');
    final day = next.day.toString().padLeft(2, '0');
    return '${next.year}-$month-$day';
  }

  double _parseMoney(String value) {
    return parseMoney(value);
  }

  InputDecoration _inputDecoration({required String labelText, String? helperText, IconData? prefixIcon}) {
    return InputDecoration(
      labelText: labelText,
      helperText: helperText,
      prefixIcon: prefixIcon != null ? Icon(prefixIcon, size: 18, color: const Color(0xFF1C1917)) : null,
      filled: true,
      fillColor: const Color(0xFFFDFDFD),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1.5),
      ),
      labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: Colors.grey),
    );
  }

  void _showAddContractDialog() {
    final formKey = GlobalKey<FormState>();
    final codeController = TextEditingController();
    final roomController = TextEditingController();
    final tenantController = TextEditingController();
    final startController = TextEditingController(text: '2026-06-01');
    final endController = TextEditingController(text: '2026-12-01');
    final priceController = TextEditingController();
    final depositController = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
            top: 24,
            left: 24,
            right: 24,
          ),
          child: SingleChildScrollView(
            child: Form(
              key: formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('Lập Hợp đồng mới', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1C1917))),
                  const SizedBox(height: 16),
                  
                  TextFormField(
                    controller: codeController,
                    decoration: _inputDecoration(labelText: 'Số Hợp đồng', prefixIcon: Icons.assignment_outlined),
                    validator: (val) => val == null || val.isEmpty ? 'Nhập số hợp đồng' : null,
                  ),
                  const SizedBox(height: 12),
                  
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: roomController,
                          decoration: _inputDecoration(labelText: 'Số phòng', prefixIcon: Icons.meeting_room_outlined),
                          validator: (val) => val == null || val.isEmpty ? 'Nhập phòng' : null,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: tenantController,
                          decoration: _inputDecoration(labelText: 'Tên khách đại diện', prefixIcon: Icons.person_outline),
                          validator: (val) => val == null || val.isEmpty ? 'Nhập tên khách' : null,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: startController,
                          decoration: _inputDecoration(labelText: 'Ngày bắt đầu', prefixIcon: Icons.calendar_today_outlined),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: endController,
                          decoration: _inputDecoration(labelText: 'Ngày kết thúc', prefixIcon: Icons.calendar_today_outlined),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: priceController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [CurrencyInputFormatter()],
                          decoration: _inputDecoration(labelText: 'Giá thuê (đ/tháng)', prefixIcon: Icons.payments_outlined),
                          validator: (val) => val == null || val.isEmpty ? 'Nhập giá' : null,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: depositController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [CurrencyInputFormatter()],
                          decoration: _inputDecoration(labelText: 'Tiền đặt cọc (đ)', prefixIcon: Icons.wallet_outlined),
                          validator: (val) => val == null || val.isEmpty ? 'Nhập cọc' : null,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  
                  ElevatedButton(
                    onPressed: () async {
                      if (!formKey.currentState!.validate()) return;
                      
                      final success = await context.read<ContractController>().createContract(
                        contractCode: codeController.text.trim(),
                        roomNumber: roomController.text.trim(),
                        tenantName: tenantController.text.trim(),
                        startDate: startController.text,
                        endDate: endController.text,
                        rentalPrice: _parseMoney(priceController.text),
                        depositAmount: _parseMoney(depositController.text),
                      );

                      if (success && mounted) {
                        Navigator.pop(context);
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Lập hợp đồng thành công!'), backgroundColor: Colors.green),
                        );
                      } else if (mounted) {
                        final errMsg = context.read<ContractController>().errorMessage ?? 'Lập hợp đồng thất bại';
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(errMsg), backgroundColor: Colors.redAccent),
                        );
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF1C1917),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('LƯU HỢP ĐỒNG', style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  void _showExtendDialog(int contractId) {
    final controller = TextEditingController(text: '2027-01-01');
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Gia hạn hợp đồng'),
          content: TextField(
            controller: controller,
            decoration: _inputDecoration(labelText: 'Ngày hết hạn mới', prefixIcon: Icons.calendar_today_outlined),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('HỦY')),
            TextButton(
              onPressed: () async {
                final success = await context.read<ContractController>().extendContract(contractId, controller.text);
                if (success && mounted) {
                  Navigator.pop(context);
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Gia hạn thành công!'), backgroundColor: Colors.green),
                  );
                } else if (mounted) {
                  final errMsg = context.read<ContractController>().errorMessage ?? 'Gia hạn thất bại';
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(errMsg), backgroundColor: Colors.redAccent),
                  );
                }
              },
              child: const Text('GIA HẠN', style: TextStyle(color: Color(0xFF1C1917), fontWeight: FontWeight.bold)),
            ),
          ],
        );
      },
    );
  }

  void _showChangeRoomDialog(Contract contract) {
    final formKey = GlobalKey<FormState>();
    final roomController = TextEditingController();
    final movementDateController = TextEditingController(text: _nextMonthStartDateString());
    final deductionController = TextEditingController(text: '0');
    final feeController = TextEditingController(text: '0');
    final depositController = TextEditingController();
    final noteController = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 20,
            bottom: MediaQuery.of(context).viewInsets.bottom + 20,
          ),
          child: SingleChildScrollView(
            child: Form(
              key: formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: const Color(0xFF1C1917),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(Icons.swap_horiz_rounded, color: Colors.white),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Lên lịch chuyển phòng', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1C1917))),
                            Text('HĐ ${contract.contractCode} • Phòng ${contract.roomNumber}', style: const TextStyle(fontSize: 12, color: Color(0xFF78716C), fontWeight: FontWeight.w600)),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFFBEB),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFFDE68A)),
                    ),
                    child: const Text(
                      'Mobile sẽ lên lịch chuyển toàn bộ khách đang ở trong hợp đồng này. Nếu cần chọn từng người/đại diện, vui lòng dùng web admin để thao tác chi tiết hơn.',
                      style: TextStyle(fontSize: 12.5, color: Color(0xFF92400E), height: 1.45, fontWeight: FontWeight.w600),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: roomController,
                    decoration: _inputDecoration(labelText: 'Số phòng mới', prefixIcon: Icons.swap_horiz_rounded),
                    validator: (value) => value == null || value.trim().isEmpty ? 'Vui lòng nhập số phòng mới' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: movementDateController,
                    readOnly: true,
                    decoration: _inputDecoration(
                      labelText: 'Ngày chuyển cố định',
                      prefixIcon: Icons.calendar_today_outlined,
                      helperText: 'Chỉ cho chuyển vào ngày 01 của tháng kế tiếp',
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: deductionController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [CurrencyInputFormatter()],
                          decoration: _inputDecoration(labelText: 'Khấu trừ hư hao', prefixIcon: Icons.money_off_outlined),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: feeController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [CurrencyInputFormatter()],
                          decoration: _inputDecoration(labelText: 'Phí chuyển', prefixIcon: Icons.local_taxi_outlined),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: depositController,
                    keyboardType: TextInputType.number,
                    inputFormatters: [CurrencyInputFormatter()],
                    decoration: _inputDecoration(labelText: 'Cọc phòng mới (tuỳ chọn)', prefixIcon: Icons.wallet_outlined),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: noteController,
                    minLines: 2,
                    maxLines: 4,
                    decoration: _inputDecoration(labelText: 'Ghi chú', prefixIcon: Icons.note_outlined),
                  ),
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => Navigator.pop(context),
                          style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 14)),
                          child: const Text('HỦY'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () async {
                            if (!(formKey.currentState?.validate() ?? false)) return;

                            final success = await context.read<ContractController>().scheduleRoomTransfer(
                                  contractId: contract.id,
                                  newRoomNumber: roomController.text.trim(),
                                  movementDate: movementDateController.text,
                                  depositDeductionAmount: _parseMoney(deductionController.text),
                                  transferFee: _parseMoney(feeController.text),
                                  newDepositAmount: depositController.text.trim().isEmpty ? null : _parseMoney(depositController.text),
                                  note: noteController.text,
                                );

                            if (success && mounted) {
                              Navigator.pop(context);
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Đã lên lịch chuyển phòng.'), backgroundColor: Colors.green),
                              );
                            } else if (mounted) {
                              final errMsg = context.read<ContractController>().errorMessage ?? 'Lên lịch chuyển phòng thất bại';
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text(errMsg), backgroundColor: Colors.redAccent),
                              );
                            }
                          },
                          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF1C1917), foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 14)),
                          child: const Text('LÊN LỊCH', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final contractController = context.watch<ContractController>();
    final contracts = contractController.contracts;

    final activeContracts = contracts.where((c) => c.status == Contract.STATUS_ACTIVE || c.status == Contract.STATUS_DRAFT).toList();
    final inactiveContracts = contracts.where((c) => c.status == Contract.STATUS_EXPIRED || c.status == Contract.STATUS_LIQUIDATED || c.status == Contract.STATUS_CANCELLED).toList();

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F6F0),
        appBar: AppBar(
          title: const Text('Quản lý Hợp đồng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Colors.white)),
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
                labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
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
            TabBarView(
              children: [
                _buildContractList(activeContracts, contractController),
                _buildContractList(inactiveContracts, contractController),
              ],
            ),
          ],
        ),
        floatingActionButton: FloatingActionButton(
          onPressed: _showAddContractDialog,
          backgroundColor: const Color(0xFF1C1917),
          foregroundColor: const Color(0xFFEAB308),
          child: const Icon(Icons.add),
        ),
      ),
    );
  }

  Widget _buildContractList(List<Contract> filteredContracts, ContractController contractController) {
    if (filteredContracts.isEmpty) {
      return const Center(
        child: Text(
          'Không có hợp đồng nào.',
          style: TextStyle(color: Colors.grey, fontSize: 14, fontWeight: FontWeight.w500),
        ),
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: filteredContracts.length,
      itemBuilder: (context, index) {
        final contract = filteredContracts[index];
        return Card(
          color: Colors.white,
          margin: const EdgeInsets.only(bottom: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: const BorderSide(color: Color(0xFFE4E2D7)),
          ),
          elevation: 0,
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Code & Status Badge Row
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      contract.contractCode,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                    ),
                    _buildStatusBadge(contract),
                  ],
                ),
                const SizedBox(height: 12),

                // Details
                _buildInfoRow(Icons.person_outline, 'Khách thuê:', contract.tenantName),
                _buildInfoRow(Icons.meeting_room_outlined, 'Phòng:', 'Phòng ${contract.roomNumber}'),
                _buildInfoRow(Icons.calendar_today_outlined, 'Thời hạn:', '${contract.startDate} -> ${contract.endDate ?? "Chưa rõ"}'),
                _buildInfoRow(Icons.payments_outlined, 'Giá thuê:', '${formatMoney(contract.rentalPrice)} / tháng', iconColor: const Color(0xFFEAB308)),
                _buildInfoRow(Icons.wallet_outlined, 'Đặt cọc:', formatMoney(contract.depositAmount)),
                
                // Actions strip
                if (contract.status == Contract.STATUS_ACTIVE || contract.status == Contract.STATUS_EXPIRED) ...[
                  const Divider(height: 24, color: Color(0xFFE4E2D7)),
                  Row(
                    children: [
                      if (contract.status == Contract.STATUS_EXPIRED)
                        // Extend Button
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => _showExtendDialog(contract.id),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFFEAB308),
                              side: const BorderSide(color: Color(0xFFEAB308)),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Text('Gia hạn', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                          ),
                        ),
                      if (contract.status == Contract.STATUS_ACTIVE) ...[
                        // Change Room Button
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => _showChangeRoomDialog(contract),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFF1C1917),
                              side: const BorderSide(color: Color(0xFF1C1917)),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Text('Lên lịch', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                          ),
                        ),
                        const SizedBox(width: 8),

                        // Terminate Button
                        Expanded(
                          child: ElevatedButton(
                            onPressed: () async {
                              final success = await contractController.terminateContract(contract.id);
                              if (success && mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(content: Text('Hợp đồng đã chấm dứt'), backgroundColor: Colors.redAccent),
                                );
                              } else if (mounted) {
                                final errMsg = contractController.errorMessage ?? 'Chấm dứt hợp đồng thất bại';
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(errMsg), backgroundColor: Colors.redAccent),
                                );
                              }
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.redAccent,
                              foregroundColor: Colors.white,
                              elevation: 0,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Text('Kết thúc', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                          ),
                        ),
                      ],
                    ],
                  )
                ]
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value, {Color? iconColor}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Icon(icon, size: 16, color: iconColor ?? const Color(0xFF8B5E34).withValues(alpha: 0.7)),
              const SizedBox(width: 8),
              Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13, fontWeight: FontWeight.w500)),
            ],
          ),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1C1917), fontSize: 13)),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(Contract contract) {
    Color color = Colors.grey;
    if (contract.status == Contract.STATUS_ACTIVE) color = Colors.green; // Active
    if (contract.status == Contract.STATUS_EXPIRED) color = const Color(0xFFEAB308); // Expired
    if (contract.status == Contract.STATUS_LIQUIDATED) color = Colors.redAccent; // Liquidated
    if (contract.status == Contract.STATUS_CANCELLED) color = Colors.grey; // Cancelled
    if (contract.status == Contract.STATUS_DRAFT) color = Colors.grey; // Draft

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.25), width: 1),
      ),
      child: Text(
        contract.statusLabel.toUpperCase(),
        style: TextStyle(color: color, fontSize: 9.5, fontWeight: FontWeight.bold, letterSpacing: 0.5),
      ),
    );
  }
}
