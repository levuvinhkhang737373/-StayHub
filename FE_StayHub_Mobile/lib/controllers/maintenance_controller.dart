import 'package:flutter/material.dart';
import '../models/maintenance_request.dart';

class MaintenanceController extends ChangeNotifier {
  final List<MaintenanceRequest> _mockRequests = [
    MaintenanceRequest(
      id: 1,
      requestCode: 'SC-0001',
      roomId: 1,
      roomNumber: '101',
      tenantId: 1,
      tenantName: 'Nguyễn Văn An',
      title: 'Hỏng vòi nước bồn rửa mặt',
      description: 'Vòi nước bị rò rỉ liên tục không khóa chặt được, cần thay mới.',
      status: 1, // PENDING
      images: [
        'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&q=80&w=300'
      ],
      createdAt: '2026-05-27 09:30',
    ),
    MaintenanceRequest(
      id: 2,
      requestCode: 'SC-0002',
      roomId: 2,
      roomNumber: '102',
      tenantId: 2,
      tenantName: 'Trần Thị Bình',
      title: 'Điều hòa không lạnh',
      description: 'Điều hòa bật 16 độ nhưng chỉ có gió, không thấy mát, nghi hết gas.',
      status: 3, // PROCESSING / IN PROGRESS
      images: [
        'https://images.unsplash.com/photo-1585338107529-13afc5f02586?auto=format&fit=crop&q=80&w=300'
      ],
      createdAt: '2026-05-26 14:15',
    ),
    MaintenanceRequest(
      id: 3,
      requestCode: 'SC-0003',
      roomId: 5,
      roomNumber: '201',
      tenantId: 3,
      tenantName: 'Lê Hoàng Cường',
      title: 'Hỏng bóng đèn ngủ lầu 2',
      description: 'Bóng đèn ngủ bật không sáng, đã thử lay chấu cắm nhưng không được.',
      status: 4, // COMPLETED / RESOLVED
      images: [
        'https://images.unsplash.com/photo-1565814329452-e1efa11c5b89?auto=format&fit=crop&q=80&w=300',
        'https://images.unsplash.com/photo-1550985616-10810253b84d?auto=format&fit=crop&q=80&w=300'
      ],
      feedback: 'Cảm ơn admin đã cho thợ sửa nhanh chóng!',
      createdAt: '2026-05-24 20:00',
    ),
    MaintenanceRequest(
      id: 4,
      requestCode: 'SC-0004',
      roomId: 1,
      roomNumber: '101',
      tenantId: 1,
      tenantName: 'Nguyễn Văn An',
      title: 'Hỏng ổ cắm điện phòng khách',
      description: 'Ổ cắm điện góc tường bị lỏng và đánh lửa khi cắm phích nước.',
      status: 4, // COMPLETED
      images: [
        'https://images.unsplash.com/photo-1558346490-a72e53ae2d4f?auto=format&fit=crop&q=80&w=300',
        'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?auto=format&fit=crop&q=80&w=300'
      ],
      createdAt: '2026-05-25 10:00',
    ),
  ];

  bool _isLoading = false;

  List<MaintenanceRequest> get requests => _mockRequests;
  bool get isLoading => _isLoading;

  /// Get requests for specific room (for Tenant view)
  List<MaintenanceRequest> getRequestsForRoom(String roomNumber) {
    return _mockRequests.where((r) => r.roomNumber == roomNumber).toList();
  }

  /// Create a new maintenance request (Tenant action)
  Future<bool> createRequest({
    required String roomNumber,
    required String title,
    required String description,
    String? beforeImageUrl,
  }) async {
    _isLoading = true;
    notifyListeners();

    await Future.delayed(const Duration(milliseconds: 500));

    final newReq = MaintenanceRequest(
      id: _mockRequests.length + 1,
      requestCode: 'SC-000${_mockRequests.length + 1}',
      roomId: 1,
      roomNumber: roomNumber,
      tenantId: 1,
      tenantName: 'Nguyễn Văn An',
      title: title,
      description: description,
      status: 1, // PENDING
      images: [
        beforeImageUrl ?? 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&q=80&w=300'
      ],
      createdAt: DateTime.now().toString().substring(0, 16),
    );

    _mockRequests.insert(0, newReq);
    _isLoading = false;
    notifyListeners();
    return true;
  }

  /// Update request status (Admin action)
  Future<bool> updateRequestStatus(int id, int status, {String? afterImageUrl, String? feedback}) async {
    _isLoading = true;
    notifyListeners();

    await Future.delayed(const Duration(milliseconds: 500));

    final index = _mockRequests.indexWhere((r) => r.id == id);
    if (index != -1) {
      final old = _mockRequests[index];
      
      // Rebuild images list preserving before image and optionally adding after image
      List<String> updatedImages = [];
      if (old.beforeImageUrl != null) updatedImages.add(old.beforeImageUrl!);
      final newAfterImage = afterImageUrl ?? old.afterImageUrl;
      if (newAfterImage != null) updatedImages.add(newAfterImage);

      _mockRequests[index] = MaintenanceRequest(
        id: old.id,
        requestCode: old.requestCode,
        roomId: old.roomId,
        roomNumber: old.roomNumber,
        tenantId: old.tenantId,
        tenantName: old.tenantName,
        title: old.title,
        description: old.description,
        status: status,
        images: updatedImages,
        assignedTo: old.assignedTo,
        receivedAt: old.receivedAt,
        completedAt: old.completedAt,
        feedback: feedback ?? old.feedback,
        createdAt: old.createdAt,
      );
      _isLoading = false;
      notifyListeners();
      return true;
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  /// Add Tenant Feedback (Tenant action)
  Future<bool> addFeedback(int id, String feedback) async {
    return updateRequestStatus(id, 4, feedback: feedback); // Keep COMPLETED status
  }
}
