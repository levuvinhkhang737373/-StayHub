import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
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
                    decoration: const InputDecoration(labelText: 'Số Hợp đồng', border: OutlineInputBorder()),
                    validator: (val) => val == null || val.isEmpty ? 'Nhập số hợp đồng' : null,
                  ),
                  const SizedBox(height: 12),
                  
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: roomController,
                          decoration: const InputDecoration(labelText: 'Số phòng', border: OutlineInputBorder()),
                          validator: (val) => val == null || val.isEmpty ? 'Nhập phòng' : null,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: tenantController,
                          decoration: const InputDecoration(labelText: 'Tên khách đại diện', border: OutlineInputBorder()),
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
                          decoration: const InputDecoration(labelText: 'Ngày bắt đầu', border: OutlineInputBorder()),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: endController,
                          decoration: const InputDecoration(labelText: 'Ngày kết thúc', border: OutlineInputBorder()),
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
                          decoration: const InputDecoration(labelText: 'Giá thuê (đ/tháng)', border: OutlineInputBorder()),
                          validator: (val) => val == null || val.isEmpty ? 'Nhập giá' : null,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: depositController,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(labelText: 'Tiền đặt cọc (đ)', border: OutlineInputBorder()),
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
                        rentalPrice: double.parse(priceController.text),
                        depositAmount: double.parse(depositController.text),
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
            decoration: const InputDecoration(labelText: 'Ngày hết hạn mới', border: OutlineInputBorder()),
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

  void _showChangeRoomDialog(int contractId) {
    final roomController = TextEditingController();
    final priceController = TextEditingController();
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Chuyển phòng khách thuê'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: roomController,
                decoration: const InputDecoration(labelText: 'Số phòng mới', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: priceController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Giá thuê mới', border: OutlineInputBorder()),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('HỦY')),
            TextButton(
              onPressed: () async {
                if (roomController.text.isNotEmpty && priceController.text.isNotEmpty) {
                  final success = await context.read<ContractController>().changeRoom(
                        contractId,
                        roomController.text.trim(),
                        double.parse(priceController.text),
                      );
                  if (success && mounted) {
                    Navigator.pop(context);
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Chuyển phòng thành công!'), backgroundColor: Colors.green),
                    );
                  } else if (mounted) {
                    final errMsg = context.read<ContractController>().errorMessage ?? 'Chuyển phòng thất bại';
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(errMsg), backgroundColor: Colors.redAccent),
                    );
                  }
                }
              },
              child: const Text('CHUYỂN PHÒNG', style: TextStyle(color: Color(0xFF1C1917), fontWeight: FontWeight.bold)),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final contractController = context.watch<ContractController>();
    final contracts = contractController.contracts;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Quản lý Hợp đồng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          contracts.isEmpty
              ? const Center(child: Text('Không có hợp đồng nào.', style: TextStyle(color: Colors.grey)))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: contracts.length,
                  itemBuilder: (context, index) {
                    final contract = contracts[index];
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
                            _buildInfoRow('Khách thuê:', contract.tenantName),
                            _buildInfoRow('Phòng:', 'Phòng ${contract.roomNumber}'),
                            _buildInfoRow('Thời hạn:', '${contract.startDate} -> ${contract.endDate}'),
                            _buildInfoRow('Giá thuê:', '${contract.rentalPrice.toStringAsFixed(0)}đ / tháng'),
                            _buildInfoRow('Đặt cọc:', '${contract.depositAmount.toStringAsFixed(0)}đ'),
                            
                            // Actions strip
                            if (contract.isActive) ...[
                              const Divider(height: 24, color: Color(0xFFE4E2D7)),
                              Row(
                                children: [
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
                                  const SizedBox(width: 8),

                                  // Change Room Button
                                  Expanded(
                                    child: OutlinedButton(
                                      onPressed: () => _showChangeRoomDialog(contract.id),
                                      style: OutlinedButton.styleFrom(
                                        foregroundColor: const Color(0xFF1C1917),
                                        side: const BorderSide(color: Color(0xFF1C1917)),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                      ),
                                      child: const Text('Chuyển phòng', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
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
                              )
                            ]
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _showAddContractDialog,
        backgroundColor: const Color(0xFF1C1917),
        foregroundColor: const Color(0xFFEAB308),
        child: const Icon(Icons.add),
      ),
    );
  }

  Widget _buildInfoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13)),
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
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        contract.statusLabel,
        style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold),
      ),
    );
  }
}
