import 'package:fe_stayhub_mobile/controllers/chat_controller.dart';
import 'package:fe_stayhub_mobile/models/chat.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'canShowDirectConversationForAdmin rejects manager to manager direct conversation',
    () {
      final currentManagerId = 12;
      final otherManagerConversation = ChatConversation.fromJson({
        'id': 7,
        'conversation_type': 2,
        'manager_admin_id': 99,
        'manager_name': 'Quản lý khác',
        'super_admin_id': currentManagerId,
        'super_admin_name': 'Admin toà nhà hiện tại',
        'status': 1,
      });

      expect(
        canShowDirectConversationForAdmin(
          otherManagerConversation,
          currentManagerId,
          1,
        ),
        isFalse,
      );
    },
  );

  test(
    'canShowDirectConversationForAdmin allows manager to superadmin conversation',
    () {
      final currentManagerId = 12;
      final superAdminConversation = ChatConversation.fromJson({
        'id': 8,
        'conversation_type': 2,
        'manager_admin_id': currentManagerId,
        'manager_name': 'Admin toà nhà hiện tại',
        'super_admin_id': 3,
        'super_admin_name': 'Superadmin',
        'status': 1,
      });

      expect(
        canShowDirectConversationForAdmin(
          superAdminConversation,
          currentManagerId,
          1,
        ),
        isTrue,
      );
    },
  );
}
