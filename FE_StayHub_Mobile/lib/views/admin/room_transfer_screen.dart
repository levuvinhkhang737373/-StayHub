import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/currency_formatter.dart';
import '../../controllers/contract_controller.dart';
import '../../controllers/tenant_controller.dart';
import '../../models/contract.dart';
import '../../models/tenant.dart';
import '../auth/login_screen.dart'; // import GridPainter

class RoomTransferScreen extends StatefulWidget {
  final Contract? contract;

  const RoomTransferScreen({
    super.key,
    this.contract,
  });

  @override
  State<RoomTransferScreen> createState() => _RoomTransferScreenState();
}

class _RoomTransferScreenState extends State<RoomTransferScreen> {
  final _formKey = GlobalKey<FormState>();
  final _searchController = TextEditingController();
  final _roomSearchController = TextEditingController();

  bool _isLoading = false;
  String? _errorMessage;

  // Stepper steps tracker
  int _currentStep = 0; // 0: Chọn khách, 1: Chọn phòng mới, 2: Quyết toán & đặt lịch

  // Selected Tenant & Contract
  Tenant? _selectedTenant;
  Contract? _activeContract;

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
    
    if (widget.contract != null) {
      _activeContract = widget.contract;
      _currentStep = 1;
      _initData();
    } else {
      _currentStep = 0;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        context.read<TenantController>().fetchTenants();
      });
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    _roomSearchController.dispose();
    _movementDateController.dispose();
    _deductionController.dispose();
    _feeController.dispose();
    _depositController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  String _nextMonthStartDateString() {
    final now = DateTime.now();
    final next = DateTime(now.year, now.month + 1, 1);
    final month = next.month.toString().padLeft(2, '0');
    final day = next.day.toString().padLeft(2, '0');
    return '${next.year}-$month-$day';
  }

  Future<void> _initData() async {
    if (_activeContract != null) {
      await _loadContractData(_activeContract!.id);
    }
  }

  Future<void> _loadContractData(int contractId) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final contractController = context.read<ContractController>();
      final contractData = await contractController.fetchContractDetail(contractId);

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

  Future<void> _onTenantSelected(Tenant tenant) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final tenantController = context.read<TenantController>();
      final tenantDetail = await tenantController.fetchTenantDetail(tenant.id);

      if (tenantDetail != null) {
        final currentContractData = tenantDetail['current_contract'];
        if (currentContractData != null) {
          final activeContract = Contract.fromJson(currentContractData);
          setState(() {
            _activeContract = activeContract;
            _selectedTenant = tenant;
            _currentStep = 1;
            // Clear any previous form state
            _selectedRoom = null;
            _contractTenants = [];
            _deductionController.text = '0';
            _feeController.text = '0';
            _depositController.clear();
            _noteController.clear();
          });
          await _loadContractData(activeContract.id);
        } else {
          setState(() {
            _errorMessage = 'Khách thuê này không có hợp đồng đang hoạt động nào.';
            _isLoading = false;
          });
        }
      } else {
        setState(() {
          _errorMessage = tenantController.errorMessage ?? 'Không thể tải chi tiết khách thuê.';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Lỗi: $e';
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
    if (_activeContract == null) return;
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
      contractId: _activeContract!.id,
      newRoomNumber: _selectedRoom!['room_number'].toString(),
      movementDate: _movementDateController.text,
      depositDeductionAmount: parseMoney(_deductionController.text),
      transferFee: parseMoney(_feeController.text),
      newDepositAmount: _depositController.text.trim().isEmpty ? null : parseMoney(_depositController.text),
      note: _noteController.text.trim(),
      tenantIds: selectedTenantIds,
    );

    setState(() {
      _isLoading = false;
      if (!success) {
        _errorMessage = contractController.errorMessage;
      }
    });

    if (success && mounted) {
      Navigator.pop(context, true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Đã lên lịch chuyển phòng thành công!'),
          backgroundColor: Colors.green,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ),
      );
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(contractController.errorMessage ?? 'Chuyển phòng thất bại.'),
          backgroundColor: Colors.redAccent,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          margin: const EdgeInsets.all(16),
        ),
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

  Widget _buildStepHeader(String title, IconData icon) {
    return Padding(
      padding: const EdgeInsets.only(top: 16.0, bottom: 8.0),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFFEAB308), size: 22),
          const SizedBox(width: 8),
          Text(
            title,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w900,
              color: Color(0xFF1C1917),
              letterSpacing: 1.1,
            ),
          ),
        ],
      ),
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
          onPressed: () {
            if (_currentStep > 0 && !(widget.contract != null && _currentStep == 1)) {
              setState(() {
                _currentStep--;
              });
            } else {
              Navigator.pop(context);
            }
          },
        ),
      ),
      body: Column(
        children: [
          _buildStepperProgress(),
          Expanded(
            child: Stack(
              children: [
                Positioned.fill(child: CustomPaint(painter: GridPainter())),
                if (_isLoading)
                  const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
                else
                  SafeArea(
                    child: _buildCurrentStepContent(),
                  ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: _buildBottomNavigationBar(),
    );
  }

  Widget _buildCurrentStepContent() {
    switch (_currentStep) {
      case 0:
        return _buildTenantStep();
      case 1:
        return _buildRoomStep();
      case 2:
        return _buildScheduleStep();
      default:
        return const Center(child: Text('Đang tải...'));
    }
  }

  Widget _buildStepperProgress() {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
      color: const Color(0xFF1C1917),
      child: Row(
        children: [
          _buildStepIndicator(0, 'Cư dân', enabled: widget.contract == null),
          _buildStepLine(0),
          _buildStepIndicator(1, 'Phòng mới'),
          _buildStepLine(1),
          _buildStepIndicator(2, 'Quyết toán'),
        ],
      ),
    );
  }

  Widget _buildStepIndicator(int stepIndex, String label, {bool enabled = true}) {
    final isActive = _currentStep == stepIndex;
    final isDone = _currentStep > stepIndex;

    Color circleColor = Colors.white24;
    Color textColor = Colors.white54;

    if (isActive) {
      circleColor = const Color(0xFFEAB308);
      textColor = Colors.white;
    } else if (isDone) {
      circleColor = const Color(0xFF0F5F59);
      textColor = Colors.white70;
    }

    return Opacity(
      opacity: enabled ? 1.0 : 0.4,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircleAvatar(
            radius: 12,
            backgroundColor: circleColor,
            child: isDone
                ? const Icon(Icons.check, size: 12, color: Colors.white)
                : Text(
                    '${stepIndex + 1}',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: isActive ? const Color(0xFF1C1917) : Colors.white,
                    ),
                  ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 10,
              fontWeight: isActive ? FontWeight.bold : FontWeight.normal,
              color: textColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStepLine(int afterStep) {
    final isDone = _currentStep > afterStep;
    return Expanded(
      child: Container(
        height: 2,
        margin: const EdgeInsets.only(bottom: 14, left: 8, right: 8),
        color: isDone ? const Color(0xFF0F5F59) : Colors.white24,
      ),
    );
  }

  Widget _buildTenantStep() {
    final tenantController = context.watch<TenantController>();
    final allTenants = tenantController.tenants;
    final rentingTenants = allTenants.where((t) => t.status == 1).toList();

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'BƯỚC 1: CHỌN CƯ DÂN CẦN CHUYỂN PHÒNG',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF1C1917),
                  letterSpacing: 0.5,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _searchController,
                onChanged: (val) => tenantController.search(val),
                style: const TextStyle(color: Color(0xFF1C1917)),
                decoration: InputDecoration(
                  hintText: 'Tìm theo tên, sđt hoặc số phòng...',
                  prefixIcon: const Icon(Icons.search, color: Color(0xFF1C1917)),
                  filled: true,
                  fillColor: Colors.white,
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE4E2D7), width: 1.5),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                  ),
                ),
              ),
            ],
          ),
        ),
        if (_errorMessage != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Container(
              padding: const EdgeInsets.all(12),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: Colors.redAccent.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                _errorMessage!,
                style: const TextStyle(color: Colors.redAccent),
              ),
            ),
          ),
        Expanded(
          child: tenantController.isLoading
              ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
              : rentingTenants.isEmpty
                  ? const Center(
                      child: Text(
                        'Không tìm thấy khách thuê đang ở nào.',
                        style: TextStyle(color: Colors.grey),
                      ),
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      itemCount: rentingTenants.length,
                      itemBuilder: (context, index) {
                        final tenant = rentingTenants[index];
                        return Card(
                          color: Colors.white,
                          margin: const EdgeInsets.only(bottom: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                            side: const BorderSide(color: Color(0xFFE4E2D7)),
                          ),
                          elevation: 0,
                          child: InkWell(
                            onTap: () => _onTenantSelected(tenant),
                            borderRadius: BorderRadius.circular(16),
                            child: Padding(
                              padding: const EdgeInsets.all(16.0),
                              child: Row(
                                children: [
                                  CircleAvatar(
                                    radius: 24,
                                    backgroundColor: const Color(0xFF1C1917).withValues(alpha: 0.05),
                                    child: Text(
                                      tenant.fullName.isNotEmpty
                                          ? tenant.fullName.substring(0, 1).toUpperCase()
                                          : 'T',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.bold,
                                        color: Color(0xFF1C1917),
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 16),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          tenant.fullName,
                                          style: const TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 15,
                                            color: Color(0xFF1C1917),
                                          ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          'SĐT: ${tenant.phone} | Phòng: ${tenant.roomNumber ?? "Chưa rõ"}',
                                          style: const TextStyle(
                                            fontSize: 12,
                                            color: Colors.grey,
                                          ),
                                        ),
                                        if (tenant.buildingName != null) ...[
                                          const SizedBox(height: 2),
                                          Text(
                                            tenant.buildingName!,
                                            style: const TextStyle(
                                              fontSize: 11,
                                              color: Colors.brown,
                                              fontWeight: FontWeight.w500,
                                            ),
                                          ),
                                        ]
                                      ],
                                    ),
                                  ),
                                  const Icon(
                                    Icons.arrow_forward_ios_rounded,
                                    size: 16,
                                    color: Colors.grey,
                                  ),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
        ),
      ],
    );
  }

  Widget _buildRoomStep() {
    if (_activeContract == null) {
      return const Center(child: Text('Không có dữ liệu hợp đồng'));
    }

    final query = _roomSearchController.text.trim().toLowerCase();
    final filteredRooms = _availableRooms.where((room) {
      final roomNo = room['room_number']?.toString().toLowerCase() ?? '';
      final maxOcc = room['max_occupants'] as int? ?? 99;
      final currOcc = room['current_occupants'] as int? ?? 0;
      final status = room['status'] as int? ?? 1;

      final matchesQuery = roomNo.contains(query);
      // Chỉ hiển thị phòng đang hoạt động (status == 1) và trống (currOcc == 0) hoặc chưa đầy (currOcc < maxOcc)
      final isAvailable = status == 1 && (currOcc == 0 || currOcc < maxOcc);
      return matchesQuery && isAvailable;
    }).toList();

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'BƯỚC 2: CHỌN PHÒNG ĐÍCH MUỐN CHUYỂN ĐẾN',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                  color: Color(0xFF1C1917),
                  letterSpacing: 0.5,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _roomSearchController,
                onChanged: (_) => setState(() {}),
                style: const TextStyle(color: Color(0xFF1C1917)),
                decoration: InputDecoration(
                  hintText: 'Tìm kiếm số phòng...',
                  prefixIcon: const Icon(Icons.search, color: Color(0xFF1C1917)),
                  filled: true,
                  fillColor: Colors.white,
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE4E2D7), width: 1.5),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: filteredRooms.isEmpty
              ? const Center(
                  child: Text(
                    'Không có phòng nào khả dụng hoặc chưa đầy.',
                    style: TextStyle(color: Colors.grey),
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  itemCount: filteredRooms.length,
                  itemBuilder: (context, index) {
                    final room = filteredRooms[index];
                    final isSelected = _selectedRoom != null && _selectedRoom!['id'] == room['id'];
                    final currentOcc = room['current_occupants'] as int? ?? 0;
                    final maxOcc = room['max_occupants'] as int? ?? 0;
                    final isEmpty = currentOcc == 0;

                    return Card(
                      color: isSelected ? const Color(0xFFFFFDF9) : Colors.white,
                      margin: const EdgeInsets.only(bottom: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                        side: BorderSide(
                          color: isSelected ? const Color(0xFFEAB308) : const Color(0xFFE4E2D7),
                          width: isSelected ? 1.5 : 1.0,
                        ),
                      ),
                      elevation: 0,
                      child: InkWell(
                        onTap: () {
                          setState(() {
                            _selectedRoom = room;
                            if (currentOcc > 0) {
                              _depositController.text = '0';
                            } else {
                              _depositController.text = formatMoneyInput(double.parse(room['base_price'].toString()).round().toString());
                            }
                          });
                        },
                        borderRadius: BorderRadius.circular(16),
                        child: Padding(
                          padding: const EdgeInsets.all(16.0),
                          child: Row(
                            children: [
                              CircleAvatar(
                                radius: 24,
                                backgroundColor: isSelected
                                    ? const Color(0xFFEAB308).withValues(alpha: 0.1)
                                    : const Color(0xFF1C1917).withValues(alpha: 0.05),
                                child: Icon(
                                  Icons.meeting_room,
                                  color: isSelected ? const Color(0xFFEAB308) : const Color(0xFF1C1917),
                                ),
                              ),
                              const SizedBox(width: 16),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Text(
                                          'Phòng ${room['room_number']}',
                                          style: const TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: 16,
                                            color: Color(0xFF1C1917),
                                          ),
                                        ),
                                        const SizedBox(width: 8),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                          decoration: BoxDecoration(
                                            color: isEmpty
                                                ? Colors.green.withValues(alpha: 0.1)
                                                : const Color(0xFFEAB308).withValues(alpha: 0.1),
                                            borderRadius: BorderRadius.circular(6),
                                            border: Border.all(
                                              color: isEmpty
                                                  ? Colors.green.withValues(alpha: 0.3)
                                                  : const Color(0xFFEAB308).withValues(alpha: 0.3),
                                            ),
                                          ),
                                          child: Text(
                                            isEmpty ? 'Phòng trống' : 'Chưa đầy ($currentOcc/$maxOcc người)',
                                            style: TextStyle(
                                              fontSize: 10,
                                              fontWeight: FontWeight.bold,
                                              color: isEmpty ? Colors.green[700] : const Color(0xFF8B5E34),
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      'Giá thuê: ${formatMoney(double.parse(room['base_price'].toString()))} / tháng',
                                      style: const TextStyle(
                                        fontSize: 13,
                                        color: Colors.grey,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              if (isSelected)
                                const Icon(
                                  Icons.check_circle,
                                  color: Color(0xFFEAB308),
                                ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _buildScheduleStep() {
    if (_activeContract == null || _selectedRoom == null) {
      return const Center(child: Text('Thiếu dữ liệu cư dân hoặc phòng mới'));
    }

    // Financial calculations matching web logic:
    final oldDepositBalance = _activeContract!.depositAmount;
    final damageAmount = parseMoney(_deductionController.text);
    final transferFeeAmount = parseMoney(_feeController.text);
    final transferChargesAmount = damageAmount + transferFeeAmount;

    final destinationRoomHasContract = (_selectedRoom!['current_occupants'] as int? ?? 0) > 0;

    final totalContractTenants = _contractTenants.length;
    final selectedTenantIds = _contractTenants
        .where((t) => t['is_selected'] == true)
        .map((t) => t['id'] as int)
        .toList();
    final selectedTenantCount = selectedTenantIds.length;
    final movingAllTenants = totalContractTenants > 0 && selectedTenantCount >= totalContractTenants;

    final usesOldDepositSettlement = movingAllTenants;
    final availableAfterCosts = usesOldDepositSettlement
        ? (oldDepositBalance - transferChargesAmount).clamp(0.0, double.infinity)
        : oldDepositBalance;

    final extraChargeAmount = usesOldDepositSettlement
        ? (transferChargesAmount - oldDepositBalance).clamp(0.0, double.infinity)
        : transferChargesAmount;

    final requiredNewDeposit = destinationRoomHasContract
        ? 0.0
        : parseMoney(_depositController.text.trim().isEmpty ? _selectedRoom!['base_price'].toString() : _depositController.text);

    final depositAppliedToNewRoom = destinationRoomHasContract || !usesOldDepositSettlement
        ? 0.0
        : (availableAfterCosts < requiredNewDeposit ? availableAfterCosts : requiredNewDeposit);

    final manualRefundAmount = !usesOldDepositSettlement
        ? 0.0
        : destinationRoomHasContract
            ? availableAfterCosts
            : (availableAfterCosts - requiredNewDeposit).clamp(0.0, double.infinity);

    final depositDueAmount = destinationRoomHasContract
        ? 0.0
        : usesOldDepositSettlement
            ? (requiredNewDeposit - availableAfterCosts).clamp(0.0, double.infinity)
            : requiredNewDeposit;

    final settlementDueAmount = depositDueAmount + extraChargeAmount;

    return SingleChildScrollView(
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
                decoration: BoxDecoration(color: Colors.redAccent.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                child: Text(_errorMessage!, style: const TextStyle(color: Colors.redAccent)),
              ),

            // Card: Hợp đồng hiện tại & Phòng mới được chọn
            Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              elevation: 0,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Hợp đồng & Phòng chuyển đến', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Colors.grey)),
                    const SizedBox(height: 8),
                    Text('Mã hợp đồng: ${_activeContract!.contractCode}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    Text('Khách đại diện: ${_selectedTenant?.fullName ?? _activeContract!.tenantName}'),
                    Text('Phòng cũ: Phòng ${_activeContract!.roomNumber.isNotEmpty ? _activeContract!.roomNumber : (_selectedTenant?.roomNumber ?? "")} (Giá cũ: ${formatMoney(_activeContract!.roomPrice)}/tháng)'),
                    const Divider(height: 16),
                    Row(
                      children: [
                        const Icon(Icons.arrow_forward_rounded, size: 16, color: Color(0xFFEAB308)),
                        const SizedBox(width: 8),
                        Text(
                          'Phòng mới: Phòng ${_selectedRoom!['room_number']} (Giá mới: ${formatMoney(double.parse(_selectedRoom!['base_price'].toString()))}/tháng)',
                          style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        ),
                      ],
                    ),
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

            // Section 2: Lịch & Lý do
            Card(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              elevation: 0,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    _buildSectionHeader('Thời gian chuyển', Icons.calendar_today_rounded),
                    const SizedBox(height: 12),
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
                            onChanged: (_) => setState(() {}),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: TextFormField(
                            controller: _feeController,
                            keyboardType: TextInputType.number,
                            inputFormatters: [CurrencyInputFormatter()],
                            decoration: _inputDecoration(labelText: 'Phí chuyển phòng (đ)', prefixIcon: Icons.local_taxi),
                            onChanged: (_) => setState(() {}),
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
                      onChanged: (_) => setState(() {}),
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
            const SizedBox(height: 16),

            // BẢNG QUYẾT TOÁN TÀI CHÍNH DỰ KIẾN (Giống giao diện web)
            Card(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: const BorderSide(color: Color(0xFFEAB308), width: 0.5),
              ),
              color: const Color(0xFFFFFDF9),
              elevation: 0,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.calculate_outlined, color: Color(0xFFEAB308), size: 20),
                        const SizedBox(width: 8),
                        const Text(
                          'QUYẾT TOÁN TÀI CHÍNH DỰ KIẾN',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFF1C1917),
                            letterSpacing: 0.5,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _buildSummaryRow('Khấu trừ hư hao:', '-${formatMoney(damageAmount)}', valueColor: Colors.redAccent),
                    _buildSummaryRow('Phí chuyển phòng:', '-${formatMoney(transferFeeAmount)}', valueColor: Colors.redAccent),
                    const Divider(height: 16),
                    _buildSummaryRow(
                      usesOldDepositSettlement ? 'Cọc khả dụng sau phí:' : 'Cọc cũ giữ lại:',
                      formatMoney(availableAfterCosts),
                      isBold: true,
                      valueColor: usesOldDepositSettlement && availableAfterCosts > 0 ? const Color(0xFF0F5F59) : null,
                    ),
                    _buildSummaryRow('Cọc chuyển sang phòng mới:', formatMoney(depositAppliedToNewRoom)),
                    _buildSummaryRow('Cọc mới còn thiếu:', formatMoney(depositDueAmount), valueColor: depositDueAmount > 0 ? Colors.redAccent : null),
                    _buildSummaryRow('Phí/khấu hao thu thêm:', formatMoney(extraChargeAmount), valueColor: extraChargeAmount > 0 ? Colors.redAccent : null),
                    const Divider(height: 16),

                    // Final settlement amount
                    if (settlementDueAmount > 0)
                      Container(
                        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                        decoration: BoxDecoration(
                          color: Colors.redAccent.withValues(alpha: 0.05),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.redAccent.withValues(alpha: 0.2)),
                        ),
                        child: Column(
                          children: [
                            const Text(
                              'KHÁCH CẦN NỘP THÊM',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Colors.redAccent, letterSpacing: 1),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              formatMoney(settlementDueAmount),
                              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Colors.redAccent),
                            ),
                          ],
                        ),
                      )
                    else if (manualRefundAmount > 0)
                      Container(
                        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                        decoration: BoxDecoration(
                          color: const Color(0xFF0F5F59).withValues(alpha: 0.05),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFF0F5F59).withValues(alpha: 0.2)),
                        ),
                        child: Column(
                          children: [
                            const Text(
                              'HOÀN CỌC CHO KHÁCH (THỦ CÔNG)',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Color(0xFF0F5F59), letterSpacing: 1),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              formatMoney(manualRefundAmount),
                              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Color(0xFF0F5F59)),
                            ),
                          ],
                        ),
                      )
                    else
                      Container(
                        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 16),
                        decoration: BoxDecoration(
                          color: Colors.grey.withValues(alpha: 0.05),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.grey.withValues(alpha: 0.2)),
                        ),
                        child: Column(
                          children: [
                            const Text(
                              'ĐỐI TRỪ CÂN BẰNG',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: Colors.grey, letterSpacing: 1),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              formatMoney(0),
                              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: Colors.grey),
                            ),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget? _buildBottomNavigationBar() {
    if (_isLoading) return null;

    if (_currentStep == 0) return null;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: Color(0xFFE4E2D7))),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          // Back button
          if (_currentStep > 0 && !(widget.contract != null && _currentStep == 1))
            OutlinedButton(
              onPressed: () {
                setState(() {
                  _currentStep--;
                });
              },
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF1C1917),
                side: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: const Text('QUAY LẠI', style: TextStyle(fontWeight: FontWeight.bold)),
            )
          else
            const SizedBox(),

          // Continue button
          ElevatedButton(
            onPressed: () {
              if (_currentStep == 1) {
                if (_selectedRoom == null) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Vui lòng chọn phòng mới!'), backgroundColor: Colors.redAccent),
                  );
                  return;
                }
                setState(() {
                  _currentStep = 2;
                });
              } else if (_currentStep == 2) {
                _handleSubmit();
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF1C1917),
              foregroundColor: const Color(0xFFEAB308),
              padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: Text(
              _currentStep == 2 ? 'LÊN LỊCH CHUYỂN PHÒNG' : 'TIẾP TỤC',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryRow(String label, String value, {bool isBold = false, Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: isBold ? FontWeight.bold : FontWeight.w500,
              color: isBold ? const Color(0xFF1C1917) : Colors.grey[700],
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.bold,
              color: valueColor ?? const Color(0xFF1C1917),
            ),
          ),
        ],
      ),
    );
  }
}

