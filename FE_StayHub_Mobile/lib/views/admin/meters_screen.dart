import 'package:flutter/material.dart';
import '../auth/login_screen.dart'; // import GridPainter

class MetersScreen extends StatefulWidget {
  const MetersScreen({super.key});

  @override
  State<MetersScreen> createState() => _MetersScreenState();
}

class _MetersScreenState extends State<MetersScreen> {
  final _formKey = GlobalKey<FormState>();
  final _electricityController = TextEditingController();
  final _waterController = TextEditingController();
  
  String _selectedRoom = '101';
  int _oldElectricity = 1240;
  int _oldWater = 342;
  bool _hasEvidencePhoto = false;

  final Map<String, List<int>> _roomOldValues = {
    '101': [1240, 342],
    '102': [890, 215],
    '103': [450, 98],
    '201': [2310, 680],
    '202': [115, 34],
  };

  void _onRoomChanged(String? room) {
    if (room != null && _roomOldValues.containsKey(room)) {
      setState(() {
        _selectedRoom = room;
        _oldElectricity = _roomOldValues[room]![0];
        _oldWater = _roomOldValues[room]![1];
        _electricityController.clear();
        _waterController.clear();
        _hasEvidencePhoto = false;
      });
    }
  }

  Future<void> _simulateCapturePhoto() async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return const AlertDialog(
          content: Row(
            children: [
              CircularProgressIndicator(color: Color(0xFF1C1917)),
              SizedBox(width: 16),
              Text('Đang mở camera giả lập...'),
            ],
          ),
        );
      },
    );

    await Future.delayed(const Duration(milliseconds: 1200));
    if (mounted) Navigator.pop(context);

    setState(() {
      _hasEvidencePhoto = true;
    });

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Đã chụp ảnh minh chứng thành công!'), backgroundColor: Colors.green),
      );
    }
  }

  void _handleSave() {
    if (!_formKey.currentState!.validate()) return;
    if (!_hasEvidencePhoto) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vui lòng chụp ảnh minh chứng chỉ số!'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Ghi nhận chỉ số Phòng $_selectedRoom thành công!'),
        backgroundColor: Colors.green,
      ),
    );

    setState(() {
      // Update local old values mock
      _roomOldValues[_selectedRoom] = [
        int.parse(_electricityController.text),
        int.parse(_waterController.text),
      ];
      _onRoomChanged(_selectedRoom);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('Ghi chỉ số Điện nước', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          SingleChildScrollView(
            padding: const EdgeInsets.all(24.0),
            child: Container(
              padding: const EdgeInsets.all(24),
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
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'GHI NHẬN CHỈ SỐ TIÊU THỤ',
                      style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Color(0xFF1C1917), letterSpacing: 0.5),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 24),

                    // Room Selection Dropdown
                    DropdownButtonFormField<String>(
                      initialValue: _selectedRoom,
                      decoration: InputDecoration(
                        labelText: 'Chọn phòng ghi nhận',
                        prefixIcon: const Icon(Icons.meeting_room, color: Color(0xFF1C1917)),
                        filled: true,
                        fillColor: const Color(0xFFF9F8F6),
                        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE4E2D7))),
                        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1C1917))),
                      ),
                      items: _roomOldValues.keys.map((room) {
                        return DropdownMenuItem<String>(value: room, child: Text('Phòng $room'));
                      }).toList(),
                      onChanged: _onRoomChanged,
                    ),
                    const SizedBox(height: 24),

                    // Electricity Card Section
                    _buildSectionHeader('ĐIỆN TIÊU THỤ (kWh)', Icons.bolt, Colors.amber),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            initialValue: '$_oldElectricity',
                            readOnly: true,
                            decoration: InputDecoration(
                              labelText: 'Chỉ số cũ',
                              filled: true,
                              fillColor: const Color(0xFFE4E2D7).withValues(alpha: 0.3),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: TextFormField(
                            controller: _electricityController,
                            keyboardType: TextInputType.number,
                            decoration: InputDecoration(
                              labelText: 'Chỉ số mới',
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            validator: (val) {
                              if (val == null || val.isEmpty) return 'Nhập chỉ số mới';
                              final newVal = int.tryParse(val);
                              if (newVal == null) return 'Phải là số nguyên';
                              if (newVal < _oldElectricity) return 'Phải >= $_oldElectricity';
                              return null;
                            },
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),

                    // Water Card Section
                    _buildSectionHeader('NƯỚC TIÊU THỤ (m³)', Icons.water_drop, Colors.blue),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: TextFormField(
                            initialValue: '$_oldWater',
                            readOnly: true,
                            decoration: InputDecoration(
                              labelText: 'Chỉ số cũ',
                              filled: true,
                              fillColor: const Color(0xFFE4E2D7).withValues(alpha: 0.3),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: TextFormField(
                            controller: _waterController,
                            keyboardType: TextInputType.number,
                            decoration: InputDecoration(
                              labelText: 'Chỉ số mới',
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                            validator: (val) {
                              if (val == null || val.isEmpty) return 'Nhập chỉ số mới';
                              final newVal = int.tryParse(val);
                              if (newVal == null) return 'Phải là số nguyên';
                              if (newVal < _oldWater) return 'Phải >= $_oldWater';
                              return null;
                            },
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),

                    // Camera Evidence Upload Mock
                    const Text('ẢNH MINH CHỨNG CHỈ SỐ', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey)),
                    const SizedBox(height: 8),
                    InkWell(
                      onTap: _simulateCapturePhoto,
                      borderRadius: BorderRadius.circular(16),
                      child: Container(
                        height: 140,
                        decoration: BoxDecoration(
                          color: const Color(0xFFF9F8F6),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFFE4E2D7), style: BorderStyle.solid),
                        ),
                        child: _hasEvidencePhoto
                            ? Stack(
                                children: [
                                  Positioned.fill(
                                    child: ClipRRect(
                                      borderRadius: BorderRadius.circular(16),
                                      child: Image.network(
                                        'https://images.unsplash.com/photo-1558346490-a72e53ae2d4f?auto=format&fit=crop&q=80&w=400',
                                        fit: double.infinity.toString() == 'double.infinity' ? BoxFit.cover : BoxFit.fill,
                                      ),
                                    ),
                                  ),
                                  Positioned(
                                    bottom: 8,
                                    right: 8,
                                    child: Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                      decoration: BoxDecoration(color: Colors.green, borderRadius: BorderRadius.circular(8)),
                                      child: const Text('ĐÃ CHỤP', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                                    ),
                                  ),
                                ],
                              )
                            : const Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.camera_alt_outlined, size: 40, color: Colors.grey),
                                  SizedBox(height: 8),
                                  Text('Bấm để chụp ảnh mặt đồng hồ', style: TextStyle(color: Colors.grey, fontSize: 12)),
                                ],
                              ),
                      ),
                    ),
                    const SizedBox(height: 32),

                    // Submit Button
                    ElevatedButton(
                      onPressed: _handleSave,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF1C1917),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text('GHI NHẬN CHỈ SỐ', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String label, IconData icon, Color color) {
    return Row(
      children: [
        Icon(icon, color: color, size: 20),
        const SizedBox(width: 8),
        Text(label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1C1917))),
      ],
    );
  }
}
