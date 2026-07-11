import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../config/currency_formatter.dart';
import '../../controllers/invoice_controller.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/facility_controller.dart';
import '../../models/invoice.dart';
import '../../services/websocket_service.dart';
import '../auth/login_screen.dart'; // import GridPainter

class InvoicesScreen extends StatefulWidget {
  const InvoicesScreen({super.key});

  @override
  State<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends State<InvoicesScreen> {
  int _selectedFilter = 0; // 0: All, 1: Unpaid, 2: Paid, 3: Overdue
  StreamSubscription? _socketSub;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<InvoiceController>().fetchInvoices(isAdmin: true);
      _socketSub = context.read<WebSocketService>().notificationsStream.listen((event) {
        final type = event['type'];
        if (type == 'admin_invoice_paid' || type == 'admin_invoice_reissued') {
          final data = event['data'];
          if (data is Map<String, dynamic>) {
            // Check building access
            final authCtrl = context.read<AuthController>();
            final admin = authCtrl.currentAdmin;
            if (admin != null) {
              final isSuperAdmin = admin.role == 2;
              final buildingId = data['building_id'];
              if (!isSuperAdmin && buildingId != null) {
                final facilityCtrl = context.read<FacilityController>();
                final isManaged = facilityCtrl.buildings.any((b) => b.id == buildingId);
                if (!isManaged) return; // Not managing this building, ignore the event
              }
            }

            context.read<InvoiceController>().updateInvoiceRealtime(data);
          }
          context.read<InvoiceController>().fetchInvoices(isAdmin: true);
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(type == 'admin_invoice_reissued' ? 'Hóa đơn đã được phát hành lại realtime.' : 'Hóa đơn đã được thanh toán realtime.'),
                backgroundColor: type == 'admin_invoice_reissued' ? const Color(0xFF0F766E) : Colors.green,
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

  Future<void> _showReissueSheet(Invoice invoice) async {
    final controller = context.read<InvoiceController>();
    final detailedInvoice = await controller.fetchInvoiceDetails(invoice.id, isAdmin: true);
    if (!mounted) return;

    if (detailedInvoice == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(controller.errorMessage ?? 'Không thể tải chi tiết hóa đơn.'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _ReissueInvoiceSheet(invoice: detailedInvoice),
    );
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
                                            formatMoney(invoice.totalAmount),
                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF1C1917)),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 16),

                                      // Actions strip
                                      if (invoice.isUnpaid || invoice.isOverdue)
                                        Wrap(
                                          spacing: 10,
                                          runSpacing: 10,
                                          children: [
                                            if (invoice.status == 2 || invoice.status == 5)
                                              SizedBox(
                                                width: MediaQuery.of(context).size.width > 420 ? 160 : MediaQuery.of(context).size.width - 64,
                                                child: OutlinedButton.icon(
                                                  onPressed: () => _showReissueSheet(invoice),
                                                  icon: const Icon(Icons.edit_note_rounded, size: 18),
                                                  label: const Text('Sửa & gửi QR mới'),
                                                  style: OutlinedButton.styleFrom(
                                                    foregroundColor: const Color(0xFF0F766E),
                                                    side: const BorderSide(color: Color(0xFF0F766E)),
                                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                                  ),
                                                ),
                                              ),
                                            // Debt Reminder Button (mock push)
                                            SizedBox(
                                              width: MediaQuery.of(context).size.width > 420 ? 140 : MediaQuery.of(context).size.width - 64,
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

                                            // Confirm Payment Button
                                            SizedBox(
                                              width: MediaQuery.of(context).size.width > 420 ? 140 : MediaQuery.of(context).size.width - 64,
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
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        invoice.statusLabel,
        style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.bold),
      ),
    );
  }
}

const int _itemTypeSurcharge = 7;
const int _itemTypeDiscount = 8;
const int _itemTypeAdjustIncrease = 10;
const int _itemTypeAdjustDecrease = 11;
const Set<int> _adjustmentItemTypes = {
  _itemTypeSurcharge,
  _itemTypeDiscount,
  _itemTypeAdjustIncrease,
  _itemTypeAdjustDecrease,
};

class _AdjustmentDraft {
  final String key;
  int itemType;
  final TextEditingController descriptionController;
  final TextEditingController quantityController;
  final TextEditingController unitPriceController;

  _AdjustmentDraft({
    required this.key,
    required this.itemType,
    required String description,
    required String quantity,
    required String unitPrice,
  })  : descriptionController = TextEditingController(text: description),
        quantityController = TextEditingController(text: quantity),
        unitPriceController = TextEditingController(text: unitPrice);

  factory _AdjustmentDraft.fromItem(InvoiceItem item) {
    return _AdjustmentDraft(
      key: 'existing-${item.id}',
      itemType: item.itemType,
      description: item.description,
      quantity: item.quantity.toStringAsFixed(2),
      unitPrice: formatMoney(item.unitPrice.abs()).replaceAll('đ', '').trim(),
    );
  }

  factory _AdjustmentDraft.empty() {
    return _AdjustmentDraft(
      key: 'new-${DateTime.now().microsecondsSinceEpoch}',
      itemType: _itemTypeSurcharge,
      description: '',
      quantity: '1',
      unitPrice: '0',
    );
  }

  Map<String, dynamic> toPayload() {
    final quantityText = quantityController.text.trim();
    final unitPriceText = unitPriceController.text.trim().replaceAll('.', '');
    final quantityValue = double.tryParse(quantityText);
    final unitPriceValue = double.tryParse(unitPriceText);

    return {
      'item_type': itemType,
      'description': descriptionController.text.trim().isEmpty ? 'Điều chỉnh hóa đơn' : descriptionController.text.trim(),
      'quantity': quantityValue == null ? (quantityText.isEmpty ? '1' : quantityText) : quantityValue.abs().toStringAsFixed(2),
      'unit_price': unitPriceValue == null ? (unitPriceText.isEmpty ? '0' : unitPriceText) : unitPriceValue.abs().toStringAsFixed(2),
    };
  }

  double signedAmount() {
    final quantity = double.tryParse(quantityController.text.trim())?.abs() ?? 1;
    final unitPrice = parseMoney(unitPriceController.text).abs();
    final amount = quantity * unitPrice;
    return itemType == _itemTypeDiscount || itemType == _itemTypeAdjustDecrease ? -amount : amount;
  }

  void dispose() {
    descriptionController.dispose();
    quantityController.dispose();
    unitPriceController.dispose();
  }
}

class _ReissueInvoiceSheet extends StatefulWidget {
  final Invoice invoice;

  const _ReissueInvoiceSheet({required this.invoice});

  @override
  State<_ReissueInvoiceSheet> createState() => _ReissueInvoiceSheetState();
}

class _ReissueInvoiceSheetState extends State<_ReissueInvoiceSheet> {
  late final TextEditingController _reasonController;
  late final TextEditingController _dueDateController;
  late final Map<int, TextEditingController> _readingControllers;
  late final List<_AdjustmentDraft> _adjustments;

  List<InvoiceItem> get _meterItems => (widget.invoice.items ?? [])
      .where((item) => item.meterReadingId != null && item.meterReading != null)
      .toList();

  @override
  void initState() {
    super.initState();
    _reasonController = TextEditingController();
    _dueDateController = TextEditingController(text: widget.invoice.dueDate);
    _readingControllers = {
      for (final item in _meterItems)
        item.meterReadingId!: TextEditingController(text: item.meterReading!.currentReading.toStringAsFixed(2)),
    };
    _adjustments = (widget.invoice.items ?? [])
        .where((item) => item.serviceId == null && item.meterReadingId == null && _adjustmentItemTypes.contains(item.itemType))
        .map(_AdjustmentDraft.fromItem)
        .toList();
  }

  @override
  void dispose() {
    _reasonController.dispose();
    _dueDateController.dispose();
    for (final controller in _readingControllers.values) {
      controller.dispose();
    }
    for (final adjustment in _adjustments) {
      adjustment.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    final reason = _reasonController.text.trim();
    if (reason.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng nhập lý do phát hành lại.'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    final meterPayload = _meterItems.map((item) {
      return {
        'meter_reading_id': item.meterReadingId,
        'current_reading': _readingControllers[item.meterReadingId]!.text.trim(),
        if (item.meterReading?.readingDate != null) 'reading_date': item.meterReading!.readingDate,
      };
    }).toList();

    final controller = context.read<InvoiceController>();
    final success = await controller.reissueInvoice(
      invoiceId: widget.invoice.id,
      reason: reason,
      dueDate: _dueDateController.text.trim().isEmpty ? null : _dueDateController.text.trim(),
      meterReadings: meterPayload,
      adjustments: _adjustments.map((adjustment) => adjustment.toPayload()).toList(),
    );

    if (!mounted) return;
    if (success) {
      Navigator.pop(context);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã phát hành lại hóa đơn và gửi QR mới.'), backgroundColor: Color(0xFF0F766E)),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(controller.errorMessage ?? 'Không thể phát hành lại hóa đơn.'), backgroundColor: Colors.redAccent),
      );
    }
  }

  void _addAdjustment() {
    setState(() {
      _adjustments.add(_AdjustmentDraft.empty());
    });
  }

  void _removeAdjustment(_AdjustmentDraft adjustment) {
    setState(() {
      _adjustments.remove(adjustment);
    });
    adjustment.dispose();
  }

  String _adjustmentTypeLabel(int itemType) {
    switch (itemType) {
      case _itemTypeDiscount:
        return 'Giảm trừ';
      case _itemTypeAdjustIncrease:
        return 'Điều chỉnh tăng';
      case _itemTypeAdjustDecrease:
        return 'Điều chỉnh giảm';
      case _itemTypeSurcharge:
      default:
        return 'Phụ thu';
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    final adjustmentTotal = _adjustments.fold<double>(0, (total, adjustment) => total + adjustment.signedAmount());

    return Container(
      constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.9),
      padding: EdgeInsets.only(left: 20, right: 20, top: 16, bottom: bottomInset + 20),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Center(
              child: Container(
                width: 36,
                height: 4,
                decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(10)),
              ),
            ),
            const SizedBox(height: 16),
            Text('Sửa & phát hành lại ${widget.invoice.invoiceCode}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF1C1917))),
            const SizedBox(height: 6),
            const Text('Chỉnh chỉ số ở đây sẽ cập nhật bảng chỉ số, dòng hóa đơn, tổng tiền và gửi QR mới cho khách thuê.', style: TextStyle(fontSize: 12, color: Color(0xFF78716C), height: 1.4)),
            const SizedBox(height: 16),
            TextField(
              controller: _dueDateController,
              decoration: _inputDecoration('Hạn thanh toán', 'YYYY-MM-DD'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _reasonController,
              maxLines: 3,
              decoration: _inputDecoration('Lý do phát hành lại', 'Ví dụ: Nhập sai chỉ số điện'),
            ),
            const SizedBox(height: 16),
            const Text('Chỉ số điện/nước', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917))),
            const SizedBox(height: 8),
            if (_meterItems.isEmpty)
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: const Color(0xFFF7F6F0), borderRadius: BorderRadius.circular(12)),
                child: const Text('Hóa đơn này không có dòng chỉ số điện/nước.', style: TextStyle(fontSize: 12, color: Color(0xFF78716C))),
              )
            else
              ..._meterItems.map((item) {
                final reading = item.meterReading!;
                return Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF7F6F0),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFE4E2D7)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.description, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1C1917))),
                      const SizedBox(height: 6),
                      Text('Chỉ số trước: ${reading.previousReading.toStringAsFixed(2)}', style: const TextStyle(fontSize: 12, color: Color(0xFF78716C))),
                      const SizedBox(height: 8),
                      TextField(
                        controller: _readingControllers[item.meterReadingId],
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: _inputDecoration('Chỉ số mới', 'Nhập chỉ số mới'),
                      ),
                    ],
                  ),
                );
              }),
            const SizedBox(height: 16),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Phụ thu / giảm trừ', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917))),
                TextButton.icon(
                  onPressed: _addAdjustment,
                  icon: const Icon(Icons.add_circle_outline_rounded, size: 18),
                  label: const Text('Thêm dòng'),
                  style: TextButton.styleFrom(foregroundColor: const Color(0xFF0F766E), textStyle: const TextStyle(fontWeight: FontWeight.bold)),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (_adjustments.isEmpty)
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: const Color(0xFFF7F6F0), borderRadius: BorderRadius.circular(12)),
                child: const Text('Chưa có dòng phụ thu hoặc giảm trừ.', style: TextStyle(fontSize: 12, color: Color(0xFF78716C))),
              )
            else
              ..._adjustments.map((adjustment) {
                return Container(
                  key: ValueKey(adjustment.key),
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF7F6F0),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFE4E2D7)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: DropdownButtonFormField<int>(
                              value: adjustment.itemType,
                              decoration: _inputDecoration('Loại điều chỉnh', 'Chọn loại'),
                              items: _adjustmentItemTypes
                                  .map((itemType) => DropdownMenuItem<int>(value: itemType, child: Text(_adjustmentTypeLabel(itemType))))
                                  .toList(),
                              onChanged: (value) {
                                if (value == null) return;
                                setState(() {
                                  adjustment.itemType = value;
                                });
                              },
                            ),
                          ),
                          const SizedBox(width: 8),
                          IconButton(
                            onPressed: () => _removeAdjustment(adjustment),
                            icon: const Icon(Icons.delete_outline_rounded, color: Colors.redAccent),
                            tooltip: 'Xóa dòng',
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      TextField(
                        controller: adjustment.descriptionController,
                        onChanged: (_) => setState(() {}),
                        decoration: _inputDecoration('Mô tả', 'Ví dụ: Giảm trừ do nhập sai phí'),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Expanded(
                            child: TextField(
                              controller: adjustment.quantityController,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                              decoration: _inputDecoration('Số lượng', '1'),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: TextField(
                              controller: adjustment.unitPriceController,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              inputFormatters: [CurrencyInputFormatter()],
                              onChanged: (_) => setState(() {}),
                              decoration: _inputDecoration('Đơn giá', '0'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              }),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFF0F766E).withOpacity(0.08), borderRadius: BorderRadius.circular(14)),
              child: Text(
                'Tổng điều chỉnh: ${adjustmentTotal >= 0 ? '+' : '-'}${formatMoney(adjustmentTotal.abs())}',
                style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF0F766E)),
              ),
            ),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: context.watch<InvoiceController>().isLoading ? null : _submit,
              icon: const Icon(Icons.qr_code_2_rounded, color: Colors.white),
              label: const Text('CẬP NHẬT & GỬI QR MỚI', style: TextStyle(fontWeight: FontWeight.bold)),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0F766E),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(String label, String hint) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      filled: true,
      fillColor: const Color(0xFFF7F6F0),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE4E2D7))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE4E2D7))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF0F766E))),
    );
  }
}
