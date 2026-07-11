import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../config/currency_formatter.dart';
import '../../controllers/contract_controller.dart';
import '../../models/contract.dart';
import '../auth/login_screen.dart'; // import GridPainter
import 'create_contract_screen.dart';

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

  String _nextMonthStartDateString() {
    final now = DateTime.now();
    final next = DateTime(now.year, now.month + 1, 1);
    final month = next.month.toString().padLeft(2, '0');
    final day = next.day.toString().padLeft(2, '0');
    return '${next.year}-$month-$day';
  }

  double _parseMoney(String value) {
    return parseMoney(value);
  }

  InputDecoration _inputDecoration({required String labelText, String? helperText, IconData? prefixIcon}) {
    return InputDecoration(
      labelText: labelText,
      helperText: helperText,
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
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1.5),
      ),
      labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: Colors.grey),
    );
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
                    decoration: _inputDecoration(labelText: 'Số Hợp đồng', prefixIcon: Icons.assignment_outlined),
                    validator: (val) => val == null || val.isEmpty ? 'Nhập số hợp đồng' : null,
                  ),
                  const SizedBox(height: 12),
                  
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: roomController,
                          decoration: _inputDecoration(labelText: 'Số phòng', prefixIcon: Icons.meeting_room_outlined),
                          validator: (val) => val == null || val.isEmpty ? 'Nhập phòng' : null,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: tenantController,
                          decoration: _inputDecoration(labelText: 'Tên khách đại diện', prefixIcon: Icons.person_outline),
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
                          decoration: _inputDecoration(labelText: 'Ngày bắt đầu', prefixIcon: Icons.calendar_today_outlined),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: endController,
                          decoration: _inputDecoration(labelText: 'Ngày kết thúc', prefixIcon: Icons.calendar_today_outlined),
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
                          inputFormatters: [CurrencyInputFormatter()],
                          decoration: _inputDecoration(labelText: 'Giá thuê (đ/tháng)', prefixIcon: Icons.payments_outlined),
                          validator: (val) => val == null || val.isEmpty ? 'Nhập giá' : null,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: depositController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [CurrencyInputFormatter()],
                          decoration: _inputDecoration(labelText: 'Tiền đặt cọc (đ)', prefixIcon: Icons.wallet_outlined),
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
                        rentalPrice: _parseMoney(priceController.text),
                        depositAmount: _parseMoney(depositController.text),
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
            decoration: _inputDecoration(labelText: 'Ngày hết hạn mới', prefixIcon: Icons.calendar_today_outlined),
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



  void _showAddTenantDialog(Contract contract) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return AddTenantToContractDialog(contract: contract);
      },
    ).then((result) {
      if (result == true) {
        context.read<ContractController>().fetchContracts('admin');
      }
    });
  }

  void _showTerminateContractDialog(Contract contract) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return TerminateContractDialog(contract: contract);
      },
    ).then((result) {
      if (result == true) {
        context.read<ContractController>().fetchContracts('admin');
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final contractController = context.watch<ContractController>();
    final contracts = contractController.contracts;

    final activeContracts = contracts.where((c) => c.status == Contract.STATUS_ACTIVE || c.status == Contract.STATUS_DRAFT).toList();
    final inactiveContracts = contracts.where((c) => c.status == Contract.STATUS_EXPIRED || c.status == Contract.STATUS_LIQUIDATED || c.status == Contract.STATUS_CANCELLED).toList();

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F6F0),
        appBar: AppBar(
          title: const Text('Quản lý Hợp đồng', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Colors.white)),
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
                  Tab(text: 'Còn hiệu lực / Chờ ký'),
                  Tab(text: 'Hết hạn / Thanh lý'),
                ],
              ),
            ),
          ),
        ),
        body: Stack(
          children: [
            Positioned.fill(child: CustomPaint(painter: GridPainter())),
            TabBarView(
              children: [
                _buildContractList(activeContracts, contractController),
                _buildContractList(inactiveContracts, contractController),
              ],
            ),
          ],
        ),
        floatingActionButton: FloatingActionButton(
          onPressed: () async {
            final result = await Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => const CreateContractScreen(),
              ),
            );
            if (result == true) {
              contractController.fetchContracts('admin');
            }
          },
          backgroundColor: const Color(0xFF1C1917),
          foregroundColor: const Color(0xFFEAB308),
          child: const Icon(Icons.add),
        ),
      ),
    );
  }

  Widget _buildContractList(List<Contract> filteredContracts, ContractController contractController) {
    if (filteredContracts.isEmpty) {
      return const Center(
        child: Text(
          'Không có hợp đồng nào.',
          style: TextStyle(color: Colors.grey, fontSize: 14, fontWeight: FontWeight.w500),
        ),
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: filteredContracts.length,
      itemBuilder: (context, index) {
        final contract = filteredContracts[index];
        debugPrint('CONTRACT_DEBUG: ${contract.contractCode} | room=${contract.room} | occupants=${contract.room?.currentOccupants}/${contract.room?.maxOccupants}');
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
                    Expanded(
                      child: Text(
                        contract.contractCode,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 8),
                    _buildStatusBadge(contract),
                  ],
                ),
                const SizedBox(height: 12),

                // Details
                _buildInfoRow(Icons.person_outline, 'Khách thuê:', contract.tenantName),
                _buildInfoRow(Icons.meeting_room_outlined, 'Phòng:', 'Phòng ${contract.roomNumber}'),
                _buildInfoRow(Icons.calendar_today_outlined, 'Thời hạn:', '${contract.startDate} -> ${contract.endDate ?? "Chưa rõ"}'),
                _buildInfoRow(Icons.payments_outlined, 'Giá thuê:', '${formatMoney(contract.rentalPrice)} / tháng', iconColor: const Color(0xFFEAB308)),
                _buildInfoRow(Icons.wallet_outlined, 'Đặt cọc:', formatMoney(contract.depositAmount)),
                
                // Actions strip
                if (contract.status == Contract.STATUS_ACTIVE || 
                    contract.status == Contract.STATUS_EXPIRED || 
                    contract.status == Contract.STATUS_DRAFT) ...[
                  const Divider(height: 24, color: Color(0xFFE4E2D7)),
                  Row(
                    children: [
                      if (contract.status == Contract.STATUS_DRAFT)
                        // Edit Button
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () async {
                              final result = await Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) => CreateContractScreen(contract: contract, isRenew: false),
                                ),
                              );
                              if (result == true) {
                                contractController.fetchContracts('admin');
                              }
                            },
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFF1C1917),
                              side: const BorderSide(color: Color(0xFF1C1917)),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Text('Chỉnh sửa', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                          ),
                        ),
                      if (contract.status == Contract.STATUS_EXPIRED)
                        // Renew/Extend Screen Button
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () async {
                              final result = await Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) => CreateContractScreen(contract: contract, isRenew: true),
                                ),
                              );
                              if (result == true) {
                                contractController.fetchContracts('admin');
                              }
                            },
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFFEAB308),
                              side: const BorderSide(color: Color(0xFFEAB308)),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Text('Gia hạn', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                          ),
                        ),
                      if (contract.status == Contract.STATUS_ACTIVE) ...[
                        if (contract.room != null && contract.room!.currentOccupants < contract.room!.maxOccupants) ...[
                          Expanded(
                            child: OutlinedButton(
                              onPressed: () => _showAddTenantDialog(contract),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: const Color(0xFFEAB308),
                                side: const BorderSide(color: Color(0xFFEAB308)),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              ),
                              child: const Text('Thêm người', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                            ),
                          ),
                          const SizedBox(width: 12),
                        ],
                        // Terminate Button
                        Expanded(
                          child: ElevatedButton(
                            onPressed: () => _showTerminateContractDialog(contract),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.redAccent,
                              foregroundColor: Colors.white,
                              elevation: 0,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            child: const Text('Thanh lý', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                          ),
                        ),
                      ],
                    ],
                  )
                ]
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value, {Color? iconColor}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Icon(icon, size: 16, color: iconColor ?? const Color(0xFF8B5E34).withValues(alpha: 0.7)),
              const SizedBox(width: 8),
              Text(label, style: const TextStyle(color: Colors.grey, fontSize: 13, fontWeight: FontWeight.w500)),
            ],
          ),
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
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.25), width: 1),
      ),
      child: Text(
        contract.statusLabel.toUpperCase(),
        style: TextStyle(color: color, fontSize: 9.5, fontWeight: FontWeight.bold, letterSpacing: 0.5),
      ),
    );
  }
}

