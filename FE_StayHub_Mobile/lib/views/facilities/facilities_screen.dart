import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../controllers/facility_controller.dart';
import '../auth/login_screen.dart'; // import GridPainter
import 'facility_form_screen.dart';

class FacilitiesScreen extends StatefulWidget {
  const FacilitiesScreen({super.key});

  @override
  State<FacilitiesScreen> createState() => _FacilitiesScreenState();
}

class _FacilitiesScreenState extends State<FacilitiesScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<FacilityController>().fetchRegions();
      context.read<FacilityController>().fetchBuildings();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F6F0),
      appBar: AppBar(
        title: const Text('StayHub Facilities', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: const Color(0xFF1C1917),
        bottom: TabBar(
          controller: _tabController,
          labelColor: const Color(0xFFEAB308),
          unselectedLabelColor: Colors.white70,
          indicatorColor: const Color(0xFFEAB308),
          tabs: const [
            Tab(icon: Icon(Icons.map), text: 'Khu vực'),
            Tab(icon: Icon(Icons.business), text: 'Tòa nhà'),
          ],
        ),
      ),
      body: Stack(
        children: [
          Positioned.fill(child: CustomPaint(painter: GridPainter())),
          TabBarView(
            controller: _tabController,
            children: [
              _buildRegionsTab(),
              _buildBuildingsTab(),
            ],
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => FacilityFormScreen(
                isBuildingForm: _tabController.index == 1,
              ),
            ),
          );
        },
        backgroundColor: const Color(0xFF1C1917),
        foregroundColor: const Color(0xFFEAB308),
        child: const Icon(Icons.add),
      ),
    );
  }

  Widget _buildRegionsTab() {
    final facilityController = context.watch<FacilityController>();
    final regions = facilityController.regions;

    if (facilityController.isLoading) {
      return const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)));
    }

    if (regions.isEmpty) {
      return const Center(child: Text('Không có khu vực nào.', style: TextStyle(color: Colors.grey)));
    }

    return RefreshIndicator(
      onRefresh: () => facilityController.fetchRegions(),
      color: const Color(0xFF1C1917),
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: regions.length,
        itemBuilder: (context, index) {
          final region = regions[index];
          return Card(
            color: Colors.white,
            margin: const EdgeInsets.only(bottom: 12),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: const BorderSide(color: Color(0xFFE4E2D7)),
            ),
            elevation: 0,
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              title: Text(
                region.name,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
              ),
              subtitle: Text(region.description ?? 'Không có mô tả', style: const TextStyle(fontSize: 13, color: Colors.grey)),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Switch(
                    value: region.isActive,
                    activeThumbColor: Colors.green,
                    activeTrackColor: Colors.green.withValues(alpha: 0.2),
                    inactiveThumbColor: Colors.grey,
                    onChanged: (val) {
                      facilityController.updateRegionStatus(region.id, val ? 1 : 2);
                    },
                  ),
                  IconButton(
                    icon: const Icon(Icons.edit_outlined, color: Color(0xFF1C1917)),
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => FacilityFormScreen(
                            isBuildingForm: false,
                            region: region,
                          ),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildBuildingsTab() {
    final facilityController = context.watch<FacilityController>();
    final buildings = facilityController.buildings;

    if (facilityController.isLoading) {
      return const Center(child: CircularProgressIndicator(color: Color(0xFF1C1917)));
    }

    if (buildings.isEmpty) {
      return const Center(child: Text('Không có tòa nhà nào.', style: TextStyle(color: Colors.grey)));
    }

    return RefreshIndicator(
      onRefresh: () => facilityController.fetchBuildings(),
      color: const Color(0xFF1C1917),
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: buildings.length,
        itemBuilder: (context, index) {
          final building = buildings[index];
          return Card(
            color: Colors.white,
            margin: const EdgeInsets.only(bottom: 12),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: const BorderSide(color: Color(0xFFE4E2D7)),
            ),
            elevation: 0,
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              title: Text(
                building.name,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF1C1917)),
              ),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.location_on_outlined, size: 14, color: Colors.grey),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          building.address ?? 'Chưa cấu hình địa chỉ',
                          style: const TextStyle(fontSize: 12, color: Colors.grey),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Switch(
                    value: building.isActive,
                    activeThumbColor: Colors.green,
                    activeTrackColor: Colors.green.withValues(alpha: 0.2),
                    inactiveThumbColor: Colors.grey,
                    onChanged: (val) {
                      facilityController.updateBuildingStatus(building.id, val ? 1 : 2);
                    },
                  ),
                  IconButton(
                    icon: const Icon(Icons.edit_outlined, color: Color(0xFF1C1917)),
                    onPressed: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => FacilityFormScreen(
                            isBuildingForm: true,
                            building: building,
                          ),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
