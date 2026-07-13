class StayHubRealtimeContract {
  static const String privatePrefix = 'private-';
  static const String presencePrefix = 'presence-';

  static const String adminMaintenanceChannel = 'admin-maintenance';
  static const String adminPaymentsChannel = 'admin-payments';
  static const String adminSuperChannel = 'admin-super';
  static const String adminBuildingChannelPrefix = 'admin-building.';
  static const String tenantChannelPrefix = 'tenant.';
  static const String chatAdminChannelPrefix = 'chat.admin.';
  static const String chatTenantChannelPrefix = 'chat.tenant.';
  static const String chatConversationChannelPrefix = 'chat.conversation.';

  static const List<String> backendPrivateChannelPatterns = [
    adminMaintenanceChannel,
    adminPaymentsChannel,
    adminSuperChannel,
    'admin-building.{buildingId}',
    'tenant.{id}',
    'chat.conversation.{conversationId}',
    'chat.admin.{adminId}',
    'chat.tenant.{tenantId}',
  ];

  static const List<String> adminMaintenanceEvents = [
    'MaintenanceRequestCreated',
    'MaintenanceRequestAssigned',
    'MaintenanceRequestProcessing',
    'MaintenanceRequestCompleted',
    'MaintenanceFeedbackCreated',
  ];

  static const List<String> tenantMaintenanceEvents = [
    'MaintenanceRequestAssigned',
    'MaintenanceRequestProcessing',
    'MaintenanceRequestCompleted',
  ];

  static const List<String> tenantEvents = [
    'NotificationSent',
    'ContractDepositPaid',
    'InvoicePaid',
    'InvoiceIssued',
    'InvoiceReissued',
    ...tenantMaintenanceEvents,
  ];

  static const List<String> adminPaymentEvents = [
    'ContractDepositPaid',
    'InvoicePaid',
    'InvoiceReissued',
  ];

  static const List<String> chatEvents = [
    'ChatMessageSent',
    'ChatConversationRead',
    'NotificationSent',
  ];

  static const List<String> adminBuildingEvents = ['ContractExpired'];

  static const List<String> intentionallyUnsupportedPublicEvents = [
    'BulkInvoiceGenerated',
  ];

  static String privateChannelName(String channelName) {
    if (channelName.startsWith(privatePrefix) ||
        channelName.startsWith(presencePrefix)) {
      return channelName;
    }

    return '$privatePrefix$channelName';
  }

  static String tenantChannel(int tenantId) => '$tenantChannelPrefix$tenantId';

  static String adminBuildingChannel(int buildingId) =>
      '$adminBuildingChannelPrefix$buildingId';

  static String chatAdminChannel(int adminId) =>
      '$chatAdminChannelPrefix$adminId';

  static String chatTenantChannel(int tenantId) =>
      '$chatTenantChannelPrefix$tenantId';

  static String chatConversationChannel(int conversationId) =>
      '$chatConversationChannelPrefix$conversationId';

  static String localMaintenanceEventType(String eventName) {
    switch (eventName) {
      case 'MaintenanceRequestAssigned':
        return 'maintenance_assigned';
      case 'MaintenanceRequestProcessing':
        return 'maintenance_processing';
      case 'MaintenanceRequestCompleted':
        return 'maintenance_completed';
      default:
        return 'maintenance_updated';
    }
  }
}