class AddTenantToContractDialog extends StatefulWidget {
  final Contract contract;
  const AddTenantToContractDialog({super.key, required this.contract});

  @override
  State<AddTenantToContractDialog> createState() => _AddTenantToContractDialogState();
}

class _AddTenantToContractDialogState extends State<AddTenantToContractDialog> {
  final _formKey = GlobalKey<FormState>();
  
  List<dynamic> _tenants = [];
  bool _isLoading = true;
  String? _error;
  
  int? _selectedTenantId;
  Map<String, dynamic>? _selectedTenant;
  
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _joinDateController = TextEditingController();
  final TextEditingController _billingStartDateController = TextEditingController();
  
  Timer? _debounceTimer;
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    final today = DateTime.now();
    final todayStr = "${today.year}-${today.month.toString().padLeft(2, '0')}-${today.day.toString().padLeft(2, '0')}";
    _joinDateController.text = todayStr;
    _billingStartDateController.text = todayStr;

    _searchController.addListener(_onSearchChanged);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadTenants().then((_) {
        if (mounted && _tenants.isNotEmpty) {
          setState(() {
            _selectedTenantId = _tenants.first['id'] as int?;
            _selectedTenant = _tenants.first;
          });
        }
      });
    });
  }

  @override
  void dispose() {
    _searchController.removeListener(_onSearchChanged);
    _searchController.dispose();
    _joinDateController.dispose();
    _billingStartDateController.dispose();
    _debounceTimer?.cancel();
    super.dispose();
  }

  void _onSearchChanged() {
    if (_debounceTimer?.isActive ?? false) _debounceTimer!.cancel();
    _debounceTimer = Timer(const Duration(milliseconds: 500), () {
      if (mounted) {
        _loadTenants();
      }
    });
  }

  Future<void> _loadTenants() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final list = await context.read<ContractController>().fetchAvailableTenants(
        widget.contract.id,
        keyword: _searchController.text,
      );
      if (mounted) {
        setState(() {
          _tenants = list;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  DateTime? _parseDate(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return null;
    try {
      return DateTime.parse(dateStr);
    } catch (_) {
      return null;
    }
  }

  Future<void> _selectJoinDate() async {
    final contractStart = _parseDate(widget.contract.startDate) ?? DateTime(2020);
    final contractEnd = _parseDate(widget.contract.actualEndDate ?? widget.contract.endDate) ?? DateTime(2035);
    
    DateTime initial = DateTime.now();
    if (initial.isBefore(contractStart)) {
      initial = contractStart;
    } else if (initial.isAfter(contractEnd)) {
      initial = contractEnd;
    }
    
    DateTime? current = _parseDate(_joinDateController.text);
    if (current != null && !current.isBefore(contractStart) && !current.isAfter(contractEnd)) {
      initial = current;
    }

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: contractStart,
      lastDate: contractEnd,
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF1C1917),
              onPrimary: Colors.white,
              onSurface: Color(0xFF1C1917),
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() {
        final formattedDate = "${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}";
        _joinDateController.text = formattedDate;
        
        final billingDate = _parseDate(_billingStartDateController.text);
        if (billingDate == null || billingDate.isBefore(picked)) {
          _billingStartDateController.text = formattedDate;
        }
      });
    }
  }

  Future<void> _selectBillingStartDate() async {
    final joinDate = _parseDate(_joinDateController.text) ?? _parseDate(widget.contract.startDate) ?? DateTime(2020);
    final contractEnd = _parseDate(widget.contract.actualEndDate ?? widget.contract.endDate) ?? DateTime(2035);

    DateTime initial = DateTime.now();
    if (initial.isBefore(joinDate)) {
      initial = joinDate;
    } else if (initial.isAfter(contractEnd)) {
      initial = contractEnd;
    }

    DateTime? current = _parseDate(_billingStartDateController.text);
    if (current != null && !current.isBefore(joinDate) && !current.isAfter(contractEnd)) {
      initial = current;
    }

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: joinDate,
      lastDate: contractEnd,
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF1C1917),
              onPrimary: Colors.white,
              onSurface: Color(0xFF1C1917),
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() {
        _billingStartDateController.text =
            "${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}";
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedTenantId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng chọn khách thuê')),
      );
      return;
    }

    setState(() {
      _isSaving = true;
    });

    final success = await context.read<ContractController>().addTenantToContract(
      contractId: widget.contract.id,
      tenantId: _selectedTenantId!,
      joinDate: _joinDateController.text,
      billingStartDate: _billingStartDateController.text,
    );

    setState(() {
      _isSaving = false;
    });

    if (mounted) {
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Thêm khách thuê vào phòng thành công!'), backgroundColor: Colors.green),
        );
        Navigator.pop(context, true);
      } else {
        final controller = context.read<ContractController>();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(controller.errorMessage ?? 'Thêm khách thuê thất bại'), backgroundColor: Colors.redAccent),
        );
      }
    }
  }

  InputDecoration _inputDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: Color(0xFF78716C), fontWeight: FontWeight.bold, fontSize: 13),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFFE4E2D7), width: 1.5),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Color(0xFFF7F6F0),
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(28),
          topRight: Radius.circular(28),
        ),
      ),
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
        top: 24,
        left: 24,
        right: 24,
      ),
      child: Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.8,
        ),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Thêm người vào phòng ${widget.contract.roomNumber}',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF1C1917)),
              ),
              const SizedBox(height: 6),
              Text(
                'Tìm cư dân, chọn người muốn thêm và cấu hình ngày tính tiền.',
                style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
              ),
              const Divider(height: 24, color: Color(0xFFE4E2D7)),
              
              Expanded(
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      TextFormField(
                        controller: _searchController,
                        decoration: _inputDecoration('Tìm cư dân theo tên, SĐT, email...').copyWith(
                          prefixIcon: const Icon(Icons.search, color: Color(0xFF78716C)),
                          hintText: 'Nhập từ khóa tìm kiếm...',
                        ),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                      ),
                      const SizedBox(height: 16),
                      
                      const Text(
                        'DANH SÁCH CƯ DÂN KHẢ DỤNG',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 0.8, color: Color(0xFF78716C)),
                      ),
                      const SizedBox(height: 8),

                      if (_isLoading)
                        const Center(
                          child: Padding(
                            padding: EdgeInsets.symmetric(vertical: 36),
                            child: CircularProgressIndicator(color: Color(0xFF1C1917)),
                          ),
                        )
                      else if (_error != null)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 24),
                          child: Text('Lỗi: $_error', style: const TextStyle(color: Colors.redAccent)),
                        )
                      else if (_tenants.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 36),
                          child: Text(
                            'Không tìm thấy khách thuê phù hợp.',
                            style: TextStyle(color: Colors.grey, fontSize: 13, fontWeight: FontWeight.bold),
                            textAlign: TextAlign.center,
                          ),
                        )
                      else
                        ListView.builder(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: _tenants.length,
                          itemBuilder: (context, index) {
                            final item = _tenants[index];
                            final isSelected = _selectedTenantId == item['id'];
                            return GestureDetector(
                              onTap: () {
                                setState(() {
                                  _selectedTenantId = item['id'] as int?;
                                  _selectedTenant = item;
                                });
                              },
                              child: Container(
                                margin: const EdgeInsets.only(bottom: 10),
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: isSelected ? const Color(0xFFFFFBEB) : Colors.white,
                                  borderRadius: BorderRadius.circular(16),
                                  border: Border.all(
                                    color: isSelected ? const Color(0xFFEAB308) : const Color(0xFFE4E2D7),
                                    width: isSelected ? 2.0 : 1.5,
                                  ),
                                  boxShadow: isSelected ? [
                                    BoxShadow(
                                      color: const Color(0xFFEAB308).withOpacity(0.1),
                                      blurRadius: 6,
                                      offset: const Offset(0, 3),
                                    )
                                  ] : null,
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            item['full_name'] ?? item['username'] ?? 'Không tên',
                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917)),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            [item['phone'], item['email']].where((e) => e != null && e.toString().isNotEmpty).join(' · '),
                                            style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                                          ),
                                          if (item['identity_number'] != null && item['identity_number'].toString().isNotEmpty) ...[
                                            const SizedBox(height: 4),
                                            Text(
                                              'CCCD: ${item['identity_number']}',
                                              style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                                            ),
                                          ],
                                          const SizedBox(height: 8),
                                          Row(
                                            children: [
                                              if (item['gender_label'] != null) ...[
                                                Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                                  decoration: BoxDecoration(
                                                    color: const Color(0xFFE4E2D7).withOpacity(0.5),
                                                    borderRadius: BorderRadius.circular(8),
                                                  ),
                                                  child: Text(
                                                    item['gender_label'],
                                                    style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF78716C)),
                                                  ),
                                                ),
                                                const SizedBox(width: 8),
                                              ],
                                              if (item['status_label'] != null) ...[
                                                Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                                  decoration: BoxDecoration(
                                                    color: const Color(0xFFEAB308).withOpacity(0.12),
                                                    borderRadius: BorderRadius.circular(8),
                                                  ),
                                                  child: Text(
                                                    item['status_label'],
                                                    style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFFCA8A04)),
                                                  ),
                                                ),
                                              ],
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Icon(
                                      isSelected ? Icons.check_circle_rounded : Icons.add_circle_outline_rounded,
                                      color: isSelected ? const Color(0xFFEAB308) : Colors.grey.shade400,
                                      size: 24,
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      const SizedBox(height: 16),

                      if (_selectedTenant != null) ...[
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: const Color(0xFFEAB308).withOpacity(0.4), width: 1.5),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'CƯ DÂN ĐÃ CHỌN',
                                style: TextStyle(fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 0.8, color: Color(0xFF78716C)),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                _selectedTenant!['full_name'] ?? _selectedTenant!['username'] ?? 'Không tên',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                [_selectedTenant!['phone'], _selectedTenant!['email']].where((e) => e != null && e.toString().isNotEmpty).join(' · ') ?? 'Không có liên hệ',
                                style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                      ],

                      TextFormField(
                        controller: _joinDateController,
                        readOnly: true,
                        onTap: _selectJoinDate,
                        decoration: _inputDecoration('Ngày tham gia phòng *').copyWith(
                          suffixIcon: const Icon(Icons.calendar_today_rounded, color: Color(0xFF1C1917)),
                        ),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        validator: (val) => val == null || val.trim().isEmpty ? 'Vui lòng chọn ngày tham gia' : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _billingStartDateController,
                        readOnly: true,
                        onTap: _selectBillingStartDate,
                        decoration: _inputDecoration('Ngày bắt đầu tính hóa đơn *').copyWith(
                          suffixIcon: const Icon(Icons.calendar_today_rounded, color: Color(0xFF1C1917)),
                        ),
                        style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        validator: (val) => val == null || val.trim().isEmpty ? 'Vui lòng chọn ngày tính tiền' : null,
                      ),
                    ],
                  ),
                ),
              ),
              
              const Divider(height: 24, color: Color(0xFFE4E2D7)),
              
              ElevatedButton(
                onPressed: _isSaving ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1C1917),
                  foregroundColor: const Color(0xFFEAB308),
                  disabledBackgroundColor: Colors.grey.shade400,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: _isSaving
                    ? const SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(color: Color(0xFFEAB308), strokeWidth: 3),
                      )
                    : const Text('LƯU THÔNG TIN', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => Navigator.pop(context),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF1C1917),
                  side: const BorderSide(color: Color(0xFFE4E2D7)),
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('HỦY BỎ', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class TerminateContractDialog extends StatefulWidget {
  final Contract contract;
  const TerminateContractDialog({super.key, required this.contract});

  @override
  State<TerminateContractDialog> createState() => _TerminateContractDialogState();
}

class _TerminateContractDialogState extends State<TerminateContractDialog> {
  final _formKey = GlobalKey<FormState>();
  
  final TextEditingController _endDateController = TextEditingController();
  final TextEditingController _deductionController = TextEditingController(text: '0');
  final TextEditingController _noteController = TextEditingController();
  int _paymentMethod = 2; // 2 = Chuyển khoản, 1 = Tiền mặt

  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    final today = DateTime.now();
    _endDateController.text = "${today.year}-${today.month.toString().padLeft(2, '0')}-${today.day.toString().padLeft(2, '0')}";
  }

  @override
  void dispose() {
    _endDateController.dispose();
    _deductionController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _selectDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2035),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: Color(0xFF1C1917),
              onPrimary: Colors.white,
              onSurface: Color(0xFF1C1917),
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) {
      setState(() {
        _endDateController.text =
            "${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}";
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    
    final deduction = double.tryParse(_deductionController.text.trim()) ?? 0.0;
    if (deduction < 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Số tiền cấn trừ không được nhỏ hơn 0')),
      );
      return;
    }

    if (deduction > widget.contract.depositAmount) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Số tiền cấn trừ (${formatMoney(deduction)}) không được vượt quá số tiền cọc (${formatMoney(widget.contract.depositAmount)})'
          ),
        ),
      );
      return;
    }

    setState(() {
      _isSaving = true;
    });

    final success = await context.read<ContractController>().terminateContract(
      widget.contract.id,
      actualEndDate: _endDateController.text,
      deductionAmount: deduction,
      paymentMethod: _paymentMethod,
      note: _noteController.text.trim(),
    );

    setState(() {
      _isSaving = false;
    });

    if (mounted) {
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Thanh lý hợp đồng thành công!'), backgroundColor: Colors.green),
        );
        Navigator.pop(context, true);
      } else {
        final controller = context.read<ContractController>();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(controller.errorMessage ?? 'Thanh lý thất bại'), backgroundColor: Colors.redAccent),
        );
      }
    }
  }

  InputDecoration _inputDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: Color(0xFF78716C), fontWeight: FontWeight.bold, fontSize: 13),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFFE4E2D7), width: 1.5),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: Color(0xFF1C1917), width: 1.5),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Color(0xFFF7F6F0),
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(28),
          topRight: Radius.circular(28),
        ),
      ),
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
        top: 24,
        left: 24,
        right: 24,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Thanh lý hợp đồng ${widget.contract.contractCode}',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF1C1917)),
              ),
              const SizedBox(height: 8),
              Text(
                'Số tiền đặt cọc hiện tại: ${formatMoney(widget.contract.depositAmount)}',
                style: const TextStyle(color: Color(0xFFEAB308), fontSize: 13, fontWeight: FontWeight.bold),
              ),
              const Divider(height: 32, color: Color(0xFFE4E2D7)),
              
              TextFormField(
                controller: _endDateController,
                readOnly: true,
                onTap: _selectDate,
                decoration: _inputDecoration('Ngày thanh lý hợp đồng *').copyWith(
                  suffixIcon: const Icon(Icons.calendar_today_rounded, color: Color(0xFF1C1917)),
                ),
                style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                validator: (val) => val == null || val.trim().isEmpty ? 'Vui lòng chọn ngày thanh lý' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _deductionController,
                keyboardType: TextInputType.number,
                decoration: _inputDecoration('Số tiền cấn trừ cọc (nếu có) *'),
                style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                validator: (val) {
                  if (val == null || val.trim().isEmpty) return 'Vui lòng nhập số tiền cấn trừ';
                  if (double.tryParse(val.trim()) == null) return 'Số tiền không hợp lệ';
                  return null;
                },
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<int>(
                value: _paymentMethod,
                decoration: _inputDecoration('Phương thức hoàn trả cọc *'),
                items: const [
                  DropdownMenuItem<int>(value: 2, child: Text('Chuyển khoản')),
                  DropdownMenuItem<int>(value: 1, child: Text('Tiền mặt')),
                ],
                onChanged: (val) {
                  if (val != null) {
                    setState(() {
                      _paymentMethod = val;
                    });
                  }
                },
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _noteController,
                maxLines: 2,
                decoration: _inputDecoration('Ghi chú thanh lý'),
                style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
              ),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: _isSaving ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.redAccent,
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: Colors.grey.shade400,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: _isSaving
                    ? const SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 3),
                      )
                    : const Text('XÁC NHẬN THANH LÝ', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () => Navigator.pop(context),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF1C1917),
                  side: const BorderSide(color: Color(0xFFE4E2D7)),
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('HỦY BỎ', style: TextStyle(fontWeight: FontWeight.bold)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
