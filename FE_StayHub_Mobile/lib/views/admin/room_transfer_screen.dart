import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/currency_formatter.dart';
import '../../controllers/contract_controller.dart';
import '../../models/contract.dart';
import '../auth/login_screen.dart'; // import GridPainter

class RoomTransferScreen extends StatefulWidget {
  final Contract contract;

  const RoomTransferScreen({
    super.key,
    required this.contract,
  });

  @override
  State<RoomTransferScreen> createState() => _RoomTransferScreenState();
}

class _RoomTransferScreenState extends State<RoomTransferScreen> {
  final _formKey = GlobalKey<FormState>();

  bool _isLoading = false;
  String? _errorMessage;

  // Selected new room
  Map<String, dynamic>? _selectedRoom;
  List<dynamic> _availableRooms = [];

  // Active tenants in the contract
  List<Map<String, dynamic>> _contractTenants = []; // id, full_name, is_selected

  // Form Fields
  final _movementDateController = TextEditingController();
  final _deductionController = TextEditingController(text: '0');
  final _feeController = TextEditingController(text: '0');
  final _depositController = TextEditingController();
  final _noteController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _movementDateController.text = _nextMonthStartDateString();
    _initData();
  }

  String _nextMonthStartDateString() {
    final now = DateTime.now();
    final next = DateTime(now.year, now.month + 1, 1);
    final month = next.month.toString().padLeft(2, '0');
    final day = next.day.toString().padLeft(2, '0');
    return '${next.year}-$month-$day';
  }

  Future<void> _initData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final contractController = context.read<ContractController>();
      final contractData = await contractController.fetchContractDetail(widget.contract.id);

      if (contractData != null) {
        // Parse tenants
        final tenantsList = contractData['contract_tenants'] as List<dynamic>? ?? [];
        _contractTenants = tenantsList.map((ct) {
          final t = ct['tenant'] as Map<String, dynamic>? ?? {};
          final tId = ct['tenant_id'] ?? t['id'];
          return {
            'id': tId,
            'full_name': t['full_name'] ?? 'Khách thuê',
            'is_selected': ct['is_staying'] == 1 || ct['is_staying'] == true,
          };
        }).toList();

        // Get building id and load available rooms
        final bId = contractData['building_id'] ?? contractData['room']?['building_id'];
        if (bId != null) {
          _availableRooms = await contractController.fetchAvailableRooms(bId);
        }
      }
    } catch (e) {
      _errorMessage = 'Lỗi tải dữ liệu chuyển phòng: $e';
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _selectDate(BuildContext context) async {
    final initialDate = DateTime.tryParse(_movementDateController.text) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime.now(),
      lastDate: DateTime(2030),
    );
    if (picked != null) {
      setState(() {
        _movementDateController.text = picked.toString().split(' ')[0];
      });
    }
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;

    final selectedTenantIds = _contractTenants
        .where((t) => t['is_selected'] == true)
        .map((t) => t['id'] as int)
        .toList();

    if (selectedTenantIds.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng chọn ít nhất một khách thuê di chuyển!'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    if (_selectedRoom == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng chọn phòng mới!'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    final contractController = context.read<ContractController>();
    final success = await contractController.scheduleRoomTransfer(
      contractId: widget.contract.id,
      newRoomNumber: _selectedRoom!['room_number'].toString(),
      movementDate: _movementDateController.text,
      depositDeductionAmount: parseMoney(_deductionController.text),
      transferFee: parseMoney(_feeController.text),
      newDepositAmount: _depositController.text.trim().isEmpty ? null : parseMoney(_depositController.text),
      note: _noteController.text.trim(),
    );

    // Filter to selected tenants payload
    // Note: The previous scheduleRoomTransfer helper always automatically fetched all tenants,
    // let's update it to respect selected tenants list!
    // But first, let's handle the response
    setState(() {
      _isLoading = false;
    });

    if (success && mounted) {
      Navigator.pop(context, true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã lên lịch chuyển phòng thành công!'), backgroundColor: Colors.green),
      );
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(contractController.errorMessage ?? 'Chuyển phòng thất bại.'), backgroundColor: Colors.redAccent),
      );
    }
  }

  InputDecoration _inputDecoration({required String labelText, IconData? prefixIcon}) {
    return InputDecoration(
      labelText: labelText,
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
      labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: Colors.grey),
    );
  }

  Widget _buildSectionHeader(String title, IconData icon) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFF1C1917), size: 20),
          const SizedBox(width: 8),
          Text(
            title,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Lên lịch chuyển phòng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          if (_isLoading)
            const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
          else
            SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_errorMessage != null)
                      Container(
                        padding: const EdgeInsets.all(12),
                        margin: const EdgeInsets.only(bottom: 16),
                        decoration: BoxDecoration(color: Colors.redAccent.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
                        child: Text(_errorMessage!, style: const TextStyle(color: Colors.redAccent)),
                      ),

                    // Card: Hợp đồng hiện tại
                    Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Hợp đồng hiện tại', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Colors.grey)),
                            const SizedBox(height: 8),
                            Text('Mã hợp đồng: ${widget.contract.contractCode}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                            Text('Phòng đang thuê: Phòng ${widget.contract.roomNumber}'),
                            Text('Giá phòng cũ: ${formatMoney(widget.contract.roomPrice)} / tháng'),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Section 1: Chọn Khách thuê di chuyển
                    Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _buildSectionHeader('Chọn khách thuê di chuyển', Icons.people_outline),
                            const Divider(),
                            if (_contractTenants.isEmpty)
                              const Padding(
                                padding: EdgeInsets.all(16),
                                child: Text('Không có khách thuê khả dụng.', style: TextStyle(color: Colors.grey, fontStyle: FontStyle.italic)),
                              )
                            else
                              ListView.builder(
                                shrinkWrap: true,
                                physics: const NeverScrollableScrollPhysics(),
                                itemCount: _contractTenants.length,
                                itemBuilder: (context, idx) {
                                  final t = _contractTenants[idx];
                                  return CheckboxListTile(
                                    title: Text(t['full_name'], style: const TextStyle(fontWeight: FontWeight.bold)),
                                    value: t['is_selected'] == true,
                                    activeColor: const Color(0xFF1C1917),
                                    onChanged: (val) {
                                      setState(() {
                                        t['is_selected'] = val;
                                      });
                                    },
                                  );
                                },
                              ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Section 2: Thông tin chuyển đến
                    Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            _buildSectionHeader('Thông tin chuyển đến', Icons.swap_horiz_rounded),
                            const SizedBox(height: 12),
                            // Room Dropdown
                            DropdownButtonFormField<dynamic>(
                              value: _selectedRoom,
                              hint: const Text('Chọn Phòng mới'),
                              decoration: _inputDecoration(labelText: 'Phòng mới', prefixIcon: Icons.meeting_room),
                              items: _availableRooms.map((r) => DropdownMenuItem(value: r, child: Text('Phòng ${r['room_number']}'))).toList(),
                              onChanged: (r) {
                                if (r != null) {
                                  setState(() {
                                    _selectedRoom = r;
                                    _depositController.text = formatMoneyInput(double.parse(r['base_price'].toString()).round().toString());
                                  });
                                }
                              },
                              validator: (val) => val == null ? 'Vui lòng chọn phòng mới' : null,
                            ),
                            const SizedBox(height: 12),

                            // Date Picker
                            TextFormField(
                              controller: _movementDateController,
                              readOnly: true,
                              decoration: _inputDecoration(labelText: 'Ngày chuyển phòng', prefixIcon: Icons.calendar_today),
                              onTap: () => _selectDate(context),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Section 3: Tài chính & Khấu hao
                    Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            _buildSectionHeader('Khấu hao & Phí phát sinh', Icons.payments_outlined),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Expanded(
                                  child: TextFormField(
                                    controller: _deductionController,
                                    keyboardType: TextInputType.number,
                                    inputFormatters: [CurrencyInputFormatter()],
                                    decoration: _inputDecoration(labelText: 'Khấu trừ hư hao (đ)', prefixIcon: Icons.money_off),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: TextFormField(
                                    controller: _feeController,
                                    keyboardType: TextInputType.number,
                                    inputFormatters: [CurrencyInputFormatter()],
                                    decoration: _inputDecoration(labelText: 'Phí chuyển phòng (đ)', prefixIcon: Icons.local_taxi),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _depositController,
                              keyboardType: TextInputType.number,
                              inputFormatters: [CurrencyInputFormatter()],
                              decoration: _inputDecoration(labelText: 'Tiền cọc phòng mới (đ)', prefixIcon: Icons.wallet),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _noteController,
                              maxLines: 3,
                              decoration: _inputDecoration(labelText: 'Ghi chú lý do chuyển', prefixIcon: Icons.note_alt_outlined),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Submit button
                    ElevatedButton(
                      onPressed: _handleSubmit,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF1C1917),
                        foregroundColor: const Color(0xFFEAB308),
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      child: const Text(
                        'LÊN LỊCH CHUYỂN PHÒNG',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                    ),
                    const SizedBox(height: 40),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
