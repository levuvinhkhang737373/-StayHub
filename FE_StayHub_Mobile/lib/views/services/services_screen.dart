import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../../config/currency_formatter.dart';
import '../../controllers/service_controller.dart';
import '../../models/service.dart' as model;
import '../auth/login_screen.dart'; // import GridPainter

class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key});

  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ServiceController>().fetchServices();
    });
  }

  void _showServiceForm([model.Service? service]) {
    final formKey = GlobalKey<FormState>();
    final nameController = TextEditingController(text: service?.name ?? '');
    final unitController = TextEditingController(text: service?.unit ?? '');
    final priceController = TextEditingController(
        text: service != null ? formatMoney(service.price ?? 0.0).replaceAll('đ', '').trim() : '');
    final descController = TextEditingController(text: service?.description ?? '');
    int status = service?.status ?? 1;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
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
                  Text(
                    service == null ? 'Thêm dịch vụ mới' : 'Chỉnh sửa dịch vụ',
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                  ),
                  const SizedBox(height: 24),
                  
                  TextFormField(
                    controller: nameController,
                    decoration: InputDecoration(
                      labelText: 'Tên dịch vụ',
                      filled: true,
                      fillColor: const Color(0xFFF9F8F6),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE4E2D7))),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1C1917))),
                    ),
                    validator: (val) => val == null || val.trim().isEmpty ? 'Nhập tên dịch vụ' : null,
                  ),
                  const SizedBox(height: 16),
                  
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: unitController,
                          decoration: InputDecoration(
                            labelText: 'Đơn vị tính',
                            filled: true,
                            fillColor: const Color(0xFFF9F8F6),
                            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE4E2D7))),
                            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1C1917))),
                          ),
                          validator: (val) => val == null || val.trim().isEmpty ? 'Nhập đơn vị' : null,
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: TextFormField(
                          controller: priceController,
                          keyboardType: TextInputType.number,
                          inputFormatters: [CurrencyInputFormatter()],
                          decoration: InputDecoration(
                            labelText: 'Đơn giá (VNĐ)',
                            filled: true,
                            fillColor: const Color(0xFFF9F8F6),
                            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE4E2D7))),
                            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1C1917))),
                          ),
                          validator: (val) {
                            if (val == null || val.isEmpty) return 'Nhập đơn giá';
                            if (double.tryParse(val.replaceAll('.', '')) == null) return 'Sai định dạng số';
                            return null;
                          },
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  
                  TextFormField(
                    controller: descController,
                    maxLines: 2,
                    decoration: InputDecoration(
                      labelText: 'Mô tả ngắn',
                      filled: true,
                      fillColor: const Color(0xFFF9F8F6),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE4E2D7))),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1C1917))),
                    ),
                  ),
                  const SizedBox(height: 16),

                  DropdownButtonFormField<int>(
                    initialValue: status,
                    decoration: InputDecoration(
                      labelText: 'Trạng thái',
                      filled: true,
                      fillColor: const Color(0xFFF9F8F6),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE4E2D7))),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1C1917))),
                    ),
                    items: const [
                      DropdownMenuItem(value: 1, child: Text('Hoạt động')),
                      DropdownMenuItem(value: 2, child: Text('Ngừng hoạt động')),
                    ],
                    onChanged: (val) {
                      if (val != null) status = val;
                    },
                  ),
                  const SizedBox(height: 24),

                  ElevatedButton(
                    onPressed: () async {
                      if (!formKey.currentState!.validate()) return;
                      
                      final controller = context.read<ServiceController>();
                      final navigator = Navigator.of(context);
                      final scaffoldMessenger = ScaffoldMessenger.of(context);
                      bool success;

                      if (service == null) {
                        success = await controller.createService(
                          name: nameController.text.trim(),
                          unit: unitController.text.trim(),
                          price: parseMoney(priceController.text),
                          status: status,
                          description: descController.text.trim(),
                        );
                      } else {
                        success = await controller.updateService(
                          id: service.id,
                          name: nameController.text.trim(),
                          unit: unitController.text.trim(),
                          price: parseMoney(priceController.text),
                          status: status,
                          description: descController.text.trim(),
                        );
                      }

                      if (success && mounted) {
                        navigator.pop();
                        scaffoldMessenger.showSnackBar(
                          const SnackBar(content: Text('Lưu dịch vụ thành công'), backgroundColor: Colors.green),
                        );
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF1C1917),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('LƯU LẠI', style: TextStyle(fontWeight: FontWeight.bold)),
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

  @override
  Widget build(BuildContext context) {
    final serviceController = context.watch<ServiceController>();
    final services = serviceController.services;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('StayHub Services', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          serviceController.isLoading
              ? const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)))
              : RefreshIndicator(
                  onRefresh: () => serviceController.fetchServices(),
                  color: const Color(0xFF1C1917),
                  child: services.isEmpty
                      ? const Center(child: Text('Không có dịch vụ nào.', style: TextStyle(color: Colors.grey)))
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: services.length,
                          itemBuilder: (context, index) {
                            final service = services[index];
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
                                child: Row(
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.all(12),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFF1C1917).withValues(alpha: 0.05),
                                        shape: BoxShape.circle,
                                      ),
                                      child: const Icon(Icons.bolt, color: Color(0xFFEAB308), size: 28),
                                    ),
                                    const SizedBox(width: 16),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            service.name,
                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            '${formatMoney(service.price ?? 0.0)} / ${service.unit}',
                                            style: const TextStyle(
                                              color: Color(0xFF1C1917),
                                              fontWeight: FontWeight.w600,
                                              fontSize: 14,
                                            ),
                                          ),
                                          if (service.description != null && service.description!.isNotEmpty) ...[
                                            const SizedBox(height: 4),
                                            Text(
                                              service.description!,
                                              style: const TextStyle(color: Colors.grey, fontSize: 12),
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                          ],
                                        ],
                                      ),
                                    ),
                                    Switch(
                                      value: service.isActive,
                                      activeThumbColor: Colors.green,
                                      activeTrackColor: Colors.green.withValues(alpha: 0.2),
                                      inactiveThumbColor: Colors.grey,
                                      onChanged: (val) {
                                        serviceController.updateServiceStatus(service.id, val ? 1 : 2);
                                      },
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.edit_outlined, color: Color(0xFF1C1917)),
                                      onPressed: () => _showServiceForm(service),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _showServiceForm(),
        backgroundColor: const Color(0xFF1C1917),
        foregroundColor: const Color(0xFFEAB308),
        child: const Icon(Icons.add),
      ),
    );
  }
}
