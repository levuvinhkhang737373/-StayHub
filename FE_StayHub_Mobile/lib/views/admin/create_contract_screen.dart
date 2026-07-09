import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/currency_formatter.dart';
import '../../controllers/contract_controller.dart';
import '../../controllers/facility_controller.dart';
import '../../controllers/tenant_controller.dart';
import '../../controllers/service_controller.dart';
import '../../models/contract.dart';
import '../../models/building.dart';
import '../../models/room.dart';
import '../../models/tenant.dart';
import '../../models/service.dart';
import '../auth/login_screen.dart'; // import GridPainter

class CreateContractScreen extends StatefulWidget {
  final Contract? contract;
  final bool isRenew;

  const CreateContractScreen({
    super.key,
    this.contract,
    this.isRenew = false,
  });

  @override
  State<CreateContractScreen> createState() => _CreateContractScreenState();
}

class _CreateContractScreenState extends State<CreateContractScreen> {
  final _formKey = GlobalKey<FormState>();

  bool _isLoading = false;
  String? _errorMessage;

  // Form Fields
  Building? _selectedBuilding;
  Map<String, dynamic>? _selectedRoom; // contains room details + services
  final _codeController = TextEditingController();
  final _startDateController = TextEditingController();
  final _endDateController = TextEditingController();
  final _priceController = TextEditingController();
  final _depositController = TextEditingController();
  final _noteController = TextEditingController();

  bool _isDepositPaid = true;
  int _depositPaymentMethod = 1; // 1: Cash, 2: Bank Transfer

  // Option Lists
  List<Building> _buildings = [];
  List<dynamic> _availableRooms = [];
  List<Tenant> _allTenants = [];

  // Selected sub-items
  List<Map<String, dynamic>> _selectedTenants = []; // tenant_id, tenantName, join_date, isRepresentative
  List<Map<String, dynamic>> _selectedVehicles = []; // vehicle_id, license_plate, started_at, monthly_fee, charge_policy
  List<Map<String, dynamic>> _selectedServices = []; // service_id, name, price, charge_method_label, unit_name, is_checked

  bool get isEditMode => widget.contract != null && !widget.isRenew;
  bool get isRenewMode => widget.contract != null && widget.isRenew;

  @override
  void initState() {
    super.initState();
    _initData();
  }

  Future<void> _initData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      // 1. Fetch buildings
      final facilityController = context.read<FacilityController>();
      await facilityController.fetchBuildings();
      _buildings = facilityController.buildings;

      // 2. Fetch all tenants
      final tenantController = context.read<TenantController>();
      await tenantController.fetchTenants();
      _allTenants = tenantController.tenants;

