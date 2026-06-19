import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/invoice_controller.dart';
import '../../models/invoice.dart';
import '../../services/websocket_service.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantInvoicesScreen extends StatefulWidget {
  const TenantInvoicesScreen({super.key});

  @override
  State<TenantInvoicesScreen> createState() => _TenantInvoicesScreenState();
}

class _TenantInvoicesScreenState extends State<TenantInvoicesScreen> {
  StreamSubscription? _socketSub;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      // Tải danh sách hóa đơn từ API
      context.read<InvoiceController>().fetchInvoices(isAdmin: false);

      // Đăng ký lắng nghe sự kiện WebSocket để cập nhật danh sách thời gian thực
      final socketService = context.read<WebSocketService>();
      _socketSub = socketService.notificationsStream.listen((event) {
        final type = event['type'];
        if (type == 'invoice_paid' || type == 'invoice_issued') {
          if (mounted) {
            context.read<InvoiceController>().fetchInvoices(isAdmin: false);
            final invoiceData = event['data'];
            final code = invoiceData != null ? invoiceData['invoice_code'] : '';
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(type == 'invoice_paid'
                    ? 'Hóa đơn $code đã được thanh toán thành công!'
                    : 'Hóa đơn mới $code vừa được phát hành!'),
                backgroundColor: type == 'invoice_paid' ? Colors.green : Colors.blue,
              ),
            );
          }
        }
      });
    });
  }

  @override
  void dispose() {
    _socketSub?.cancel();
    super.dispose();
  }

  void _showPaymentBottomSheet(Invoice invoice) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: _PaymentBottomSheetContent(invoice: invoice),
        );
      },
    ).then((_) {
      if (mounted) {
        context.read<InvoiceController>().fetchInvoices(isAdmin: false);
      }
    });
  }

  void _showInvoiceDetailsBottomSheet(Invoice invoice) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: _InvoiceDetailsBottomSheetContent(
            invoiceId: invoice.id,
            onPayTap: (detailedInvoice) => _showPaymentBottomSheet(detailedInvoice),
          ),
        );
      },
    ).then((_) {
      if (mounted) {
        context.read<InvoiceController>().fetchInvoices(isAdmin: false);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final invoiceController = context.watch<InvoiceController>();
    final invoices = invoiceController.invoices;

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F6F0),
        appBar: AppBar(
          title: const Text('Hóa đơn của tôi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Colors.white)),
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
                  Tab(text: 'Chưa thanh toán'),
                  Tab(text: 'Đã thanh toán'),
                ],
              ),
            ),
          ),
        ),
        body: Stack(
          children: [
            Positioned.fill(
              child: Opacity(
                opacity: 0.15,
                child: CustomPaint(painter: GridPainter()),
              ),
            ),
            invoiceController.isLoading
                ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
                : TabBarView(
                    children: [
                      _buildInvoiceList(invoices.where((inv) => inv.isUnpaid).toList()),
                      _buildInvoiceList(invoices.where((inv) => inv.isPaid).toList()),
                    ],
                  ),
          ],
        ),
      ),
    );
  }

  Widget _buildInvoiceList(List<Invoice> filteredInvoices) {
    if (filteredInvoices.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.receipt_long_outlined, color: Colors.grey.withOpacity(0.4), size: 64),
            const SizedBox(height: 12),
            const Text(
              'Không tìm thấy hóa đơn nào.',
              style: TextStyle(color: Colors.grey, fontWeight: FontWeight.w600, fontSize: 14),
            ),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: () => context.read<InvoiceController>().fetchInvoices(isAdmin: false),
      color: const Color(0xFF1C1917),
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        itemCount: filteredInvoices.length,
        itemBuilder: (context, index) {
          final invoice = filteredInvoices[index];
          return Container(
            margin: const EdgeInsets.only(bottom: 14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.04),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
              border: Border.all(color: const Color(0xFFE4E2D7).withOpacity(0.6)),
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: InkWell(
                onTap: () => _showInvoiceDetailsBottomSheet(invoice),
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Header: Room Code and Status
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(6),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFEAB308).withOpacity(0.12),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Icon(Icons.meeting_room_outlined, color: Color(0xFF1C1917), size: 16),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                'Phòng ${invoice.roomNumber}',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917)),
                              ),
                            ],
                          ),
                          _buildStatusBadge(invoice.status, invoice.statusLabel),
                        ],
                      ),
                      const SizedBox(height: 12),
                      const Divider(height: 1, color: Color(0xFFE4E2D7)),
                      const SizedBox(height: 12),
                      
                      // Month & Invoice Code
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                invoice.invoiceCode,
                                style: TextStyle(color: Colors.grey.shade400, fontSize: 10, fontWeight: FontWeight.w600, letterSpacing: 0.5),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'Kỳ thanh toán: Tháng ${invoice.billingMonth}/${invoice.billingYear}',
                                style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1C1917), fontSize: 13),
                              ),
                            ],
                          ),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                invoice.isUnpaid ? 'Còn lại cần đóng' : 'Tổng cộng',
                                style: TextStyle(color: Colors.grey.shade400, fontSize: 10, fontWeight: FontWeight.w500),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                '${(invoice.isUnpaid ? invoice.remainingAmount : invoice.totalAmount).toStringAsFixed(0)}đ',
                                style: TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 16,
                                  color: invoice.isUnpaid ? const Color(0xFF1C1917) : Colors.green.shade700,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      
                      // Bottom bar: due date and button/action
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Row(
                            children: [
                              Icon(Icons.calendar_today_outlined, size: 12, color: Colors.grey.shade400),
                              const SizedBox(width: 4),
                              Text(
                                'Hạn: ${invoice.dueDate}',
                                style: TextStyle(color: Colors.grey.shade500, fontSize: 11, fontWeight: FontWeight.w500),
                              ),
                            ],
                          ),
                          Row(
                            children: [
                              Text(
                                'Chi tiết',
                                style: TextStyle(color: Colors.grey.shade400, fontSize: 11, fontWeight: FontWeight.bold),
                              ),
                              Icon(Icons.chevron_right_rounded, size: 16, color: Colors.grey.shade400),
                            ],
                          ),
                        ],
                      ),

                      if (invoice.isUnpaid) ...[
                        const SizedBox(height: 12),
                        const Divider(height: 1, color: Color(0xFFE4E2D7)),
                        const SizedBox(height: 12),
                        ElevatedButton.icon(
                          onPressed: () => _showPaymentBottomSheet(invoice),
                          icon: const Icon(Icons.payment, size: 16, color: Color(0xFF1C1917)),
                          label: const Text('Thanh toán ngay', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFEAB308),
                            foregroundColor: const Color(0xFF1C1917),
                            elevation: 0,
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                        ),
                      ]
                    ],
                  ),
                ),
              ),
            ),
          );
        },
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
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withOpacity(0.3), width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 5,
            height: 5,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold),
          ),
        ],
      ),
    );
  }
}

