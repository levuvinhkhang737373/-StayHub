import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/invoice_controller.dart';
import '../../models/invoice.dart';
import '../auth/login_screen.dart'; // import GridPainter

class InvoicesScreen extends StatefulWidget {
  const InvoicesScreen({super.key});

  @override
  State<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends State<InvoicesScreen> {
  int _selectedFilter = 0; // 0: All, 1: Unpaid, 2: Paid, 3: Overdue

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<InvoiceController>().fetchInvoices(isAdmin: true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final invoiceController = context.watch<InvoiceController>();
    final allInvoices = invoiceController.invoices;

    final invoices = allInvoices.where((inv) {
      if (_selectedFilter == 1) return inv.status == 2 || inv.status == 3;
      if (_selectedFilter == 2) return inv.status == 4;
      if (_selectedFilter == 3) return inv.status == 5;
      return true;
    }).toList();

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Quản lý Hóa đơn', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          Column(
            children: [
              // Filter Buttons Scroll strip
              Container(
                height: 60,
                padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 16),
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  children: [
                    _buildFilterChip('Tất cả', 0),
                    _buildFilterChip('Chưa thanh toán', 1),
                    _buildFilterChip('Đã thanh toán', 2),
                    _buildFilterChip('Quá hạn', 3),
                  ],
                ),
              ),

              // Invoices list
              Expanded(
                child: invoiceController.isLoading
                    ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
                    : invoices.isEmpty
                        ? const Center(child: Text('Không tìm thấy hóa đơn nào.', style: TextStyle(color: Colors.grey)))
                        : ListView.builder(
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            itemCount: invoices.length,
                            itemBuilder: (context, index) {
                              final invoice = invoices[index];
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
                                    crossAxisAlignment: CrossAxisAlignment.stretch,
                                    children: [
                                      // Top Code & Status Row
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Text(
                                            invoice.invoiceCode,
                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF1C1917)),
                                          ),
                                          _buildStatusBadge(invoice),
                                        ],
                                      ),
                                      const SizedBox(height: 12),

                                      // Detail info
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Text('Phòng: ${invoice.roomNumber}', style: const TextStyle(color: Colors.grey, fontSize: 13)),
                                          Text('Hạn đóng: ${invoice.dueDate}', style: const TextStyle(color: Colors.grey, fontSize: 13)),
                                        ],
                                      ),
                                      const Divider(height: 24, color: Color(0xFFE4E2D7)),

                                      // Amount row
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          const Text('Tổng tiền:', style: TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1C1917))),
                                          Text(
                                            '${invoice.totalAmount.toStringAsFixed(0)}đ',
                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF1C1917)),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 16),

                                      // Actions strip
                                      if (invoice.isUnpaid || invoice.isOverdue)
                                        Row(
                                          children: [
                                            // Debt Reminder Button (mock push)
                                            Expanded(
                                              child: OutlinedButton.icon(
                                                onPressed: () async {
                                                  final success = await invoiceController.sendDebtReminder(invoice.id);
                                                  if (success && mounted) {
                                                    ScaffoldMessenger.of(context).showSnackBar(
                                                      const SnackBar(content: Text('Đã gửi thông báo nhắc nợ thành công!'), backgroundColor: Colors.amber),
                                                    );
                                                  }
                                                },
                                                icon: const Icon(Icons.notifications_active_outlined, size: 18),
                                                label: const Text('Nhắc nợ'),
                                                style: OutlinedButton.styleFrom(
                                                  foregroundColor: const Color(0xFFEAB308),
                                                  side: const BorderSide(color: Color(0xFFEAB308)),
                                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                                ),
                                              ),
                                            ),
                                            const SizedBox(width: 12),

                                            // Confirm Payment Button
                                            Expanded(
                                              child: ElevatedButton(
                                                onPressed: () async {
                                                  final success = await invoiceController.confirmPayment(invoice.id);
                                                  if (success && mounted) {
                                                    ScaffoldMessenger.of(context).showSnackBar(
                                                      const SnackBar(content: Text('Xác nhận thanh toán thành công!'), backgroundColor: Colors.green),
                                                    );
                                                  }
                                                },
                                                style: ElevatedButton.styleFrom(
                                                  backgroundColor: const Color(0xFF1C1917),
                                                  foregroundColor: Colors.white,
                                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                                  elevation: 0,
                                                ),
                                                child: const Text('Xác nhận thu'),
                                              ),
                                            ),
                                          ],
                                        )
                                      else
                                        Row(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          children: const [
                                            Icon(Icons.check_circle, color: Colors.green, size: 20),
                                            SizedBox(width: 6),
                                            Text(
                                              'Giao dịch đã được hoàn tất',
                                              style: TextStyle(color: Colors.green, fontSize: 13, fontWeight: FontWeight.bold),
                                            ),
                                          ],
                                        )
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

  Widget _buildFilterChip(String label, int filterIndex) {
    final isSelected = _selectedFilter == filterIndex;
    return Padding(
      padding: const EdgeInsets.only(right: 8.0),
      child: FilterChip(
        selected: isSelected,
        label: Text(label),
        labelStyle: TextStyle(
          color: isSelected ? const Color(0xFFEAB308) : const Color(0xFF78716C),
          fontWeight: FontWeight.bold,
          fontSize: 12,
        ),
        backgroundColor: Colors.white,
        selectedColor: const Color(0xFF1C1917),
        checkmarkColor: const Color(0xFFEAB308),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: const BorderSide(color: Color(0xFFE4E2D7)),
        ),
        onSelected: (val) {
          setState(() {
            _selectedFilter = filterIndex;
          });
        },
      ),
    );
  }

  Widget _buildStatusBadge(Invoice invoice) {
    Color color = Colors.grey;
    if (invoice.status == 4) color = Colors.green; // Paid
    if (invoice.status == 2 || invoice.status == 3) color = const Color(0xFFEAB308); // Unpaid
    if (invoice.status == 5) color = Colors.redAccent; // Overdue

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        invoice.statusLabel,
        style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.bold),
      ),
    );
  }
}
