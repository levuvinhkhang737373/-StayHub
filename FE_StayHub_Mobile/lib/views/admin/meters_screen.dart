import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/facility_controller.dart';
import '../../controllers/meter_reading_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class MetersScreen extends StatefulWidget {
  const MetersScreen({super.key});

  @override
  State<MetersScreen> createState() => _MetersScreenState();
}

class _MetersScreenState extends State<MetersScreen> {
  int? _selectedBuildingId;
  int _selectedMonth = DateTime.now().month;
  int _selectedYear = DateTime.now().year;

  final List<int> _months = List.generate(12, (index) => index + 1);
  final List<int> _years = List.generate(5, (index) => DateTime.now().year - 2 + index);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initData();
    });
  }

  Future<void> _initData() async {
    final facilityCtrl = context.read<FacilityController>();
    await facilityCtrl.fetchBuildings();
    if (facilityCtrl.buildings.isNotEmpty) {
      setState(() {
        _selectedBuildingId = facilityCtrl.buildings.first.id;
      });
      _fetchReadings();
    }
  }

  Future<void> _fetchReadings() async {
    if (_selectedBuildingId == null) return;
    await context.read<MeterReadingController>().fetchMeterReadings(
          buildingId: _selectedBuildingId!,
          month: _selectedMonth,
          year: _selectedYear,
        );
  }

  String _formatCurrency(double value) {
    return '${value.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]}.')} đ';
  }

  void _openReadingModal(RoomReading room, double electricPrice, double waterPrice) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return _ReadingDialog(
          room: room,
          month: _selectedMonth,
          year: _selectedYear,
          electricPrice: electricPrice,
          waterPrice: waterPrice,
          onSaveSuccess: () {
            _fetchReadings();
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final facilityCtrl = context.watch<FacilityController>();
    final meterReadingCtrl = context.watch<MeterReadingController>();

    final buildings = facilityCtrl.buildings;
    final rooms = meterReadingCtrl.rooms;
    final servicePrices = meterReadingCtrl.servicePrices;

    // Find prices
    final electricPriceRecord = servicePrices.firstWhere(
      (p) => p.slug.contains('electric') || p.slug.contains('dien'),
      orElse: () => ServicePriceInit(serviceId: 0, name: 'Điện', slug: 'electric', price: 0, unitName: 'kWh'),
    );
    final waterPriceRecord = servicePrices.firstWhere(
      (p) => p.slug.contains('water') || p.slug.contains('nuoc'),
      orElse: () => ServicePriceInit(serviceId: 0, name: 'Nước', slug: 'water', price: 0, unitName: 'm³'),
    );

    final electricPrice = electricPriceRecord.price;
    final waterPrice = waterPriceRecord.price;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text(
          'Chốt số Điện nước',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          Column(
            children: [
              // Filters Section
              Container(
                margin: const EdgeInsets.all(16),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: const Color(0xFFE4E2D7)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.02),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Building Select
                    DropdownButtonFormField<int>(
                      value: _selectedBuildingId,
                      decoration: InputDecoration(
                        labelText: 'Chọn tòa nhà',
                        prefixIcon: const Icon(Icons.apartment, color: Color(0xFF1C1917)),
                        filled: true,
                        fillColor: const Color(0xFFF9F8F6),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                        ),
                        focusedBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: const BorderSide(color: Color(0xFF1C1917)),
                        ),
                      ),
                      items: buildings.map((b) {
                        return DropdownMenuItem<int>(
                          value: b.id,
                          child: Text(b.name, overflow: TextOverflow.ellipsis),
                        );
                      }).toList(),
                      onChanged: _onBuildingChanged,
                    ),
                    const SizedBox(height: 12),
                    // Month & Year Select + Refresh
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<int>(
                            value: _selectedMonth,
                            decoration: InputDecoration(
                              labelText: 'Tháng',
                              filled: true,
                              fillColor: const Color(0xFFF9F8F6),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(color: Color(0xFF1C1917)),
                              ),
                            ),
                            items: _months.map((m) {
                              return DropdownMenuItem<int>(
                                value: m,
                                child: Text('Tháng $m'),
                              );
                            }).toList(),
                            onChanged: _onMonthChanged,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: DropdownButtonFormField<int>(
                            value: _selectedYear,
                            decoration: InputDecoration(
                              labelText: 'Năm',
                              filled: true,
                              fillColor: const Color(0xFFF9F8F6),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(color: Color(0xFFE4E2D7)),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(color: Color(0xFF1C1917)),
                              ),
                            ),
                            items: _years.map((y) {
                              return DropdownMenuItem<int>(
                                value: y,
                                child: Text('Năm $y'),
                              );
                            }).toList(),
                            onChanged: _onYearChanged,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Container(
                          height: 54,
                          width: 54,
                          decoration: BoxDecoration(
                            color: const Color(0xFF1C1917),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: IconButton(
                            icon: const Icon(Icons.refresh, color: Colors.white),
                            onPressed: _fetchReadings,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              // Pricing Alert Area
              if (_selectedBuildingId != null && servicePrices.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFFDF5),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFFF3C56B).withValues(alpha: 0.35)),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.bolt, color: Colors.amber, size: 20),
                            const SizedBox(width: 4),
                            Text(
                              'Điện: ${_formatCurrency(electricPrice)}/${electricPriceRecord.unitName}',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF8A4F18)),
                            ),
                          ],
                        ),
                        Row(
                          children: [
                            const Icon(Icons.water_drop, color: Colors.blue, size: 20),
                            const SizedBox(width: 4),
                            Text(
                              'Nước: ${_formatCurrency(waterPrice)}/${waterPriceRecord.unitName}',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.cyan),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),

              // List of Rooms
              Expanded(
                child: meterReadingCtrl.isLoading
                    ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
                    : rooms.isEmpty
                        ? Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                const Icon(Icons.meeting_room_outlined, size: 64, color: Colors.grey),
                                const SizedBox(height: 16),
                                Text(
                                  _selectedBuildingId == null ? 'Vui lòng chọn tòa nhà' : 'Không có phòng nào trong kỳ chốt này',
                                  style: const TextStyle(color: Colors.grey, fontSize: 14),
                                ),
                              ],
                            ),
                          )
                        : ListView.builder(
                            padding: const EdgeInsets.all(16),
                            itemCount: rooms.length,
                            itemBuilder: (context, index) {
                              final room = rooms[index];
                              return _buildRoomCard(room, electricPrice, waterPrice);
                            },
                          ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _onBuildingChanged(int? buildingId) {
    if (buildingId != null) {
      setState(() {
        _selectedBuildingId = buildingId;
      });
      _fetchReadings();
    }
  }

  void _onMonthChanged(int? month) {
    if (month != null) {
      setState(() {
        _selectedMonth = month;
      });
      _fetchReadings();
    }
  }

  void _onYearChanged(int? year) {
    if (year != null) {
      setState(() {
        _selectedYear = year;
      });
      _fetchReadings();
    }
  }

  Widget _buildRoomCard(RoomReading room, double electricPrice, double waterPrice) {
    final elec = room.meters.firstWhere(
      (m) => m.meterType == 1,
      orElse: () => MeterDeviceReading(id: 0, meterType: 1, serviceId: 0, serviceName: 'Điện', previousReading: 0),
    );
    final water = room.meters.firstWhere(
      (m) => m.meterType == 2,
      orElse: () => MeterDeviceReading(id: 0, meterType: 2, serviceId: 0, serviceName: 'Nước', previousReading: 0),
    );

    final isChotElec = elec.id != 0 && elec.existingReading != null;
    final isChotWater = water.id != 0 && water.existingReading != null;

    final elecCost = isChotElec ? elec.existingReading!.consumption * electricPrice : 0.0;
    final waterCost = isChotWater ? water.existingReading!.consumption * waterPrice : 0.0;
    final totalUtilityCost = elecCost + waterCost;

    final hasMeters = elec.id != 0 || water.id != 0;

    final now = DateTime.now();
    final isPastMonth = _selectedYear < now.year || (_selectedYear == now.year && _selectedMonth < now.month);

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFE4E2D7)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Room Info Row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Phòng ${room.roomNumber}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    room.tenantName != null ? 'Khách thuê: ${room.tenantName}' : 'Phòng trống',
                    style: const TextStyle(fontSize: 12, color: Colors.grey, fontStyle: FontStyle.italic),
                  ),
                ],
              ),
              if (isChotElec && isChotWater)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.green.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.green.withValues(alpha: 0.3)),
                  ),
                  child: const Text(
                    'ĐÃ CHỐT',
                    style: TextStyle(color: Colors.green, fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                )
              else
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.orange.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.orange.withValues(alpha: 0.3)),
                  ),
                  child: const Text(
                    'CHƯA XONG',
                    style: TextStyle(color: Colors.orange, fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
            ],
          ),
          const Divider(height: 24, color: Color(0xFFE4E2D7)),

          // Electric Details
          if (elec.id != 0) ...[
            Row(
              children: [
                const Icon(Icons.bolt, color: Colors.amber, size: 18),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Đồng hồ Điện (Mã: ${elec.meterCode ?? "#${elec.id}"})',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Cũ: ${elec.previousReading} | Mới: ${isChotElec ? elec.existingReading!.currentReading : "-"} | Dùng: ${isChotElec ? elec.existingReading!.consumption : "0"} kWh',
                        style: const TextStyle(fontSize: 11, color: Colors.grey),
                      ),
                    ],
                  ),
                ),
                if (isChotElec)
                  Text(
                    _formatCurrency(elecCost),
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF8A4F18)),
                  ),
              ],
            ),
            const SizedBox(height: 12),
          ],

          // Water Details
          if (water.id != 0) ...[
            Row(
              children: [
                const Icon(Icons.water_drop, color: Colors.blue, size: 18),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Đồng hồ Nước (Mã: ${water.meterCode ?? "#${water.id}"})',
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Cũ: ${water.previousReading} | Mới: ${isChotWater ? water.existingReading!.currentReading : "-"} | Dùng: ${isChotWater ? water.existingReading!.consumption : "0"} m³',
                        style: const TextStyle(fontSize: 11, color: Colors.grey),
                      ),
                    ],
                  ),
                ),
                if (isChotWater)
                  Text(
                    _formatCurrency(waterCost),
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.cyan),
                  ),
              ],
            ),
            const SizedBox(height: 12),
          ],

          if (!hasMeters) ...[
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text(
                'Không có công tơ nào trong phòng này',
                style: TextStyle(fontStyle: FontStyle.italic, color: Colors.grey, fontSize: 12),
              ),
            ),
          ],

          // Cost Summary and Action Button Row
          const Divider(height: 16, color: Color(0xFFE4E2D7)),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('TỔNG DỰ KIẾN', style: TextStyle(fontSize: 10, color: Colors.grey, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 2),
                  Text(
                    _formatCurrency(totalUtilityCost),
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF1C1917)),
                  ),
                ],
              ),
              ElevatedButton.icon(
                icon: Icon(
                  isChotElec && isChotWater ? Icons.edit_note : Icons.add_chart,
                  size: 16,
                ),
                label: Text(
                  isChotElec && isChotWater ? 'Sửa chỉ số' : 'Chốt số',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1C1917),
                  foregroundColor: Colors.white,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: (hasMeters && !isPastMonth) ? () => _openReadingModal(room, electricPrice, waterPrice) : null,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ReadingDialog extends StatefulWidget {
  final RoomReading room;
  final int month;
  final int year;
  final double electricPrice;
  final double waterPrice;
  final VoidCallback onSaveSuccess;

  const _ReadingDialog({
    required this.room,
    required this.month,
    required this.year,
    required this.electricPrice,
    required this.waterPrice,
    required this.onSaveSuccess,
  });

  @override
  State<_ReadingDialog> createState() => _ReadingDialogState();
}

class _ReadingDialogState extends State<_ReadingDialog> {
  final _formKey = GlobalKey<FormState>();
  final _elecController = TextEditingController();
  final _waterController = TextEditingController();
  final _noteController = TextEditingController();

  DateTime _readingDate = DateTime.now();
  bool _isSaving = false;
  String? _errorMessage;

  MeterDeviceReading? _elecMeter;
  MeterDeviceReading? _waterMeter;

  double _elecUsage = 0;
  double _waterUsage = 0;

  @override
  void initState() {
    super.initState();

    _elecMeter = widget.room.meters.firstWhere(
      (m) => m.meterType == 1,
      orElse: () => MeterDeviceReading(id: 0, meterType: 1, serviceId: 0, serviceName: 'Điện', previousReading: 0),
    );
    _waterMeter = widget.room.meters.firstWhere(
      (m) => m.meterType == 2,
      orElse: () => MeterDeviceReading(id: 0, meterType: 2, serviceId: 0, serviceName: 'Nước', previousReading: 0),
    );

    if (_elecMeter?.id != 0 && _elecMeter?.existingReading != null) {
      _elecController.text = _elecMeter!.existingReading!.currentReading.toString();
      _elecUsage = _elecMeter!.existingReading!.currentReading - _elecMeter!.previousReading;
      if (_elecMeter!.existingReading!.note != null) {
        _noteController.text = _elecMeter!.existingReading!.note!;
      }
      if (_elecMeter!.existingReading!.readingDate != null) {
        try {
          _readingDate = DateTime.parse(_elecMeter!.existingReading!.readingDate!);
        } catch (_) {}
      }
    }

    if (_waterMeter?.id != 0 && _waterMeter?.existingReading != null) {
      _waterController.text = _waterMeter!.existingReading!.currentReading.toString();
      _waterUsage = _waterMeter!.existingReading!.currentReading - _waterMeter!.previousReading;
      if (_waterMeter!.existingReading!.note != null) {
        _noteController.text = _waterMeter!.existingReading!.note!;
      }
      if (_waterMeter!.existingReading!.readingDate != null) {
        try {
          _readingDate = DateTime.parse(_waterMeter!.existingReading!.readingDate!);
        } catch (_) {}
      }
    }

    _elecController.addListener(_calculateElecUsage);
    _waterController.addListener(_calculateWaterUsage);
  }

  void _calculateElecUsage() {
    final val = double.tryParse(_elecController.text);
    if (val != null && _elecMeter != null && val >= _elecMeter!.previousReading) {
      setState(() {
        _elecUsage = val - _elecMeter!.previousReading;
      });
    } else {
      setState(() {
        _elecUsage = 0;
      });
    }
  }

  void _calculateWaterUsage() {
    final val = double.tryParse(_waterController.text);
    if (val != null && _waterMeter != null && val >= _waterMeter!.previousReading) {
      setState(() {
        _waterUsage = val - _waterMeter!.previousReading;
      });
    } else {
      setState(() {
        _waterUsage = 0;
      });
    }
  }

  @override
  void dispose() {
    _elecController.dispose();
    _waterController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  String _formatDate(DateTime date) {
    return '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  String _formatCurrency(double value) {
    return '${value.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]}.')} đ';
  }

  Future<void> _selectDate() async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _readingDate,
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
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
    if (picked != null && picked != _readingDate) {
      setState(() {
        _readingDate = picked;
      });
    }
  }

  Future<void> _handleSave() async {
    if (!_formKey.currentState!.validate()) return;

    final hasAnyInput = (_elecMeter?.id != 0 && _elecController.text.trim().isNotEmpty) ||
        (_waterMeter?.id != 0 && _waterController.text.trim().isNotEmpty);

    if (!hasAnyInput) {
      setState(() {
        _errorMessage = 'Vui lòng nhập ít nhất một chỉ số mới.';
      });
      return;
    }

    setState(() {
      _isSaving = true;
      _errorMessage = null;
    });

    final formattedDate = _formatDate(_readingDate);
    final note = _noteController.text.isEmpty ? null : _noteController.text;
    final ctrl = context.read<MeterReadingController>();

    try {
      bool success = true;

      if (_elecMeter?.id != 0 && _elecController.text.trim().isNotEmpty) {
        final elecVal = double.parse(_elecController.text);
        final elecOk = await ctrl.saveMeterReading(
          meterDeviceId: _elecMeter!.id,
          month: widget.month,
          year: widget.year,
          currentReading: elecVal,
          readingDate: formattedDate,
          note: note,
        );
        if (!elecOk) success = false;
      }

      if (_waterMeter?.id != 0 && _waterController.text.trim().isNotEmpty) {
        final waterVal = double.parse(_waterController.text);
        final waterOk = await ctrl.saveMeterReading(
          meterDeviceId: _waterMeter!.id,
          month: widget.month,
          year: widget.year,
          currentReading: waterVal,
          readingDate: formattedDate,
          note: note,
        );
        if (!waterOk) success = false;
      }

      if (success) {
        widget.onSaveSuccess();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Chốt chỉ số điện nước thành công!'), backgroundColor: Colors.green),
          );
          Navigator.pop(context);
        }
      } else {
        setState(() {
          _errorMessage = ctrl.errorMessage ?? 'Không thể lưu chốt số điện nước.';
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Lỗi lưu thông tin: $e';
      });
    } finally {
      setState(() {
        _isSaving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final double totalCost = (_elecUsage * widget.electricPrice) + (_waterUsage * widget.waterPrice);

    return AlertDialog(
      backgroundColor: const Color(0xFFFFFDF9),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      titlePadding: EdgeInsets.zero,
      contentPadding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
      actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      title: Container(
        padding: const EdgeInsets.all(20),
        decoration: const BoxDecoration(
          color: Color(0xFF1C1917),
          borderRadius: BorderRadius.only(topLeft: Radius.circular(24), topRight: Radius.circular(24)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Utility Record',
              style: TextStyle(color: Color(0xFFEAB308), fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 1.5),
            ),
            const SizedBox(height: 4),
            Text(
              'Chốt chỉ số - Phòng ${widget.room.roomNumber}',
              style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            Text(
              'Khách: ${widget.room.tenantName ?? "Trống"}',
              style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontSize: 12),
            ),
          ],
        ),
      ),
      content: SizedBox(
        width: 340,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_errorMessage != null)
                  Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.red.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red.withValues(alpha: 0.3)),
                    ),
                    child: Text(
                      _errorMessage!,
                      style: const TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ),

                // Date Picker field
                InkWell(
                  onTap: _isSaving ? null : _selectDate,
                  borderRadius: BorderRadius.circular(12),
                  child: InputDecorator(
                    decoration: InputDecoration(
                      labelText: 'Ngày ghi nhận số liệu',
                      prefixIcon: const Icon(Icons.calendar_today, size: 18),
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: Text(
                      _formatDate(_readingDate),
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                  ),
                ),
                const SizedBox(height: 16),

                // Electric Meter Inputs
                if (_elecMeter?.id != 0) ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.amber.withValues(alpha: 0.05),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: Colors.amber.withValues(alpha: 0.2)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Row(
                          children: [
                            Icon(Icons.bolt, color: Colors.amber, size: 18),
                            SizedBox(width: 6),
                            Text(
                              'Đồng hồ Điện',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Color(0xFF8A4F18)),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                initialValue: _elecMeter!.previousReading.toString(),
                                readOnly: true,
                                decoration: const InputDecoration(
                                  labelText: 'Chỉ số cũ',
                                  filled: true,
                                  fillColor: Color(0xFFF0EFEA),
                                  border: OutlineInputBorder(),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextFormField(
                                controller: _elecController,
                                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                enabled: !_isSaving,
                                decoration: const InputDecoration(
                                  labelText: 'Chỉ số mới',
                                  fillColor: Colors.white,
                                  filled: true,
                                  border: OutlineInputBorder(),
                                ),
                                validator: (val) {
                                  if (val == null || val.trim().isEmpty) return null;
                                  final numVal = double.tryParse(val);
                                  if (numVal == null) return 'Phải là số';
                                  if (numVal < _elecMeter!.previousReading) {
                                    return '>= ${_elecMeter!.previousReading}';
                                  }
                                  return null;
                                },
                              ),
                            ),
                          ],
                        ),
                        if (_elecUsage > 0) ...[
                          const SizedBox(height: 8),
                          Text(
                            'Sử dụng: ${_elecUsage.toStringAsFixed(1)} kWh (${_formatCurrency(_elecUsage * widget.electricPrice)})',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF8A4F18)),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                ],

                // Water Meter Inputs
                if (_waterMeter?.id != 0) ...[
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.blue.withValues(alpha: 0.05),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: Colors.blue.withValues(alpha: 0.2)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Row(
                          children: [
                            Icon(Icons.water_drop, color: Colors.blue, size: 18),
                            SizedBox(width: 6),
                            Text(
                              'Đồng hồ Nước',
                              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.cyan),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                initialValue: _waterMeter!.previousReading.toString(),
                                readOnly: true,
                                decoration: const InputDecoration(
                                  labelText: 'Chỉ số cũ',
                                  filled: true,
                                  fillColor: Color(0xFFF0EFEA),
                                  border: OutlineInputBorder(),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextFormField(
                                controller: _waterController,
                                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                enabled: !_isSaving,
                                decoration: const InputDecoration(
                                  labelText: 'Chỉ số mới',
                                  fillColor: Colors.white,
                                  filled: true,
                                  border: OutlineInputBorder(),
                                ),
                                validator: (val) {
                                  if (val == null || val.trim().isEmpty) return null;
                                  final numVal = double.tryParse(val);
                                  if (numVal == null) return 'Phải là số';
                                  if (numVal < _waterMeter!.previousReading) {
                                    return '>= ${_waterMeter!.previousReading}';
                                  }
                                  return null;
                                },
                              ),
                            ),
                          ],
                        ),
                        if (_waterUsage > 0) ...[
                          const SizedBox(height: 8),
                          Text(
                            'Sử dụng: ${_waterUsage.toStringAsFixed(1)} m³ (${_formatCurrency(_waterUsage * widget.waterPrice)})',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Colors.cyan),
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                ],

                // Note
                TextFormField(
                  controller: _noteController,
                  maxLines: 2,
                  enabled: !_isSaving,
                  decoration: InputDecoration(
                    labelText: 'Ghi chú',
                    hintText: 'Thêm ghi chú của kỳ này...',
                    filled: true,
                    fillColor: Colors.white,
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(height: 16),

                // Cost Summary
                if (totalCost > 0)
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1C1917).withValues(alpha: 0.05),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFF1C1917).withValues(alpha: 0.1)),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'DỰ KIẾN TIỀN KỲ NÀY:',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 10, color: Colors.grey),
                        ),
                        Text(
                          _formatCurrency(totalCost),
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1C1917)),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _isSaving ? null : () => Navigator.pop(context),
          child: const Text('HỦY', style: TextStyle(color: Colors.grey, fontWeight: FontWeight.bold)),
        ),
        ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF1C1917),
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
          onPressed: _isSaving ? null : _handleSave,
          child: _isSaving
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                )
              : const Text('LƯU CHỐT SỐ', style: TextStyle(fontWeight: FontWeight.bold)),
        ),
      ],
    );
  }
}