class _PaymentBottomSheetContent extends StatefulWidget {
  final Invoice invoice;
  const _PaymentBottomSheetContent({required this.invoice});

  @override
  State<_PaymentBottomSheetContent> createState() => _PaymentBottomSheetContentState();
}

class _PaymentBottomSheetContentState extends State<_PaymentBottomSheetContent> {
  Invoice? _detailedInvoice;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadDetails();
  }

  Future<void> _loadDetails() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final detailed = await context.read<InvoiceController>().fetchInvoiceDetails(widget.invoice.id, isAdmin: false);
      if (mounted) {
        setState(() {
          _detailedInvoice = detailed;
          _isLoading = false;
          if (detailed == null) {
            _error = context.read<InvoiceController>().errorMessage ?? 'Không thể tải thông tin thanh toán.';
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _error = 'Lỗi kết nối: $e';
        });
      }
    }
  }

  void _copyToClipboard(String text, String label) {
    Clipboard.setData(ClipboardData(text: text));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Đã sao chép $label: $text'),
        duration: const Duration(seconds: 1),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final invoiceController = context.watch<InvoiceController>();
    final liveInvoice = invoiceController.invoices.firstWhere(
      (inv) => inv.id == widget.invoice.id,
      orElse: () => _detailedInvoice ?? widget.invoice,
    );

    if (liveInvoice.isPaid) {
      return Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.7,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: 20),
            const Center(
              child: Icon(
                Icons.check_circle_rounded,
                color: Colors.green,
                size: 72,
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'Thanh toán thành công!',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: Color(0xFF1C1917),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Hóa đơn ${liveInvoice.invoiceCode} đã được thanh toán.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 13,
                color: Colors.grey,
              ),
            ),
            const SizedBox(height: 24),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFF7F6F0),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE4E2D7)),
              ),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Số tiền thanh toán:', style: TextStyle(color: Colors.grey, fontSize: 13)),
                      Text(
                        '${liveInvoice.totalAmount.toStringAsFixed(0)}đ',
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917), fontSize: 13),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Kỳ thanh toán:', style: TextStyle(color: Colors.grey, fontSize: 13)),
                      Text(
                        'Tháng ${liveInvoice.billingMonth}/${liveInvoice.billingYear}',
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917), fontSize: 13),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () => Navigator.pop(context),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF1C1917),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                elevation: 0,
              ),
              child: const Text('ĐÓNG', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
            ),
            const SizedBox(height: 12),
          ],
        ),
      );
    }

    if (_isLoading) {
      return const SizedBox(
        height: 350,
        child: Center(
          child: CircularProgressIndicator(color: Color(0xFF1C1917)),
        ),
      );
    }

    if (_error != null || _detailedInvoice == null) {
      return SizedBox(
        height: 250,
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline_rounded, color: Colors.red, size: 40),
              const SizedBox(height: 12),
              Text(
                _error ?? 'Lỗi không xác định',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.red, fontWeight: FontWeight.bold, fontSize: 13),
              ),
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: _loadDetails,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1C1917),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                child: const Text('Thử lại', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
        ),
      );
    }

    final invoice = _detailedInvoice!;

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.9,
      ),
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle
          const SizedBox(height: 10),
          Center(
            child: Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          ),
          const SizedBox(height: 12),
          
          Flexible(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Thanh toán hóa đơn',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.close),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  
                  // Invoice summary card
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF7F6F0),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFE4E2D7)),
                    ),
                    child: Column(
                      children: [
                        _buildModalRow('Mã hóa đơn:', invoice.invoiceCode),
                        const SizedBox(height: 6),
                        _buildModalRow('Kỳ thanh toán:', 'Tháng ${invoice.billingMonth}/${invoice.billingYear}'),
                        const SizedBox(height: 6),
                        _buildModalRow('Số tiền cần đóng:', '${invoice.remainingAmount.toStringAsFixed(0)}đ'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // VietQR Code Dynamic Display
                  if (invoice.paymentQrUrl != null) ...[
                    const Text(
                      'Quét mã VietQR chuyển khoản nhanh',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1C1917)),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 10),
                    Center(
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          border: Border.all(color: const Color(0xFFE4E2D7).withOpacity(0.8)),
                          borderRadius: BorderRadius.circular(16),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.04),
                              blurRadius: 10,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Image.network(
                          invoice.paymentQrUrl!,
                          height: 160,
                          width: 160,
                          fit: BoxFit.contain,
                          loadingBuilder: (context, child, loadingProgress) {
                            if (loadingProgress == null) return child;
                            return const SizedBox(
                              height: 160,
                              width: 160,
                              child: Center(
                                child: CircularProgressIndicator(color: Color(0xFF1C1917)),
                              ),
                            );
                          },
                          errorBuilder: (context, error, stackTrace) => const SizedBox(
                            height: 160,
                            width: 160,
                            child: Center(
                              child: Icon(Icons.qr_code_2_rounded, size: 64, color: Colors.grey),
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF7F6F0),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFFE4E2D7)),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('NỘI DUNG', style: TextStyle(fontSize: 9, color: Colors.grey, fontWeight: FontWeight.bold)),
                                      const SizedBox(height: 2),
                                      Text(
                                        invoice.invoiceCode,
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                                      ),
                                    ],
                                  ),
                                ),
                                InkWell(
                                  onTap: () => _copyToClipboard(invoice.invoiceCode, 'nội dung'),
                                  child: Container(
                                    padding: const EdgeInsets.all(4),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFEAB308).withOpacity(0.12),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: const Icon(Icons.copy_rounded, size: 14, color: Color(0xFF1C1917)),
                                  ),
                                )
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF7F6F0),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFFE4E2D7)),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('SỐ TIỀN', style: TextStyle(fontSize: 9, color: Colors.grey, fontWeight: FontWeight.bold)),
                                      const SizedBox(height: 2),
                                      Text(
                                        '${invoice.remainingAmount.toStringAsFixed(0)}đ',
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                                      ),
                                    ],
                                  ),
                                ),
                                InkWell(
                                  onTap: () => _copyToClipboard(invoice.remainingAmount.toStringAsFixed(0), 'số tiền'),
                                  child: Container(
                                    padding: const EdgeInsets.all(4),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFEAB308).withOpacity(0.12),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: const Icon(Icons.copy_rounded, size: 14, color: Color(0xFF1C1917)),
                                  ),
                                )
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                  ],

                  const Divider(height: 32, color: Color(0xFFE4E2D7)),
                  
                  // Automated info notice
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEAB308).withOpacity(0.08),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFEAB308).withOpacity(0.3)),
                    ),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.bolt_rounded, color: Color(0xFFEAB308), size: 22),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Hệ thống tự động đối soát',
                                style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 13,
                                  color: Colors.yellow.shade900,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Sau khi chuyển khoản thành công với đúng nội dung và số tiền ở trên, hệ thống sẽ tự động cập nhật trạng thái hóa đơn của bạn trong vòng 1-2 phút. Bạn không cần tải lên ảnh biên lai hay làm gì thêm.',
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.grey.shade700,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Close / Done Button
                  ElevatedButton(
                    onPressed: () => Navigator.pop(context),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF1C1917),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      elevation: 0,
                    ),
                    child: const Text('ĐÃ HOÀN TẤT CHUYỂN KHOẢN', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                  ),
                  const SizedBox(height: 20),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildModalRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: Colors.grey, fontSize: 12)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917), fontSize: 12)),
      ],
    );
  }
}

