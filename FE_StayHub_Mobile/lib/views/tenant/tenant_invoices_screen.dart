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
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) {
        return _PaymentBottomSheetContent(invoice: invoice);
      },
    );
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
          title: const Text('Hóa đơn của tôi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          backgroundColor: const Color(0xFF1C1917),
          bottom: const TabBar(
            indicatorColor: Color(0xFFEAB308),
            labelColor: Color(0xFFEAB308),
            unselectedLabelColor: Colors.grey,
            tabs: [
              Tab(text: 'Chưa thanh toán'),
              Tab(text: 'Đã thanh toán'),
            ],
          ),
        ),
        body: Stack(
          children: [
            Positioned.fill(child: CustomPaint(painter: GridPainter())),
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
      return const Center(child: Text('Không tìm thấy hóa đơn nào.', style: TextStyle(color: Colors.grey)));
    }
    return RefreshIndicator(
      onRefresh: () => context.read<InvoiceController>().fetchInvoices(isAdmin: false),
      color: const Color(0xFF1C1917),
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: filteredInvoices.length,
        itemBuilder: (context, index) {
          final invoice = filteredInvoices[index];
          return Card(
            color: Colors.white,
            margin: const EdgeInsets.only(bottom: 16),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: const BorderSide(color: Color(0xFFE4E2D7)),
            ),
            elevation: 0,
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: invoice.isUnpaid ? () => _showPaymentBottomSheet(invoice) : null,
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
            ),
          );
        },
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
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold),
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
  final _picker = ImagePicker();
  XFile? _selectedImage;
  
  final _amountController = TextEditingController();
  final _refController = TextEditingController();
  final _noteController = TextEditingController();
  
  bool _isUploading = false;
  String? _uploadError;

  @override
  void initState() {
    super.initState();
    _amountController.text = widget.invoice.remainingAmount.toStringAsFixed(0);
  }

  @override
  void dispose() {
    _amountController.dispose();
    _refController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    try {
      final img = await _picker.pickImage(source: ImageSource.gallery, imageQuality: 85);
      if (img != null) {
        setState(() {
          _selectedImage = img;
        });
      }
    } catch (e) {
      setState(() {
        _uploadError = 'Lỗi chọn ảnh: $e';
      });
    }
  }

  Future<void> _submitProof() async {
    if (_selectedImage == null) {
      setState(() {
        _uploadError = 'Vui lòng chọn ảnh minh chứng giao dịch.';
      });
      return;
    }

    final amt = double.tryParse(_amountController.text);
    if (amt == null || amt <= 0) {
      setState(() {
        _uploadError = 'Vui lòng nhập số tiền hợp lệ.';
      });
      return;
    }

    setState(() {
      _isUploading = true;
      _uploadError = null;
    });

    final success = await context.read<InvoiceController>().uploadPaymentProof(
          invoiceId: widget.invoice.id,
          amount: amt,
          transactionReference: _refController.text.trim(),
          note: _noteController.text.trim(),
          imagePath: _selectedImage!.path,
        );

    if (mounted) {
      setState(() {
        _isUploading = false;
      });

      if (success) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Đã gửi minh chứng thanh toán hóa đơn ${widget.invoice.invoiceCode} thành công!'),
            backgroundColor: Colors.green,
          ),
        );
      } else {
        setState(() {
          _uploadError = context.read<InvoiceController>().errorMessage ?? 'Không thể gửi minh chứng.';
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
    final invoice = invoiceController.invoices.firstWhere(
      (inv) => inv.id == widget.invoice.id,
      orElse: () => widget.invoice,
    );
    
    return DraggableScrollableSheet(
      initialChildSize: invoice.isPaid ? 0.6 : 0.85,
      maxChildSize: 0.95,
      minChildSize: 0.5,
      expand: false,
      builder: (context, scrollController) {
        if (invoice.isPaid) {
          return SingleChildScrollView(
            controller: scrollController,
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 32),
                const Center(
                  child: Icon(
                    Icons.check_circle,
                    color: Colors.green,
                    size: 80,
                  ),
                ),
                const SizedBox(height: 24),
                const Text(
                  'Thanh toán thành công!',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF1C1917),
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Hóa đơn ${invoice.invoiceCode} đã được thanh toán.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 14,
                    color: Colors.grey,
                  ),
                ),
                const SizedBox(height: 32),
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
                          Text('${invoice.totalAmount.toStringAsFixed(0)}đ', style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917), fontSize: 13)),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Kỳ thanh toán:', style: TextStyle(color: Colors.grey, fontSize: 13)),
                          Text('Tháng ${invoice.billingMonth}/${invoice.billingYear}', style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917), fontSize: 13)),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 32),
                ElevatedButton(
                  onPressed: () => Navigator.pop(context),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF1C1917),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('ĐÓNG', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
                const SizedBox(height: 24),
              ],
            ),
          );
        }

        return SingleChildScrollView(
          controller: scrollController,
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom + 24,
            top: 24,
            left: 24,
            right: 24,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Thanh toán Hóa đơn',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              
              // Invoice summary card
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

              // VietQR Code Dynamic Display
              if (invoice.paymentQrUrl != null) ...[
                const Text(
                  'Quét mã VietQR chuyển khoản nhanh',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917)),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Center(
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      border: Border.all(color: const Color(0xFFE4E2D7)),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.05),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Image.network(
                      invoice.paymentQrUrl!,
                      height: 180,
                      width: 180,
                      fit: BoxFit.contain,
                      loadingBuilder: (context, child, loadingProgress) {
                        if (loadingProgress == null) return child;
                        return const SizedBox(
                          height: 180,
                          width: 180,
                          child: Center(
                            child: CircularProgressIndicator(color: Color(0xFF1C1917)),
                          ),
                        );
                      },
                      errorBuilder: (context, error, stackTrace) => const SizedBox(
                        height: 180,
                        width: 180,
                        child: Center(
                          child: Icon(Icons.qr_code, size: 64, color: Colors.grey),
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
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF7F6F0),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFE4E2D7)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Nội dung chuyển khoản', style: TextStyle(fontSize: 10, color: Colors.grey)),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  invoice.invoiceCode,
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                ),
                                IconButton(
                                  constraints: const BoxConstraints(),
                                  padding: EdgeInsets.zero,
                                  icon: const Icon(Icons.copy, size: 16, color: Color(0xFFEAB308)),
                                  onPressed: () => _copyToClipboard(invoice.invoiceCode, 'nội dung'),
                                )
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF7F6F0),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFE4E2D7)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Số tiền chuyển khoản', style: TextStyle(fontSize: 10, color: Colors.grey)),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  '${invoice.remainingAmount.toStringAsFixed(0)}đ',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                ),
                                IconButton(
                                  constraints: const BoxConstraints(),
                                  padding: EdgeInsets.zero,
                                  icon: const Icon(Icons.copy, size: 16, color: Color(0xFFEAB308)),
                                  onPressed: () => _copyToClipboard(invoice.remainingAmount.toStringAsFixed(0), 'số tiền'),
                                )
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
              ],

              // Upload proof section
              const Text(
                'Hoặc gửi minh chứng thanh toán thủ công',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917)),
              ),
              const SizedBox(height: 12),
              
              if (_uploadError != null) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    border: Border.all(color: Colors.red.shade200),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    _uploadError!,
                    style: TextStyle(color: Colors.red.shade800, fontSize: 12, fontWeight: FontWeight.bold),
                  ),
                ),
                const SizedBox(height: 12),
              ],

              // Input Amount
              TextField(
                controller: _amountController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Số tiền đã chuyển (đ) *',
                  border: OutlineInputBorder(),
                  contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
              ),
              const SizedBox(height: 12),

              // Input Transaction Ref
              TextField(
                controller: _refController,
                decoration: const InputDecoration(
                  labelText: 'Mã tham chiếu giao dịch (Không bắt buộc)',
                  border: OutlineInputBorder(),
                  contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
              ),
              const SizedBox(height: 12),

              // Image Picker Selector
              InkWell(
                onTap: _pickImage,
                child: Container(
                  height: 100,
                  decoration: BoxDecoration(
                    color: const Color(0xFFF7F6F0),
                    border: Border.all(color: const Color(0xFFE4E2D7)),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: _selectedImage != null
                      ? Stack(
                          fit: StackFit.expand,
                          children: [
                            const Center(
                              child: Icon(Icons.check_circle, color: Colors.green, size: 36),
                            ),
                            Positioned(
                              bottom: 8,
                              left: 8,
                              right: 8,
                              child: Text(
                                _selectedImage!.name,
                                textAlign: TextAlign.center,
                                style: const TextStyle(fontSize: 11, overflow: TextOverflow.ellipsis),
                              ),
                            )
                          ],
                        )
                      : Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: const [
                            Icon(Icons.camera_alt, color: Colors.grey),
                            SizedBox(height: 4),
                            Text('Tải lên ảnh biên lai chuyển khoản *', style: TextStyle(fontSize: 12, color: Colors.grey)),
                          ],
                        ),
                ),
              ),
              const SizedBox(height: 12),

              // Input Note
              TextField(
                controller: _noteController,
                decoration: const InputDecoration(
                  labelText: 'Ghi chú thêm',
                  border: OutlineInputBorder(),
                  contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
              ),
              const SizedBox(height: 24),

              // Submit Button
              ElevatedButton(
                onPressed: _isUploading ? null : _submitProof,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1C1917),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: _isUploading
                    ? const Center(child: CircularProgressIndicator(color: Colors.white))
                    : const Text('XÁC NHẬN GỬI MINH CHỨNG', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
              const SizedBox(height: 24),
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
}