      if (widget.contract != null) {
        // Edit or Renew Mode: Load full contract detail
        final contractController = context.read<ContractController>();
        final contractData = await contractController.fetchContractDetail(widget.contract!.id);

        if (contractData != null) {
          // Pre-fill fields
          final bId = contractData['building_id'] ?? contractData['room']?['building_id'];
          if (bId != null) {
            _selectedBuilding = _buildings.firstWhere(
              (b) => b.id == bId,
              orElse: () => Building(
                id: bId,
                regionId: 0,
                name: contractData['room']?['building_name'] ?? 'Tòa nhà',
                status: 1,
                genderPolicy: 1,
              ),
            );
          }

          // In edit/renew mode, room info is frozen
          _selectedRoom = {
            'id': contractData['room_id'],
            'room_number': contractData['room']?['room_number'] ?? contractData['room_number'],
            'base_price': contractData['room_price'],
          };

          _codeController.text = isRenewMode ? '' : (contractData['contract_code'] ?? '');
          _priceController.text = formatMoneyInput(double.parse(contractData['room_price'].toString()).round().toString());
          _depositController.text = formatMoneyInput(double.parse(contractData['deposit_amount'].toString()).round().toString());
          _noteController.text = contractData['note'] ?? '';
          _isDepositPaid = contractData['is_deposit_paid'] == 1 || contractData['is_deposit_paid'] == true;
          _depositPaymentMethod = contractData['deposit_payment_method'] ?? 1;

          // Set dates
          if (isRenewMode) {
            final oldEndDateStr = contractData['end_date'] as String?;
            if (oldEndDateStr != null && oldEndDateStr.isNotEmpty) {
              try {
                final oldEndDate = DateTime.parse(oldEndDateStr);
                final newStartDate = oldEndDate.add(const Duration(days: 1));
                _startDateController.text = newStartDate.toString().split(' ')[0];
                final newEndDate = DateTime(newStartDate.year + 1, newStartDate.month, newStartDate.day).subtract(const Duration(days: 1));
                _endDateController.text = newEndDate.toString().split(' ')[0];
              } catch (_) {
                _startDateController.text = DateTime.now().toString().split(' ')[0];
                _endDateController.text = DateTime.now().add(const Duration(days: 365)).toString().split(' ')[0];
              }
            }
          } else {
            _startDateController.text = contractData['start_date'] ?? '';
            _endDateController.text = contractData['end_date'] ?? '';
          }

          // Pre-fill Tenants
          final tenantsList = contractData['contract_tenants'] as List<dynamic>? ?? [];
          final repId = contractData['representative_tenant_id'] ?? contractData['tenant_id'];
          _selectedTenants = tenantsList.map((ct) {
            final t = ct['tenant'];
            final tId = ct['tenant_id'] ?? t?['id'];
            return {
              'tenant_id': tId,
              'tenantName': t?['full_name'] ?? 'Khách thuê',
              'join_date': isRenewMode ? _startDateController.text : (ct['join_date'] ?? _startDateController.text),
              'isRepresentative': tId == repId,
            };
          }).toList();

          // Pre-fill Vehicles
          final vehiclesList = contractData['contract_vehicles'] as List<dynamic>? ?? [];
          _selectedVehicles = vehiclesList.map((cv) {
            final v = cv['vehicle'];
            return {
              'vehicle_id': cv['vehicle_id'] ?? v?['id'],
              'license_plate': v?['license_plate'] ?? 'Chưa xác định',
              'started_at': isRenewMode ? _startDateController.text : (cv['started_at'] ?? _startDateController.text),
              'monthly_fee': double.parse((cv['monthly_fee'] ?? 0).toString()).round(),
              'charge_policy': cv['charge_policy'] ?? 1,
            };
          }).toList();

          // Pre-fill Services
          final servicesList = contractData['contract_services'] as List<dynamic>? ?? [];
          _selectedServices = servicesList.map((cs) {
            final s = cs['service'];
            return {
              'service_id': cs['service_id'] ?? s?['id'],
              'name': s?['name'] ?? 'Dịch vụ',
              'price': double.parse((cs['price'] ?? 0).toString()).round().toString(),
              'charge_method_label': s?['charge_method'] == 1 ? 'Theo chỉ số' : (s?['charge_method'] == 2 ? 'Cố định' : 'Miễn phí'),
              'unit_name': s?['unit_name'] ?? '',
              'is_checked': true,
            };
          }).toList();
        }
      } else {
        // Create Mode: Set default dates
        _startDateController.text = DateTime.now().toString().split(' ')[0];
        final nextYear = DateTime.now().add(const Duration(days: 365));
        _endDateController.text = nextYear.toString().split(' ')[0];
      }
    } catch (e) {
      _errorMessage = 'Không thể tải dữ liệu ban đầu: $e';
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _loadRoomsForBuilding(int buildingId) async {
    setState(() {
      _isLoading = true;
      _selectedRoom = null;
      _availableRooms = [];
      _selectedServices = [];
    });

    try {
      final contractController = context.read<ContractController>();
      _availableRooms = await contractController.fetchAvailableRooms(buildingId);

      // Load services of the building as well
      final facilityDetail = await context.read<FacilityController>().fetchBuildingDetail(buildingId);
      final servicePrices = facilityDetail?['service_prices'] as List<dynamic>? ?? [];
      
      // Load all active system services to merge custom building prices
      final serviceController = context.read<ServiceController>();
      await serviceController.fetchServices();
      final systemServices = serviceController.services;

      _selectedServices = systemServices.map((service) {
        final customPrice = servicePrices.firstWhere(
          (sp) => sp['service_id'] == service.id && sp['status'] == 1,
          orElse: () => null,
        );
        final initialPrice = customPrice != null 
            ? double.parse(customPrice['price'].toString()).round().toString() 
            : (service.price != null ? service.price!.round().toString() : '0');

        return {
          'service_id': service.id,
          'name': service.name,
          'price': initialPrice,
          'charge_method_label': service.chargeMethod == 1 ? 'Theo chỉ số' : (service.chargeMethod == 2 ? 'Cố định' : 'Miễn phí'),
          'unit_name': service.unitName ?? '',
          'is_checked': service.isRequired, // Required services are checked by default
        };
      }).toList();

    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Lỗi tải danh sách phòng: $e'), backgroundColor: Colors.redAccent),
      );
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _addTenantRow(Tenant tenant) async {
    // Check if already selected
    if (_selectedTenants.any((t) => t['tenant_id'] == tenant.id)) return;

    setState(() {
      _selectedTenants.add({
        'tenant_id': tenant.id,
        'tenantName': tenant.fullName,
        'join_date': _startDateController.text,
        'isRepresentative': _selectedTenants.isEmpty, // first is representative by default
      });
    });

    // Auto-populate tenant's vehicles
    try {
      final vehicles = await context.read<ContractController>().fetchTenantVehicles(tenant.id);
      if (vehicles.isNotEmpty) {
        setState(() {
          for (var v in vehicles) {
            if (!_selectedVehicles.any((sv) => sv['vehicle_id'] == v['id'])) {
              _selectedVehicles.add({
                'vehicle_id': v['id'],
                'license_plate': v['license_plate'] ?? 'Chưa xác định',
                'started_at': _startDateController.text,
                'monthly_fee': double.parse((v['monthly_fee'] ?? 0).toString()).round(),
                'charge_policy': 1, // Monthly default
              });
            }
          }
        });
      }
    } catch (_) {}
  }

  void _selectRepresentative(int tenantId) {
    setState(() {
      for (var t in _selectedTenants) {
        t['isRepresentative'] = t['tenant_id'] == tenantId;
      }
    });
  }

  void _removeTenantRow(int tenantId) {
    setState(() {
      _selectedTenants.removeWhere((t) => t['tenant_id'] == tenantId);
      // Remove their vehicles as well
      // In a real app we might verify first, but keeping it simple like web
      if (_selectedTenants.isNotEmpty && !_selectedTenants.any((t) => t['isRepresentative'] == true)) {
        _selectedTenants.first['isRepresentative'] = true;
      }
    });
  }

  void _showAddTenantDialog() {
    String search = '';
    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            final filtered = _allTenants.where((t) {
              final query = search.toLowerCase();
              return t.fullName.toLowerCase().contains(query) ||
                  (t.phone?.contains(query) ?? false) ||
                  (t.identityNumber.contains(query));
            }).toList();

            return AlertDialog(
              title: const Text('Thêm khách thuê vào hợp đồng'),
              content: SizedBox(
                width: double.maxFinite,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextField(
                      decoration: const InputDecoration(
                        labelText: 'Tìm kiếm tên, SĐT, CCCD',
                        prefixIcon: Icon(Icons.search),
                        border: OutlineInputBorder(),
                      ),
                      onChanged: (val) {
                        setDialogState(() {
                          search = val;
                        });
                      },
                    ),
                    const SizedBox(height: 12),
                    Expanded(
                      child: ListView.builder(
                        itemCount: filtered.length,
                        itemBuilder: (context, idx) {
                          final tenant = filtered[idx];
                          final isAdded = _selectedTenants.any((st) => st['tenant_id'] == tenant.id);

                          return ListTile(
                            title: Text(tenant.fullName, style: const TextStyle(fontWeight: FontWeight.bold)),
                            subtitle: Text('SĐT: ${tenant.phone ?? "N/A"} • CCCD: ${tenant.identityNumber}'),
                            trailing: isAdded
                                ? const Icon(Icons.check_circle, color: Colors.green)
                                : IconButton(
                                    icon: const Icon(Icons.add_circle_outline, color: Color(0xFF1C1917)),
                                    onPressed: () {
                                      _addTenantRow(tenant);
                                      Navigator.pop(context);
                                    },
                                  ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('ĐÓNG'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  void _showAddVehicleDialog() {
    // Show manual registration or list of vehicles if any
    final licenseController = TextEditingController();
    final feeController = TextEditingController(text: '0');
    int policy = 1;

    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Đăng ký phương tiện mới'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: licenseController,
                decoration: const InputDecoration(labelText: 'Biển số xe / Nhãn hiệu', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: feeController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Phí gửi xe hàng tháng (đ)', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int>(
                value: policy,
                decoration: const InputDecoration(labelText: 'Chính sách tính phí', border: OutlineInputBorder()),
                items: const [
                  DropdownMenuItem(value: 1, child: Text('Hàng tháng (Cố định)')),
                  DropdownMenuItem(value: 2, child: Text('Hàng ngày (Tính ngày)')),
                  DropdownMenuItem(value: 3, child: Text('Miễn phí')),
                ],
                onChanged: (val) {
                  if (val != null) policy = val;
                },
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('HỦY'),
            ),
            TextButton(
              onPressed: () {
                if (licenseController.text.trim().isEmpty) return;
                setState(() {
                  _selectedVehicles.add({
                    'vehicle_id': null, // Custom added vehicle
                    'license_plate': licenseController.text.trim(),
                    'started_at': _startDateController.text,
                    'monthly_fee': double.tryParse(feeController.text) ?? 0,
                    'charge_policy': policy,
                  });
                });
                Navigator.pop(context);
              },
              child: const Text('THÊM', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917))),
            ),
          ],
        );
      },
    );
  }

  Future<void> _handleSave() async {
    if (!_formKey.currentState!.validate()) return;

    if (_selectedTenants.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng thêm ít nhất một khách thuê!'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    final double roomPriceVal = parseMoney(_priceController.text);
    final double depositVal = parseMoney(_depositController.text);

    if (depositVal <= roomPriceVal) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tiền đặt cọc phải lớn hơn tiền phòng.'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    final repTenant = _selectedTenants.firstWhere((t) => t['isRepresentative'] == true, orElse: () => _selectedTenants.first);

    // Build payload
    final payload = {
      if (_codeController.text.isNotEmpty) 'contract_code': _codeController.text.trim(),
      'room_id': _selectedRoom?['id'],
      'start_date': _startDateController.text,
      'end_date': _endDateController.text,
      'room_price': roomPriceVal,
      'deposit_amount': depositVal,
      'is_deposit_paid': _isDepositPaid,
      'deposit_payment_method': _depositPaymentMethod,
      'note': _noteController.text.trim(),
      'representative_tenant_id': repTenant['tenant_id'],
      'tenants': _selectedTenants.map((t) => {
        'tenant_id': t['tenant_id'],
        'join_date': t['join_date'],
        'is_staying': true,
      }).toList(),
      'vehicles': _selectedVehicles.map((v) => {
        if (v['vehicle_id'] != null) 'vehicle_id': v['vehicle_id'],
        'license_plate': v['license_plate'],
        'started_at': v['started_at'],
        'monthly_fee': v['monthly_fee'],
        'charge_policy': v['charge_policy'],
      }).toList(),
      'services': _selectedServices.where((s) => s['is_checked'] == true).map((s) => {
        'service_id': s['service_id'],
        'price': double.tryParse(s['price'].toString()) ?? 0.0,
      }).toList(),
    };

    setState(() {
      _isLoading = true;
    });

    final contractController = context.read<ContractController>();
    final success = await contractController.saveContract(
      payload: payload,
      editContractId: widget.contract?.id,
      isRenew: isRenewMode,
    );

    setState(() {
      _isLoading = false;
    });

    if (success && mounted) {
      Navigator.pop(context, true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(isEditMode 
              ? 'Cập nhật hợp đồng thành công!' 
              : (isRenewMode ? 'Gia hạn/Tái ký hợp đồng thành công!' : 'Lập hợp đồng mới thành công!')),
          backgroundColor: Colors.green,
        ),
      );
    } else if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(contractController.errorMessage ?? 'Không thể thực hiện tác vụ.'),
          backgroundColor: Colors.redAccent,
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

  Future<void> _selectDate(BuildContext context, TextEditingController controller) async {
    final initialDate = DateTime.tryParse(controller.text) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(2020),
      lastDate: DateTime(2040),
    );
    if (picked != null) {
      setState(() {
        controller.text = picked.toString().split(' ')[0];
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = isEditMode 
        ? 'Chỉnh sửa Hợp đồng' 
        : (isRenewMode ? 'Gia hạn/Tái ký HĐ' : 'Lập Hợp đồng mới');

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
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

                    // Section 1: Thông tin chung
                    Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          children: [
                            _buildSectionHeader('Thông tin chung', Icons.info_outline),
                            const SizedBox(height: 12),
                            // Building Dropdown
                            DropdownButtonFormField<Building>(
                              value: _selectedBuilding,
                              hint: const Text('Chọn Tòa nhà'),
                              decoration: _inputDecoration(labelText: 'Tòa nhà', prefixIcon: Icons.business),
                              disabledHint: _selectedBuilding != null ? Text(_selectedBuilding!.name) : null,
                              items: isEditMode || isRenewMode 
                                  ? null 
                                  : _buildings.map((b) => DropdownMenuItem(value: b, child: Text(b.name))).toList(),
                              onChanged: isEditMode || isRenewMode 
                                  ? null 
                                  : (b) {
                                      if (b != null) {
                                        setState(() {
                                          _selectedBuilding = b;
                                        });
                                        _loadRoomsForBuilding(b.id);
                                      }
                                    },
                              validator: (val) => val == null ? 'Vui lòng chọn tòa nhà' : null,
                            ),
                            const SizedBox(height: 12),

                            // Room Dropdown
                            DropdownButtonFormField<dynamic>(
                              value: _selectedRoom,
                              hint: const Text('Chọn Phòng'),
                              decoration: _inputDecoration(labelText: 'Phòng', prefixIcon: Icons.meeting_room),
                              disabledHint: _selectedRoom != null ? Text('Phòng ${_selectedRoom!['room_number']}') : null,
                              items: isEditMode || isRenewMode
                                  ? null
                                  : _availableRooms.map((r) => DropdownMenuItem(value: r, child: Text('Phòng ${r['room_number']}'))).toList(),
                              onChanged: isEditMode || isRenewMode
                                  ? null
                                  : (r) {
                                      if (r != null) {
                                        setState(() {
                                          _selectedRoom = r;
                                          _priceController.text = formatMoneyInput(double.parse(r['base_price'].toString()).round().toString());
                                          _depositController.text = formatMoneyInput(double.parse(r['base_price'].toString()).round().toString());

                                          // Map room services
                                          final rServices = r['services'] as List<dynamic>? ?? [];
                                          for (var rs in rServices) {
                                            final idx = _selectedServices.indexWhere((s) => s['service_id'] == rs['id']);
                                            if (idx != -1) {
                                              _selectedServices[idx]['price'] = double.parse((rs['pivot']?['price'] ?? 0).toString()).round().toString();
                                              _selectedServices[idx]['is_checked'] = true;
                                            }
                                          }
                                        });
                                      }
                                    },
                              validator: (val) => val == null ? 'Vui lòng chọn phòng' : null,
                            ),
                            const SizedBox(height: 12),

                             // Contract Code (Read-only, only show when editing an existing contract)
                             if (isEditMode && widget.contract != null) ...[
                               TextFormField(
                                 initialValue: widget.contract!.contractCode,
                                 readOnly: true,
                                 decoration: _inputDecoration(labelText: 'Số Hợp đồng (Mã số)', prefixIcon: Icons.vpn_key_outlined),
                               ),
                               const SizedBox(height: 12),
                             ],

                            // Start Date & End Date
                            Row(
                              children: [
                                Expanded(
                                  child: TextFormField(
                                    controller: _startDateController,
                                    readOnly: true,
                                    decoration: _inputDecoration(labelText: 'Ngày bắt đầu', prefixIcon: Icons.calendar_today),
                                    onTap: () => _selectDate(context, _startDateController),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: TextFormField(
                                    controller: _endDateController,
                                    readOnly: true,
                                    decoration: _inputDecoration(labelText: 'Ngày kết thúc', prefixIcon: Icons.calendar_today),
                                    onTap: () => _selectDate(context, _endDateController),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),

                            // Price & Deposit
                            Row(
                              children: [
                                Expanded(
                                  child: TextFormField(
                                    controller: _priceController,
                                    keyboardType: TextInputType.number,
                                    inputFormatters: [CurrencyInputFormatter()],
                                    decoration: _inputDecoration(labelText: 'Giá thuê (đ/tháng)', prefixIcon: Icons.payments),
                                    validator: (val) => val == null || val.isEmpty ? 'Vui lòng nhập giá' : null,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: TextFormField(
                                    controller: _depositController,
                                    keyboardType: TextInputType.number,
                                    inputFormatters: [CurrencyInputFormatter()],
                                    decoration: _inputDecoration(labelText: 'Tiền đặt cọc (đ)', prefixIcon: Icons.wallet),
                                    validator: (val) => val == null || val.isEmpty ? 'Vui lòng nhập tiền cọc' : null,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Section 2: Khách thuê
                    Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                _buildSectionHeader('Khách thuê trong hợp đồng', Icons.people_outline),
                                ElevatedButton.icon(
                                  onPressed: _showAddTenantDialog,
                                  icon: const Icon(Icons.person_add_alt_1_rounded, size: 16),
                                  label: const Text('THÊM', style: TextStyle(fontSize: 12)),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: const Color(0xFF1C1917),
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                  ),
                                ),
                              ],
                            ),
                            const Divider(),
                            if (_selectedTenants.isEmpty)
                              const Padding(
                                padding: EdgeInsets.all(16),
                                child: Text('Chưa có khách thuê nào được thêm.', style: TextStyle(color: Colors.grey, fontStyle: FontStyle.italic)),
                              )
                            else
                              ListView.separated(
                                shrinkWrap: true,
                                physics: const NeverScrollableScrollPhysics(),
                                itemCount: _selectedTenants.length,
                                separatorBuilder: (_, __) => const Divider(),
                                itemBuilder: (context, idx) {
                                  final st = _selectedTenants[idx];
                                  return ListTile(
                                    title: Text(st['tenantName'], style: const TextStyle(fontWeight: FontWeight.bold)),
                                    subtitle: Text('Ngày vào ở: ${st['join_date']}'),
                                    trailing: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        // Representative indicator
                                        FilterChip(
                                          label: const Text('Người Đại Diện', style: TextStyle(fontSize: 11)),
                                          selected: st['isRepresentative'] == true,
                                          selectedColor: const Color(0xFFFEF08A),
                                          checkmarkColor: Colors.black,
                                          onSelected: (_) => _selectRepresentative(st['tenant_id']),
                                        ),
                                        IconButton(
                                          icon: const Icon(Icons.remove_circle_outline, color: Colors.red),
                                          onPressed: () => _removeTenantRow(st['tenant_id']),
                                        ),
                                      ],
                                    ),
                                  );
                                },
                              ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Section 3: Phương tiện
                    Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                _buildSectionHeader('Đăng ký Phương tiện', Icons.directions_car_filled_outlined),
                                ElevatedButton.icon(
                                  onPressed: _showAddVehicleDialog,
                                  icon: const Icon(Icons.add, size: 16),
                                  label: const Text('ĐĂNG KÝ XE', style: TextStyle(fontSize: 12)),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: const Color(0xFF1C1917),
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                  ),
                                ),
                              ],
                            ),
                            const Divider(),
                            if (_selectedVehicles.isEmpty)
                              const Padding(
                                padding: EdgeInsets.all(16),
                                child: Text('Chưa đăng ký phương tiện nào.', style: TextStyle(color: Colors.grey, fontStyle: FontStyle.italic)),
                              )
                            else
                              ListView.separated(
                                shrinkWrap: true,
                                physics: const NeverScrollableScrollPhysics(),
                                itemCount: _selectedVehicles.length,
                                separatorBuilder: (_, __) => const Divider(),
                                itemBuilder: (context, idx) {
                                  final sv = _selectedVehicles[idx];
                                  final policyLabel = sv['charge_policy'] == 1 
                                      ? 'Cố định' 
                                      : (sv['charge_policy'] == 2 ? 'Theo ngày' : 'Miễn phí');

                                  return ListTile(
                                    title: Text(sv['license_plate'], style: const TextStyle(fontWeight: FontWeight.bold)),
                                    subtitle: Text('Đơn giá: ${formatMoney(sv['monthly_fee'])} • Cách thu: $policyLabel'),
                                    trailing: IconButton(
                                      icon: const Icon(Icons.delete_outline, color: Colors.grey),
                                      onPressed: () {
                                        setState(() {
                                          _selectedVehicles.removeAt(idx);
                                        });
                                      },
                                    ),
                                  );
                                },
                              ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Section 4: Dịch vụ áp dụng
                    Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _buildSectionHeader('Dịch vụ phòng áp dụng', Icons.electrical_services_outlined),
                            const Divider(),
                            if (_selectedServices.isEmpty)
                              const Padding(
                                padding: EdgeInsets.all(16),
                                child: Text('Không tìm thấy dịch vụ nào.', style: TextStyle(color: Colors.grey, fontStyle: FontStyle.italic)),
                              )
                            else
                              ListView.builder(
                                shrinkWrap: true,
                                physics: const NeverScrollableScrollPhysics(),
                                itemCount: _selectedServices.length,
                                itemBuilder: (context, idx) {
                                  final s = _selectedServices[idx];
                                  return CheckboxListTile(
                                    title: Text(s['name'], style: const TextStyle(fontWeight: FontWeight.bold)),
                                    subtitle: Text('Hình thức: ${s['charge_method_label']} • Đơn giá: ${formatMoney(double.tryParse(s['price'].toString()) ?? 0)} / ${s['unit_name']}'),
                                    value: s['is_checked'] == true,
                                    activeColor: const Color(0xFF1C1917),
                                    onChanged: (val) {
                                      setState(() {
                                        s['is_checked'] = val;
                                      });
                                    },
                                    secondary: SizedBox(
                                      width: 100,
                                      child: TextFormField(
                                        initialValue: s['price'].toString(),
                                        keyboardType: TextInputType.number,
                                        decoration: const InputDecoration(
                                          labelText: 'Giá tiền',
                                          isDense: true,
                                          contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                                        ),
                                        onChanged: (val) {
                                          s['price'] = val;
                                        },
                                      ),
                                    ),
                                  );
                                },
                              ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Deposit details & Note
                    Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 0,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _buildSectionHeader('Thanh toán cọc & Ghi chú', Icons.payment_outlined),
                            const SizedBox(height: 12),
                            CheckboxListTile(
                              title: const Text('Đã thu tiền đặt cọc'),
                              value: _isDepositPaid,
                              activeColor: const Color(0xFF1C1917),
                              onChanged: (val) {
                                if (val != null) {
                                  setState(() {
                                    _isDepositPaid = val;
                                  });
                                }
                              },
                            ),
                            if (_isDepositPaid) ...[
                              const SizedBox(height: 8),
                              DropdownButtonFormField<int>(
                                value: _depositPaymentMethod,
                                decoration: _inputDecoration(labelText: 'Phương thức thanh toán cọc', prefixIcon: Icons.payment),
                                items: const [
                                  DropdownMenuItem(value: 1, child: Text('Tiền mặt')),
                                  DropdownMenuItem(value: 2, child: Text('Chuyển khoản ngân hàng')),
                                ],
                                onChanged: (val) {
                                  if (val != null) {
                                    setState(() {
                                      _depositPaymentMethod = val;
                                    });
                                  }
                                },
                              ),
                            ],
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _noteController,
                              maxLines: 3,
                              decoration: _inputDecoration(labelText: 'Ghi chú thêm', prefixIcon: Icons.note_alt_outlined),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Submit button
                    ElevatedButton(
                      onPressed: _handleSave,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF1C1917),
                        foregroundColor: const Color(0xFFEAB308),
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      child: Text(
                        isEditMode 
                            ? 'CẬP NHẬT HỢP ĐỒNG' 
                            : (isRenewMode ? 'GIA HẠN HỢP ĐỒNG' : 'LẬP HỢP ĐỒNG MỚI'),
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
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