class _InvoiceDetailsBottomSheetContent extends StatefulWidget {
  final int invoiceId;
  final Function(Invoice) onPayTap;
  const _InvoiceDetailsBottomSheetContent({required this.invoiceId, required this.onPayTap});

  @override
  State<_InvoiceDetailsBottomSheetContent> createState() => _InvoiceDetailsBottomSheetContentState();
}

class _InvoiceDetailsBottomSheetContentState extends State<_InvoiceDetailsBottomSheetContent> {
  Invoice? _detailedInvoice;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadDetails();
  }

  Future<void> _loadDetails() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final invoice = await context.read<InvoiceController>().fetchInvoiceDetails(widget.invoiceId, isAdmin: false);
      if (mounted) {
        setState(() {
          _detailedInvoice = invoice;
          _isLoading = false;
          if (invoice == null) {
            _error = context.read<InvoiceController>().errorMessage ?? 'Không thể tải thông tin chi tiết.';
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _error = 'Lỗi kết nối: $e';
        });
      }
    }
  }

  String _getItemName(InvoiceItem item) {
    switch (item.itemType) {
      case 1:
        return 'Tiền phòng';
      case 2:
        return 'Tiền điện';
      case 3:
        return 'Tiền nước';
      case 4:
        return 'Tiền mạng / Internet';
      case 5:
        return 'Phí thu gom rác';
      case 6:
        return 'Phí gửi xe';
      case 7:
        return 'Phụ phí';
      case 8:
        return 'Giảm giá / Khuyến mãi';
      case 9:
        return 'Nợ cũ kỳ trước';
      case 10:
        return 'Điều chỉnh tăng';
      case 11:
        return 'Điều chỉnh giảm';
      default:
        return 'Khoản thu khác';
    }
  }

  IconData _getItemIcon(InvoiceItem item) {
    switch (item.itemType) {
      case 1:
        return Icons.home_outlined;
      case 2:
        return Icons.flash_on_outlined;
      case 3:
        return Icons.water_drop_outlined;
      case 4:
        return Icons.wifi_rounded;
      case 5:
        return Icons.delete_outline_rounded;
      case 6:
        return Icons.motorcycle_rounded;
      case 7:
        return Icons.add_circle_outline_rounded;
      case 8:
        return Icons.local_offer_outlined;
      case 9:
        return Icons.history_rounded;
      case 10:
        return Icons.trending_up_rounded;
      case 11:
        return Icons.trending_down_rounded;
      default:
        return Icons.receipt_long_outlined;
    }
  }

  Color _getItemIconColor(InvoiceItem item) {
    switch (item.itemType) {
      case 1:
        return const Color(0xFF1C1917);
      case 2:
        return Colors.orange;
      case 3:
        return Colors.blue;
      case 4:
        return Colors.teal;
      case 5:
        return Colors.brown;
      case 6:
        return Colors.indigo;
      case 7:
        return Colors.amber.shade800;
      case 8:
        return Colors.green;
      case 9:
        return Colors.red;
      case 10:
        return Colors.deepOrange;
      case 11:
        return Colors.lightGreen;
      default:
        return Colors.purple;
    }
  }

  Widget _buildStatusBadge(int status, String label) {
    Color color = Colors.grey;
    if (status == 4) color = Colors.green; // Paid
    if (status == 2 || status == 3) color = const Color(0xFFEAB308); // Unpaid, partially paid
    if (status == 5) color = Colors.redAccent; // Overdue

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withOpacity(0.3), width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 5,
            height: 5,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const SizedBox(
        height: 300,
        child: Center(
          child: CircularProgressIndicator(color: Color(0xFF1C1917)),
        ),
      );
    }

    if (_error != null || _detailedInvoice == null) {
      return SizedBox(
        height: 250,
        child: Padding(
          padding: const EdgeInsets.all(24.0),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline_rounded, color: Colors.red, size: 40),
              const SizedBox(height: 12),
              Text(
                _error ?? 'Lỗi không xác định',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.red, fontWeight: FontWeight.bold, fontSize: 13),
              ),
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: _loadDetails,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1C1917),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                child: const Text('Thử lại', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
        ),
      );
    }

    final invoice = _detailedInvoice!;
    final items = invoice.items ?? [];

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.88,
      ),
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle
          const SizedBox(height: 10),
          Center(
            child: Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          ),
          const SizedBox(height: 12),
          
          Flexible(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Header
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Chi tiết hóa đơn',
                            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            invoice.invoiceCode,
                            style: TextStyle(color: Colors.grey.shade400, fontSize: 11, fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(context),
                        icon: const Icon(Icons.close),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),

                  // Room Info and Status
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF7F6F0),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFE4E2D7)),
                    ),
                    child: Column(
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Row(
                              children: [
                                const Icon(Icons.meeting_room_outlined, color: Color(0xFF1C1917), size: 18),
                                const SizedBox(width: 6),
                                Text(
                                  'Phòng ${invoice.roomNumber}',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917)),
                                ),
                              ],
                            ),
                            _buildStatusBadge(invoice.status, invoice.statusLabel),
                          ],
                        ),
                        const Divider(height: 18, color: Color(0xFFE4E2D7)),
                        _buildDetailRow('Thời gian:', '${invoice.periodStart} ~ ${invoice.periodEnd}'),
                        const SizedBox(height: 4),
                        _buildDetailRow('Hạn đóng:', invoice.dueDate),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Title Fee list
                  const Text(
                    'Chi tiết các khoản phí',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1C1917)),
                  ),
                  const SizedBox(height: 10),

                  // Fee List items
                  if (items.isEmpty)
                    Container(
                      padding: const EdgeInsets.symmetric(vertical: 20),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        border: Border.all(color: const Color(0xFFE4E2D7)),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Center(
                        child: Text('Không có chi tiết khoản phí nào.', style: TextStyle(color: Colors.grey, fontSize: 12)),
                      ),
                    )
                  else
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: items.length,
                      separatorBuilder: (context, index) => const SizedBox(height: 8),
                      itemBuilder: (context, index) {
                        final item = items[index];
                        return Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF7F6F0).withOpacity(0.5),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFFE4E2D7).withOpacity(0.5)),
                          ),
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(8),
                                decoration: BoxDecoration(
                                  color: _getItemIconColor(item).withOpacity(0.1),
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(_getItemIcon(item), color: _getItemIconColor(item), size: 18),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      _getItemName(item),
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1C1917)),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      item.description.isNotEmpty ? item.description : 'Đơn giá: ${item.unitPrice.toStringAsFixed(0)}đ x ${item.quantity.toStringAsFixed(0)}',
                                      style: TextStyle(color: Colors.grey.shade500, fontSize: 10, fontWeight: FontWeight.w500),
                                    ),
                                  ],
                                ),
                              ),
                              Text(
                                '${item.amount >= 0 ? '+' : ''}${item.amount.toStringAsFixed(0)}đ',
                                style: TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 13,
                                  color: item.amount >= 0 ? const Color(0xFF1C1917) : Colors.green.shade600,
                                ),
                              ),
                            ],
                          ),
                        );
                      },
                    ),

                  const SizedBox(height: 20),

                  // Summary Card (Receipt-style)
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1C1917),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Column(
                      children: [
                        _buildSummaryRow('Nợ cũ kỳ trước:', '${invoice.previousDebtAmount.toStringAsFixed(0)}đ', color: Colors.white70),
                        const SizedBox(height: 6),
                        _buildSummaryRow('Phí phát sinh kỳ này:', '${(invoice.totalAmount - invoice.previousDebtAmount).toStringAsFixed(0)}đ', color: Colors.white70),
                        const SizedBox(height: 6),
                        const Divider(height: 12, color: Colors.white24),
                        const SizedBox(height: 6),
                        _buildSummaryRow('Tổng cộng hóa đơn:', '${invoice.totalAmount.toStringAsFixed(0)}đ', isBold: true, color: Colors.white),
                        const SizedBox(height: 6),
                        _buildSummaryRow('Đã thanh toán:', '${invoice.paidAmount.toStringAsFixed(0)}đ', color: const Color(0xFFEAB308)),
                        const SizedBox(height: 6),
                        const Divider(height: 12, color: Colors.white24),
                        const SizedBox(height: 6),
                        _buildSummaryRow('Còn lại cần thanh toán:', '${invoice.remainingAmount.toStringAsFixed(0)}đ', isBold: true, color: Colors.redAccent, isLarge: true),
                      ],
                    ),
                  ),
                  
                  const SizedBox(height: 24),

                  // Pay button if invoice is unpaid
                  if (invoice.isUnpaid)
                    ElevatedButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                        widget.onPayTap(invoice);
                      },
                      icon: const Icon(Icons.payment, size: 18, color: Color(0xFF1C1917)),
                      label: const Text('TIẾN HÀNH THANH TOÁN', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFEAB308),
                        foregroundColor: const Color(0xFF1C1917),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        elevation: 0,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),
                  const SizedBox(height: 16),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: Colors.grey, fontSize: 12)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF1C1917), fontSize: 12)),
      ],
    );
  }

  Widget _buildSummaryRow(String label, String value, {bool isBold = false, Color? color, bool isLarge = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            color: color ?? Colors.white70,
            fontWeight: isBold ? FontWeight.bold : FontWeight.w500,
            fontSize: isLarge ? 14 : (isBold ? 13 : 12),
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontWeight: isBold ? FontWeight.w900 : FontWeight.w700,
            color: color ?? Colors.white,
            fontSize: isLarge ? 15 : (isBold ? 13 : 12),
          ),
        ),
      ],
    );
  }
}
