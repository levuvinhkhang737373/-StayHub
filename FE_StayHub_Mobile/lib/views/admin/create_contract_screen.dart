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
  List<Map<String, dynamic>> _allFetchedVehicles = []; // Cached available vehicles of selected tenants

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
            String name = v?['license_plate']?.toString() ?? '';
            if (name.isEmpty) name = v?['brand']?.toString() ?? '';
            if (name.isEmpty) {
              final typeVal = v?['vehicle_type'];
              name = typeVal == 1 ? 'Xe máy' : (typeVal == 2 ? 'Xe đạp' : 'Ô tô');
            }
            if (v != null) {
              if (!_allFetchedVehicles.any((fav) => fav['id'] == v['id'])) {
                _allFetchedVehicles.add(v);
              }
            }
            return {
              'vehicle_id': cv['vehicle_id'] ?? v?['id'],
              'license_plate': name.isNotEmpty ? name : 'Chưa xác định',
              'started_at': isRenewMode ? _startDateController.text : (cv['started_at'] ?? _startDateController.text),
              'monthly_fee': double.parse((cv['monthly_fee'] ?? 0).toString()).round(),
              'charge_policy': cv['charge_policy'] ?? 1,
            };
          }).toList();

          // Fetch other vehicles for these tenants
          for (var t in _selectedTenants) {
            try {
              final tenantVehicles = await contractController.fetchTenantVehicles(t['tenant_id']);
              for (var v in tenantVehicles) {
                if (!_allFetchedVehicles.any((fav) => fav['id'] == v['id'])) {
                  _allFetchedVehicles.add(v);
                }
              }
            } catch (_) {}
          }

          // Pre-fill Services
          final servicesList = contractData['contract_services'] as List<dynamic>? ?? [];
          _selectedServices = servicesList.map((cs) {
            final s = cs['service'];
            return {
              'service_id': cs['service_id'] ?? s?['id'],
              'name': s?['name'] ?? 'Dịch vụ',
              'slug': s?['slug'],
              'charge_method': s?['charge_method'],
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
        final customPrices = servicePrices.where((sp) => sp['service_id'] == service.id && sp['status'] == 1);
        final customPrice = customPrices.isNotEmpty ? customPrices.first : null;
        final initialPrice = customPrice != null 
            ? double.parse(customPrice['price'].toString()).round().toString() 
            : (service.price != null ? service.price!.round().toString() : '0');

        return {
          'service_id': service.id,
          'name': service.name,
          'slug': service.slug,
          'charge_method': service.chargeMethod,
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
      });
    });

    // Auto-populate tenant's vehicles
    try {
      final vehicles = await context.read<ContractController>().fetchTenantVehicles(tenant.id);
      if (vehicles.isNotEmpty) {
        setState(() {
          for (var v in vehicles) {
            if (!_allFetchedVehicles.any((fav) => fav['id'] == v['id'])) {
              _allFetchedVehicles.add(v);
            }
            if (!_selectedVehicles.any((sv) => sv['vehicle_id'] == v['id'])) {
              String name = v['license_plate']?.toString() ?? '';
              if (name.isEmpty) name = v['brand']?.toString() ?? '';
              if (name.isEmpty) {
                final typeVal = v['vehicle_type'];
                name = typeVal == 1 ? 'Xe máy' : (typeVal == 2 ? 'Xe đạp' : 'Ô tô');
              }
              _selectedVehicles.add({
                'vehicle_id': v['id'],
                'license_plate': name.isNotEmpty ? name : 'Chưa xác định',
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

  void _removeTenantRow(int tenantId) {
    setState(() {
      _selectedTenants.removeWhere((t) => t['tenant_id'] == tenantId);
      // Remove vehicles belonging to this tenant from selected vehicles list
      _selectedVehicles.removeWhere((sv) {
        final vehicleId = sv['vehicle_id'];
        if (vehicleId != null) {
          final matchedList = _allFetchedVehicles.where((fav) => fav['id'] == vehicleId);
          final matchedVehicle = matchedList.isNotEmpty ? matchedList.first : null;
          if (matchedVehicle != null && matchedVehicle['tenant_id'] == tenantId) {
            return true;
          }
        }
        return false;
      });
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
              final matchesQuery = t.fullName.toLowerCase().contains(query) ||
                  (t.phone?.contains(query) ?? false) ||
                  (t.identityNumber.contains(query));
              final hasActiveContract = t.roomNumber != null && t.roomNumber!.isNotEmpty;
              return matchesQuery && !hasActiveContract && t.status == 1;
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

  void _showCreateVehicleDialog() {
    if (_selectedTenants.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng thêm khách thuê trước khi tạo xe!'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    final licenseController = TextEditingController();
    final brandController = TextEditingController();
    final colorController = TextEditingController();
    int selectedTenantId = _selectedTenants.first['tenant_id'];
    int selectedType = 1; // Xe máy

    showDialog(
      context: context,
      builder: (context) {
        bool isDialogLoading = false;
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              title: const Text('Tạo xe mới trong hệ thống'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (isDialogLoading)
                      const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
                    else ...[
                      DropdownButtonFormField<int>(
                        value: selectedTenantId,
                        decoration: _inputDecoration(labelText: 'Chủ sở hữu (Khách thuê)', prefixIcon: Icons.person),
                        items: _selectedTenants.map((t) {
                          return DropdownMenuItem<int>(
                            value: t['tenant_id'] as int,
                            child: Text(t['tenantName'] as String, overflow: TextOverflow.ellipsis),
                          );
                        }).toList(),
                        onChanged: (val) {
                          if (val != null) {
                            setDialogState(() {
                              selectedTenantId = val;
                            });
                          }
                        },
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<int>(
                        value: selectedType,
                        decoration: _inputDecoration(labelText: 'Loại phương tiện', prefixIcon: Icons.category),
                        items: const [
                          DropdownMenuItem(value: 1, child: Text('Xe máy')),
                          DropdownMenuItem(value: 2, child: Text('Xe đạp')),
                          DropdownMenuItem(value: 3, child: Text('Ô tô')),
                        ],
                        onChanged: (val) {
                          if (val != null) {
                            setDialogState(() {
                              selectedType = val;
                            });
                          }
                        },
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: licenseController,
                        decoration: _inputDecoration(labelText: 'Biển số xe', prefixIcon: Icons.credit_card),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: brandController,
                        decoration: _inputDecoration(labelText: 'Nhãn hiệu (VD: Honda Vision)', prefixIcon: Icons.branding_watermark),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: colorController,
                        decoration: _inputDecoration(labelText: 'Màu sắc (Tùy chọn)', prefixIcon: Icons.color_lens),
                      ),
                    ],
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('HỦY'),
                ),
                TextButton(
                  onPressed: isDialogLoading ? null : () async {
                    if (licenseController.text.trim().isEmpty && (selectedType == 1 || selectedType == 3)) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Vui lòng nhập biển số xe!'), backgroundColor: Colors.redAccent),
                      );
                      return;
                    }
                    setDialogState(() {
                      isDialogLoading = true;
                    });
                    final newVehicle = await context.read<ContractController>().registerNewVehicle(
                      tenantId: selectedTenantId,
                      vehicleType: selectedType,
                      licensePlate: licenseController.text.trim(),
                      brand: brandController.text.trim().isNotEmpty ? brandController.text.trim() : null,
                      color: colorController.text.trim().isNotEmpty ? colorController.text.trim() : null,
                    );
                    if (newVehicle != null) {
                      setState(() {
                        // 1. Add to fetched vehicles list
                        _allFetchedVehicles.add(newVehicle);
                        
                        // 2. Add directly to selected vehicles in form
                        String name = newVehicle['license_plate']?.toString() ?? '';
                        if (name.isEmpty) name = newVehicle['brand']?.toString() ?? '';
                        if (name.isEmpty) {
                          final typeVal = newVehicle['vehicle_type'];
                          name = typeVal == 1 ? 'Xe máy' : (typeVal == 2 ? 'Xe đạp' : 'Ô tô');
                        }
                        _selectedVehicles.add({
                          'vehicle_id': newVehicle['id'],
                          'license_plate': name,
                          'started_at': _startDateController.text,
                          'monthly_fee': double.parse((newVehicle['monthly_fee'] ?? 0).toString()).round(),
                          'charge_policy': 1,
                        });
                      });
                      if (context.mounted) Navigator.pop(context);
                    } else {
                      setDialogState(() {
                        isDialogLoading = false;
                      });
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(context.read<ContractController>().errorMessage ?? 'Lỗi tạo xe mới')),
                        );
                      }
                    }
                  },
                  child: const Text('TẠO MỚI', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917))),
                ),
              ],
            );
          },
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

    final representativeTenantId = _selectedTenants.first['tenant_id'];

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
      'representative_tenant_id': representativeTenantId,
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
      'services': _selectedServices.where((s) => (s['is_checked'] == true || _isUtilityService(s)) && !_isUtilityService(s)).map((s) => {
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

  InputDecoration _inputDecoration({required String labelText, String? hintText, IconData? prefixIcon}) {
    return InputDecoration(
      labelText: labelText,
      hintText: hintText,
      prefixIcon: prefixIcon != null ? Icon(prefixIcon, size: 18, color: const Color(0xFF1C1917)) : null,
      labelStyle: const TextStyle(color: Color(0xFF78716C), fontWeight: FontWeight.bold, fontSize: 13),
      hintStyle: const TextStyle(color: Colors.grey, fontSize: 13),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFFE4E2D7), width: 1.5),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFFE4E2D7), width: 1.5),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1.5),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Colors.redAccent, width: 2.0),
      ),
    );
  }

  bool _isUtilityService(Map<String, dynamic> s) {
    final slug = (s['slug'] ?? '').toString().toLowerCase();
    final name = (s['name'] ?? '').toString().toLowerCase();
    return ['electric', 'water', 'electricity', 'dien-sinh-hoat', 'nuoc-sinh-hoat', 'dien', 'nuoc'].contains(slug) ||
        name.contains('điện') ||
        name.contains('nước');
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
            SafeArea(
              child: SingleChildScrollView(
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
                                separatorBuilder: (_, __) => const SizedBox(height: 8),
                                itemBuilder: (context, idx) {
                                  final st = _selectedTenants[idx];
                                  final isRep = idx == 0;
                                  return Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                    decoration: BoxDecoration(
                                      color: isRep ? const Color(0xFFFFFDF9) : const Color(0xFFFBFBFA),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(
                                        color: isRep ? const Color(0xFFEAB308).withValues(alpha: 0.3) : const Color(0xFFE4E2D7),
                                      ),
                                    ),
                                    child: Row(
                                      children: [
                                        CircleAvatar(
                                          radius: 18,
                                          backgroundColor: isRep ? const Color(0xFFEAB308).withValues(alpha: 0.15) : const Color(0xFF1C1917).withValues(alpha: 0.05),
                                          child: Text(
                                            st['tenantName'].isNotEmpty ? st['tenantName'].substring(0, 1).toUpperCase() : 'T',
                                            style: TextStyle(
                                              fontWeight: FontWeight.bold,
                                              fontSize: 12,
                                              color: isRep ? const Color(0xFF8B5E34) : const Color(0xFF1C1917),
                                            ),
                                          ),
                                        ),
                                        const SizedBox(width: 12),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Row(
                                                children: [
                                                  Expanded(
                                                    child: Row(
                                                      children: [
                                                        Flexible(
                                                          child: Text(
                                                            st['tenantName'],
                                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                                            overflow: TextOverflow.ellipsis,
                                                          ),
                                                        ),
                                                      ],
                                                    ),
                                                  ),
                                                ],
                                              ),
                                              const SizedBox(height: 2),
                                              Text(
                                                'Ngày vào ở: ${st['join_date']}',
                                                style: const TextStyle(fontSize: 11, color: Colors.grey),
                                              ),
                                            ],
                                          ),
                                        ),
                                        IconButton(
                                          icon: const Icon(Icons.remove_circle_outline, color: Colors.redAccent, size: 20),
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
                            _buildSectionHeader('Đăng ký Phương tiện', Icons.directions_car_filled_outlined),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Expanded(
                                  child: ElevatedButton.icon(
                                    onPressed: () => _showCreateVehicleDialog(),
                                    icon: const Icon(Icons.add_circle_outline, size: 16),
                                    label: const Text('TẠO XE MỚI', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: Colors.white,
                                      foregroundColor: const Color(0xFF1C1917),
                                      side: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
                                      padding: const EdgeInsets.symmetric(vertical: 12),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: ElevatedButton.icon(
                                    onPressed: () {
                                      setState(() {
                                        _selectedVehicles.add({
                                          'vehicle_id': null,
                                          'license_plate': '',
                                          'started_at': _startDateController.text,
                                          'monthly_fee': 0,
                                          'charge_policy': 1,
                                        });
                                      });
                                    },
                                    icon: const Icon(Icons.add_circle, size: 16),
                                    label: const Text('THÊM DÒNG XE', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: const Color(0xFF1C1917),
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(vertical: 12),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const Divider(height: 24),
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
                                separatorBuilder: (_, __) => const SizedBox(height: 12),
                                itemBuilder: (context, idx) {
                                  final sv = _selectedVehicles[idx];
                                  final isUnselected = sv['vehicle_id'] == null;

                                  return Container(
                                    padding: const EdgeInsets.all(12),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFFBFBFA),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(color: const Color(0xFFE4E2D7)),
                                    ),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.stretch,
                                      children: [
                                        if (isUnselected) ...[
                                          // Vehicle Picker dropdown
                                          Row(
                                            children: [
                                              Expanded(
                                                child: DropdownButtonFormField<int>(
                                                  value: null,
                                                  decoration: _inputDecoration(labelText: 'Chọn phương tiện', prefixIcon: Icons.directions_car),
                                                  items: _allFetchedVehicles.where((v) {
                                                    return !_selectedVehicles.any((selected) => selected['vehicle_id'] == v['id']);
                                                  }).map((v) {
                                                    String name = v['license_plate']?.toString() ?? '';
                                                    if (name.isEmpty) name = v['brand']?.toString() ?? '';
                                                    if (name.isEmpty) {
                                                      final typeVal = v['vehicle_type'];
                                                      name = typeVal == 1 ? 'Xe máy' : (typeVal == 2 ? 'Xe đạp' : 'Ô tô');
                                                    }
                                                    return DropdownMenuItem<int>(
                                                      value: v['id'] as int,
                                                      child: Text(name, overflow: TextOverflow.ellipsis),
                                                    );
                                                  }).toList(),
                                                  onChanged: (val) {
                                                    if (val != null) {
                                                      final selected = _allFetchedVehicles.firstWhere((v) => v['id'] == val);
                                                      setState(() {
                                                        sv['vehicle_id'] = val;
                                                        String name = selected['license_plate']?.toString() ?? '';
                                                        if (name.isEmpty) name = selected['brand']?.toString() ?? '';
                                                        if (name.isEmpty) {
                                                          final typeVal = selected['vehicle_type'];
                                                          name = typeVal == 1 ? 'Xe máy' : (typeVal == 2 ? 'Xe đạp' : 'Ô tô');
                                                        }
                                                        sv['license_plate'] = name;
                                                        sv['monthly_fee'] = double.parse((selected['monthly_fee'] ?? 0).toString()).round();
                                                        sv['charge_policy'] = selected['charge_policy'] ?? 1;
                                                      });
                                                    }
                                                  },
                                                ),
                                              ),
                                              const SizedBox(width: 8),
                                              IconButton(
                                                icon: const Icon(Icons.delete_outline, color: Colors.redAccent),
                                                onPressed: () {
                                                  setState(() {
                                                    _selectedVehicles.removeAt(idx);
                                                  });
                                                },
                                              ),
                                            ],
                                          ),
                                        ] else ...[
                                          // Details & price settings
                                          Row(
                                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                            children: [
                                              Row(
                                                children: [
                                                  const Icon(Icons.directions_car, size: 18, color: Color(0xFF1C1917)),
                                                  const SizedBox(width: 8),
                                                  Text(
                                                    sv['license_plate'],
                                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917)),
                                                  ),
                                                ],
                                              ),
                                              IconButton(
                                                icon: const Icon(Icons.delete_outline, color: Colors.redAccent, size: 20),
                                                onPressed: () {
                                                  setState(() {
                                                    _selectedVehicles.removeAt(idx);
                                                  });
                                                },
                                              ),
                                            ],
                                          ),
                                          const SizedBox(height: 8),
                                          Row(
                                            children: [
                                              Expanded(
                                                child: TextFormField(
                                                  initialValue: sv['monthly_fee'].toString(),
                                                  keyboardType: TextInputType.number,
                                                  decoration: _inputDecoration(labelText: 'Phí gửi (đ/tháng)'),
                                                  onChanged: (val) {
                                                    sv['monthly_fee'] = double.tryParse(val) ?? 0;
                                                  },
                                                ),
                                              ),
                                              const SizedBox(width: 8),
                                              Expanded(
                                                child: DropdownButtonFormField<int>(
                                                  value: sv['charge_policy'],
                                                  decoration: _inputDecoration(labelText: 'Chu kỳ'),
                                                  items: const [
                                                    DropdownMenuItem(value: 1, child: Text('Hàng tháng')),
                                                    DropdownMenuItem(value: 2, child: Text('Hàng ngày')),
                                                    DropdownMenuItem(value: 3, child: Text('Miễn phí')),
                                                  ],
                                                  onChanged: (val) {
                                                    if (val != null) {
                                                      setState(() {
                                                        sv['charge_policy'] = val;
                                                      });
                                                    }
                                                  },
                                                ),
                                              ),
                                            ],
                                          ),
                                        ],
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
                            const Divider(height: 24),
                            if (_selectedServices.isEmpty)
                              const Padding(
                                padding: EdgeInsets.all(16),
                                child: Text('Không tìm thấy dịch vụ nào.', style: TextStyle(color: Colors.grey, fontStyle: FontStyle.italic)),
                              )
                            else
                              ListView.separated(
                                shrinkWrap: true,
                                physics: const NeverScrollableScrollPhysics(),
                                itemCount: _selectedServices.length,
                                separatorBuilder: (_, __) => const SizedBox(height: 12),
                                itemBuilder: (context, idx) {
                                  final s = _selectedServices[idx];
                                  final isUtility = _isUtilityService(s);
                                  final isChecked = isUtility ? true : (s['is_checked'] == true);
                                  return Container(
                                    padding: const EdgeInsets.all(12),
                                    decoration: BoxDecoration(
                                      color: isChecked ? const Color(0xFFFFFDF9) : const Color(0xFFFBFBFA),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(
                                        color: isChecked ? const Color(0xFFEAB308).withValues(alpha: 0.3) : const Color(0xFFE4E2D7),
                                        width: isChecked ? 1.5 : 1.0,
                                      ),
                                    ),
                                    child: Row(
                                      children: [
                                        Checkbox(
                                          value: isChecked,
                                          activeColor: const Color(0xFF1C1917),
                                          onChanged: isUtility
                                              ? null
                                              : (val) {
                                                  setState(() {
                                                    s['is_checked'] = val;
                                                  });
                                                },
                                        ),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(s['name'], style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                              const SizedBox(height: 4),
                                              Text(
                                                'Hình thức: ${s['charge_method_label']}',
                                                style: const TextStyle(fontSize: 11, color: Colors.grey),
                                              ),
                                              Text(
                                                'Đơn giá gốc: ${formatMoney(double.tryParse(s['price'].toString()) ?? 0)} / ${s['unit_name']}',
                                                style: const TextStyle(fontSize: 11, color: Colors.brown, fontWeight: FontWeight.w500),
                                              ),
                                            ],
                                          ),
                                        ),
                                        const SizedBox(width: 12),
                                        SizedBox(
                                          width: 110,
                                          child: TextFormField(
                                            initialValue: s['price'].toString(),
                                            keyboardType: TextInputType.number,
                                            inputFormatters: [CurrencyInputFormatter()],
                                            enabled: isChecked && !isUtility,
                                            decoration: InputDecoration(
                                              labelText: 'Giá mới (${s['unit_name']})',
                                              labelStyle: const TextStyle(fontSize: 9, fontWeight: FontWeight.bold),
                                              isDense: true,
                                              filled: true,
                                              fillColor: (isChecked && !isUtility) ? Colors.white : Colors.grey[100],
                                              contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                                              border: OutlineInputBorder(
                                                borderRadius: BorderRadius.circular(8),
                                                borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                                              ),
                                            ),
                                            onChanged: (val) {
                                              s['price'] = parseMoney(val).round().toString();
                                            },
                                          ),
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
                            if (!isEditMode && !isRenewMode) ...[
                              const SizedBox(height: 8),
                              DropdownButtonFormField<int>(
                                value: _depositPaymentMethod,
                                decoration: _inputDecoration(labelText: 'Phương thức đóng cọc', prefixIcon: Icons.payment),
                                items: const [
                                  DropdownMenuItem(value: 1, child: Text('Tiền mặt')),
                                  DropdownMenuItem(value: 2, child: Text('Chuyển khoản QR ngân hàng')),
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
          ),
        ],
      ),
    );
  }
}
