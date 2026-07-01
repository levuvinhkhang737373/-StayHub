import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/meter_reading_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter

class TenantUtilityScreen extends StatefulWidget {
  const TenantUtilityScreen({super.key});

  @override
  State<TenantUtilityScreen> createState() => _TenantUtilityScreenState();
}

class _TenantUtilityScreenState extends State<TenantUtilityScreen> {
  bool _showAllElectricPrices = false;
  bool _showAllWaterPrices = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final controller = context.read<MeterReadingController>();
      controller.fetchTenantUtilityPriceHistory();
      controller.fetchTenantUtilityReadings();
    });
  }

  String formatCurrency(double value) {
    final str = value.toStringAsFixed(0);
    final regExp = RegExp(r'\B(?=(\d{3})+(?!\d))');
    return str.replaceAll(regExp, '.');
  }

  @override
  Widget build(BuildContext context) {
    final controller = context.watch<MeterReadingController>();
    final readings = controller.tenantReadings;

    if (controller.isLoading && readings.isEmpty) {
      return const Scaffold(
        backgroundColor: Color(0xFFF7F6F0),
        body: Center(child: CircularProgressIndicator(valueColor: AlwaysStoppedAnimation<Color>(Color(0xFFEAB308)))),
      );
    }

    final List<Map<String, dynamic>> electricHistory = readings
        .where((r) => r.meterType == 1)
        .map((r) => {
              'month': r.billingMonth,
              'year': r.billingYear,
              'oldValue': r.previousReading,
              'newValue': r.currentReading,
              'consumption': r.consumption,
              'recordedAt': r.readingDate ?? '',
              'imageUrl': r.imageUrl ?? 'https://images.unsplash.com/photo-1558346490-a72e53ae2d4f?auto=format&fit=crop&q=80&w=400',
            })
        .toList();

    final List<Map<String, dynamic>> waterHistory = readings
        .where((r) => r.meterType == 2)
        .map((r) => {
              'month': r.billingMonth,
              'year': r.billingYear,
              'oldValue': r.previousReading,
              'newValue': r.currentReading,
              'consumption': r.consumption,
              'recordedAt': r.readingDate ?? '',
              'imageUrl': r.imageUrl ?? 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?auto=format&fit=crop&q=80&w=400',
            })
        .toList();

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F6F0),
        appBar: AppBar(
          title: const Text('Chỉ số Điện Nước', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
          backgroundColor: const Color(0xFF1C1917),
          bottom: const TabBar(
            indicatorColor: Color(0xFFEAB308),
            labelColor: Color(0xFFEAB308),
            unselectedLabelColor: Colors.grey,
            tabs: [
              Tab(icon: Icon(Icons.bolt, size: 20), text: 'Điện tiêu thụ'),
              Tab(icon: Icon(Icons.water_drop, size: 20), text: 'Nước tiêu thụ'),
            ],
          ),
        ),
        body: Stack(
          children: [
            Positioned.fill(child: CustomPaint(painter: GridPainter())),
            TabBarView(
              children: [
                _buildUtilityTab(context, electricHistory, isElectric: true),
                _buildUtilityTab(context, waterHistory, isElectric: false),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildUtilityTab(BuildContext context, List<Map<String, dynamic>> history, {required bool isElectric}) {
    if (history.isEmpty) {
      return const Center(child: Text('Không tìm thấy dữ liệu chỉ số.', style: TextStyle(color: Colors.grey)));
    }

    final latest = history.first;
    final unit = isElectric ? 'kWh' : 'm³';
    final rate = isElectric ? 3500 : 15000;
    final themeColor = isElectric ? Colors.amber : Colors.blue;

    final controller = context.watch<MeterReadingController>();
    final priceHistory = controller.effectivePriceHistory;

    // Filter price history for current service type
    final filteredPrices = priceHistory.where((p) {
      if (isElectric) {
        return p.serviceName.contains('Điện') || p.serviceName.contains('electric') || p.serviceId == 1;
      } else {
        return p.serviceName.contains('Nước') || p.serviceName.contains('water') || p.serviceId == 2;
      }
    }).toList();

    // Limit to latest 5 points for a clean trend graph
    final graphPrices = filteredPrices.take(5).toList().reversed.toList();
    final latestPrice = filteredPrices.isNotEmpty ? filteredPrices.first.price : rate;

    final showAll = isElectric ? _showAllElectricPrices : _showAllWaterPrices;
    final displayPrices = showAll ? filteredPrices : filteredPrices.take(3).toList();

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Current Month Dashboard Card
          Container(
            padding: const EdgeInsets.all(20),
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
              children: [
                Text(
                  'CHỈ SỐ THÁNG ${latest['month']}/${latest['year']}',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey, letterSpacing: 1.0),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    _buildBigCounter('Chỉ số cũ', '${latest['oldValue']}', Colors.grey),
                    Icon(Icons.arrow_forward_rounded, color: themeColor, size: 24),
                    _buildBigCounter('Chỉ số mới', '${latest['newValue']}', themeColor),
                  ],
                ),
                const Divider(height: 32, color: Color(0xFFE4E2D7)),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Tiêu thụ thực tế', style: TextStyle(color: Colors.grey, fontSize: 12)),
                        const SizedBox(height: 4),
                        Text(
                          '+${latest['consumption']} $unit',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: themeColor),
                        ),
                      ],
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text('Đơn giá', style: TextStyle(color: Colors.grey, fontSize: 12)),
                        const SizedBox(height: 4),
                        Text(
                          '${formatCurrency(latestPrice.toDouble())}đ/$unit',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Price Change Trend Graph Card
          Container(
            padding: const EdgeInsets.all(20),
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
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      isElectric ? 'XU HƯỚNG ĐƠN GIÁ ĐIỆN' : 'XU HƯỚNG ĐƠN GIÁ NƯỚC',
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12, color: Colors.grey, letterSpacing: 1.0),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: themeColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        'Hôm nay: ${formatCurrency(latestPrice.toDouble())}đ',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: themeColor),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 24),
                SizedBox(
                  height: 150,
                  child: graphPrices.isEmpty
                      ? Center(
                          child: controller.isLoading
                              ? const CircularProgressIndicator()
                              : const Text('Không có dữ liệu xu hướng.', style: TextStyle(color: Colors.grey, fontSize: 12)),
                        )
                      : CustomPaint(
                          painter: PriceLineChartPainter(
                            records: graphPrices,
                            lineColor: themeColor,
                          ),
                        ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Price History List Section
          const Text(
            'LỊCH SỬ THAY ĐỔI ĐƠN GIÁ',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
          ),
          const SizedBox(height: 12),
          ListView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: displayPrices.length,
            itemBuilder: (context, index) {
              final record = displayPrices[index];
              final isCurrent = record.status == 1; // STATUS_ACTIVE

              return Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: isCurrent ? themeColor.withOpacity(0.3) : const Color(0xFFE4E2D7)),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${formatCurrency(record.price)}đ / $unit',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                            color: isCurrent ? themeColor : const Color(0xFF1C1917),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          record.effectiveTo != null
                              ? 'Kỳ: ${record.effectiveFrom} → ${record.effectiveTo}'
                              : 'Áp dụng từ: ${record.effectiveFrom}',
                          style: const TextStyle(fontSize: 11, color: Colors.grey),
                        ),
                      ],
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: isCurrent ? Colors.green.withOpacity(0.1) : Colors.grey.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        isCurrent ? 'Đang áp dụng' : 'Hết hiệu lực',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: isCurrent ? Colors.green : Colors.grey[700],
                        ),
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
          if (filteredPrices.length > 3) ...[
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.center,
              child: TextButton.icon(
                onPressed: () {
                  setState(() {
                    if (isElectric) {
                      _showAllElectricPrices = !_showAllElectricPrices;
                    } else {
                      _showAllWaterPrices = !_showAllWaterPrices;
                    }
                  });
                },
                icon: Icon(
                  showAll ? Icons.keyboard_arrow_up : Icons.keyboard_arrow_down,
                  size: 18,
                  color: themeColor,
                ),
                label: Text(
                  showAll ? 'Thu gọn lịch sử' : 'Xem thêm lịch sử (${filteredPrices.length - 3} đơn giá)',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: themeColor,
                  ),
                ),
              ),
            ),
          ],
          const SizedBox(height: 24),

          // Consumption History List Section
          const Text(
            'LỊCH SỬ TIÊU THỤ CÁC THÁNG',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF78716C), letterSpacing: 1.0),
          ),
          const SizedBox(height: 12),
          ListView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: history.length,
            itemBuilder: (context, index) {
              final record = history[index];
              final cost = record['consumption'] * rate;

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
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Tháng ${record['month']}/${record['year']}',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF1C1917)),
                          ),
                          Text(
                            '${formatCurrency(cost.toDouble())}đ',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF1C1917)),
                          ),
                        ],
                      ),
                      const Divider(height: 20, color: Color(0xFFE4E2D7)),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Chỉ số: ${record['oldValue']} → ${record['newValue']} ($unit)', style: const TextStyle(color: Colors.grey, fontSize: 13)),
                          Text(
                            'Dùng: ${record['consumption']} $unit',
                            style: TextStyle(fontWeight: FontWeight.w600, color: themeColor, fontSize: 13),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton.icon(
                          onPressed: () => _showEvidenceDialog(context, record, isElectric),
                          icon: Icon(Icons.image_outlined, size: 16, color: themeColor),
                          label: Text(
                            'Xem ảnh minh chứng',
                            style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: themeColor),
                          ),
                          style: TextButton.styleFrom(
                            minimumSize: Size.zero,
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _buildBigCounter(String label, String value, Color color) {
    return Column(
      children: [
        Text(label, style: const TextStyle(color: Colors.grey, fontSize: 11)),
        const SizedBox(height: 6),
        Text(
          value,
          style: TextStyle(fontSize: 28, fontWeight: FontWeight.w900, color: color),
        ),
      ],
    );
  }

  void _showEvidenceDialog(BuildContext context, Map<String, dynamic> record, bool isElectric) {
    showDialog(
      context: context,
      builder: (context) {
        return Dialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AppBar(
                title: Text('Minh chứng Tháng ${record['month']}/${record['year']}'),
                backgroundColor: const Color(0xFF1C1917),
                automaticallyImplyLeading: false,
                actions: [
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
                shape: const RoundedRectangleBorder(
                  borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(20.0),
                child: Column(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Image.network(
                        record['imageUrl'],
                        height: 200,
                        width: double.infinity,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) => Container(
                          height: 200,
                          color: const Color(0xFFF9F8F6),
                          child: const Center(child: Icon(Icons.broken_image, size: 50, color: Colors.grey)),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Loại đồng hồ:', style: TextStyle(color: Colors.grey)),
                        Text(
                          isElectric ? 'Đồng hồ điện tử' : 'Đồng hồ nước',
                          style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Ngày ghi nhận:', style: TextStyle(color: Colors.grey)),
                        Text(
                          record['recordedAt'],
                          style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1C1917)),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

}

class PriceLineChartPainter extends CustomPainter {
  final List<UtilityPriceRecord> records;
  final Color lineColor;

  PriceLineChartPainter({required this.records, required this.lineColor});

  @override
  void paint(Canvas canvas, Size size) {
    if (records.isEmpty) return;

    final leftPadding = 45.0;
    final rightPadding = 15.0;
    final topPadding = 25.0;
    final bottomPadding = 25.0;

    final plotWidth = size.width - leftPadding - rightPadding;
    final plotHeight = size.height - topPadding - bottomPadding;

    final prices = records.map((e) => e.price).toList();
    double maxVal = prices.fold(0.0, (m, e) => e > m ? e : m);
    double minVal = prices.fold(maxVal, (m, e) => e < m ? e : m);

    if (maxVal == minVal) {
      maxVal += maxVal * 0.2 + 1000;
      minVal = (minVal - minVal * 0.2 - 1000).clamp(0, double.infinity);
    } else {
      double diff = maxVal - minVal;
      maxVal += diff * 0.25;
      minVal = (minVal - diff * 0.25).clamp(0, double.infinity);
    }

    // Grid lines (Horizontal)
    final gridPaint = Paint()
      ..color = const Color(0xFFE4E2D7)
      ..strokeWidth = 1.0;

    final gridTicks = 3;
    for (int i = 0; i <= gridTicks; i++) {
      final y = topPadding + (i / gridTicks) * plotHeight;
      canvas.drawLine(Offset(leftPadding, y), Offset(size.width - rightPadding, y), gridPaint);

      // Y-axis Label
      final val = maxVal - (i / gridTicks) * (maxVal - minVal);
      final tp = TextPainter(
        text: TextSpan(
          text: '${val.toStringAsFixed(0)}đ',
          style: const TextStyle(color: Colors.grey, fontSize: 8, fontWeight: FontWeight.w500),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      tp.paint(canvas, Offset(leftPadding - tp.width - 5, y - tp.height / 2));
    }

    // Calculate point coordinates
    final points = <Offset>[];
    for (int i = 0; i < records.length; i++) {
      final record = records[i];
      final x = records.length > 1
          ? leftPadding + (i / (records.length - 1)) * plotWidth
          : leftPadding + plotWidth / 2;
      final y = topPadding + (1 - (record.price - minVal) / (maxVal - minVal)) * plotHeight;
      points.add(Offset(x, y));
    }

    // Draw area path (gradient fill)
    if (points.isNotEmpty) {
      final areaPath = Path();
      areaPath.moveTo(points.first.dx, topPadding + plotHeight);
      for (final pt in points) {
        areaPath.lineTo(pt.dx, pt.dy);
      }
      areaPath.lineTo(points.last.dx, topPadding + plotHeight);
      areaPath.close();

      final fillPaint = Paint()
        ..shader = LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            lineColor.withOpacity(0.35),
            lineColor.withOpacity(0.0),
          ],
        ).createShader(Rect.fromLTRB(leftPadding, topPadding, size.width - rightPadding, topPadding + plotHeight));
      canvas.drawPath(areaPath, fillPaint);
    }

    // Draw line path
    final linePaint = Paint()
      ..color = lineColor
      ..strokeWidth = 3.0
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    final linePath = Path();
    if (points.isNotEmpty) {
      linePath.moveTo(points.first.dx, points.first.dy);
      for (int i = 1; i < points.length; i++) {
        linePath.lineTo(points[i].dx, points[i].dy);
      }
      canvas.drawPath(linePath, linePaint);
    }

    // Draw points & labels
    final pointPaint = Paint()
      ..color = lineColor
      ..style = PaintingStyle.fill;
    final whitePaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.fill;

    for (int i = 0; i < points.length; i++) {
      final pt = points[i];
      final record = records[i];

      // Draw point circle
      canvas.drawCircle(pt, 5.0, pointPaint);
      canvas.drawCircle(pt, 2.5, whitePaint);

      // Price Label above point
      final priceTp = TextPainter(
        text: TextSpan(
          text: '${record.price.toStringAsFixed(0)}đ',
          style: TextStyle(color: lineColor, fontSize: 9, fontWeight: FontWeight.bold),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      priceTp.paint(canvas, Offset(pt.dx - priceTp.width / 2, pt.dy - priceTp.height - 4));

      // X-axis Label below point (effective from date)
      // Extract MM/YY from YYYY-MM-DD
      String dateStr = '';
      if (record.effectiveFrom.length >= 7) {
        dateStr = record.effectiveFrom.substring(5, 7) + '/' + record.effectiveFrom.substring(2, 4);
      } else {
        dateStr = record.effectiveFrom;
      }
      final dateTp = TextPainter(
        text: TextSpan(
          text: dateStr,
          style: const TextStyle(color: Colors.grey, fontSize: 8, fontWeight: FontWeight.w500),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      dateTp.paint(canvas, Offset(pt.dx - dateTp.width / 2, topPadding + plotHeight + 6));
    }
  }

  @override
  bool shouldRepaint(covariant PriceLineChartPainter oldDelegate) {
    return oldDelegate.records != oldDelegate.records || oldDelegate.lineColor != lineColor;
  }
}
