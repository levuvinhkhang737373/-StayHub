import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/invoice_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantInvoicesScreen extends StatefulWidget {
  const TenantInvoicesScreen({super.key});

  @override
  State<TenantInvoicesScreen> createState() => _TenantInvoicesScreenState();
}

class _TenantInvoicesScreenState extends State<TenantInvoicesScreen> {
  void _showPaymentBottomSheet(dynamic invoice) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
            top: 24,
            left: 24,
            right: 24,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Thanh toán Hóa đơn',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFF7F6F0),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE4E2D7)),
                ),
                child: Column(
                  children: [
                    _buildModalRow('Mã hóa đơn:', invoice.invoiceCode),
                    const SizedBox(height: 8),
                    _buildModalRow('Kỳ thanh toán:', 'Tháng ${invoice.billingMonth}/${invoice.billingYear}'),
                    const SizedBox(height: 8),
                    _buildModalRow('Số tiền cần thanh toán:', '${invoice.remainingAmount.toStringAsFixed(0)}đ'),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Phương thức thanh toán',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917)),
              ),
              const SizedBox(height: 12),
              ListTile(
                leading: const Icon(Icons.account_balance, color: Color(0xFF1C1917)),
                title: const Text('Chuyển khoản Ngân hàng (VietQR)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                subtitle: const Text('Tự động xác nhận sau 1-3 phút', style: TextStyle(fontSize: 12)),
                trailing: const Icon(Icons.radio_button_checked, color: Color(0xFFEAB308)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: const BorderSide(color: Color(0xFFEAB308), width: 1.5),
                ),
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: () async {
                  final success = await context.read<InvoiceController>().payInvoice(invoice.id);
                  if (success && mounted) {
                    Navigator.pop(context);
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text('Thanh toán hóa đơn ${invoice.invoiceCode} thành công!'),
                        backgroundColor: Colors.green,
                      ),
                    );
                  }
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1C1917),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('XÁC NHẬN THANH TOÁN', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
              const SizedBox(height: 32),
            ],
          ),
        );
      },
    );
  }

  Widget _buildModalRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917), fontSize: 13)),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final authController = context.watch<AuthController>();
    final tenant = authController.currentTenant;

    final invoiceController = context.watch<InvoiceController>();
    final roomNumber = tenant?.roomNumber ?? '101';
    final invoices = invoiceController.getInvoicesForRoom(roomNumber);

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Hóa đơn của tôi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          invoices.isEmpty
              ? const Center(child: Text('Không tìm thấy hóa đơn nào.', style: TextStyle(color: Colors.grey)))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: invoices.length,
                  itemBuilder: (context, index) {
                    final invoice = invoices[index];
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
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  invoice.invoiceCode,
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                                ),
                                _buildStatusBadge(invoice.status, invoice.statusLabel),
                              ],
                            ),
                            const SizedBox(height: 12),
                            _buildInfoRow('Kỳ thanh toán:', 'Tháng ${invoice.billingMonth}/${invoice.billingYear}'),
                            _buildInfoRow('Tổng số tiền:', '${invoice.totalAmount.toStringAsFixed(0)}đ'),
                            _buildInfoRow('Đã thanh toán:', '${invoice.paidAmount.toStringAsFixed(0)}đ'),
                            _buildInfoRow('Còn lại:', '${invoice.remainingAmount.toStringAsFixed(0)}đ'),
                            _buildInfoRow('Hạn thanh toán:', invoice.dueDate),
                            
                            if (invoice.isUnpaid) ...[
                              const Divider(height: 24, color: Color(0xFFE4E2D7)),
                              ElevatedButton.icon(
                                onPressed: () => _showPaymentBottomSheet(invoice),
                                icon: const Icon(Icons.payment, size: 18, color: Color(0xFF1C1917)),
                                label: const Text('Thanh toán ngay', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFFEAB308),
                                  foregroundColor: const Color(0xFF1C1917),
                                  elevation: 0,
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                ),
                              ),
                            ]
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

  Widget _buildStatusBadge(int status, String label) {
    Color color = Colors.grey;
    if (status == 4) color = Colors.green; // Paid
    if (status == 2 || status == 3) color = const Color(0xFFEAB308); // Unpaid, partially paid
    if (status == 5) color = Colors.redAccent; // Overdue

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold),
      ),
    );
  }
}
