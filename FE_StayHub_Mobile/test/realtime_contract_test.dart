import 'dart:io';

import 'package:fe_stayhub_mobile/services/realtime_contract.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final repoRoot = _findRepoRoot();
  final backendRoot = Directory('${repoRoot.path}/BE_StayHub');
  final mobileRoot = Directory('${repoRoot.path}/FE_StayHub_Mobile');

  group('StayHub realtime contract', () {
    test('normalizes all mobile private channels with Laravel private prefix', () {
      final channelNames = [
        StayHubRealtimeContract.adminMaintenanceChannel,
        StayHubRealtimeContract.adminBuildingChannel(1),
        StayHubRealtimeContract.tenantChannel(59),
        StayHubRealtimeContract.chatAdminChannel(12),
        StayHubRealtimeContract.chatTenantChannel(59),
        StayHubRealtimeContract.chatConversationChannel(5),
      ];

      for (final channelName in channelNames) {
        expect(
          StayHubRealtimeContract.privateChannelName(channelName),
          startsWith(StayHubRealtimeContract.privatePrefix),
          reason: '$channelName must be sent to Reverb as a private-* channel',
        );
      }

      expect(
        StayHubRealtimeContract.privateChannelName('private-tenant.59'),
        'private-tenant.59',
      );
      expect(
        StayHubRealtimeContract.privateChannelName('presence-room.1'),
        'presence-room.1',
      );
    });

    test('matches backend private broadcast channel declarations', () {
      final channelsFile = File('${backendRoot.path}/routes/channels.php');
      expect(channelsFile.existsSync(), isTrue);
      final channelsSource = channelsFile.readAsStringSync();

      for (final pattern in StayHubRealtimeContract.backendPrivateChannelPatterns) {
        expect(
          channelsSource,
          contains("Broadcast::channel('$pattern'"),
          reason: 'Mobile realtime contract must match Laravel channel $pattern',
        );
      }
    });

    test('covers backend broadcast event names used by mobile', () {
      final eventsDir = Directory('${backendRoot.path}/app/Events');
      expect(eventsDir.existsSync(), isTrue);
      final eventSources = _backendEventSources(eventsDir).join('\n');

      final mobileEvents = {
        ...StayHubRealtimeContract.adminMaintenanceEvents,
        ...StayHubRealtimeContract.adminMaintenanceBillingEvents,
        ...StayHubRealtimeContract.tenantEvents,
        ...StayHubRealtimeContract.chatEvents,
        ...StayHubRealtimeContract.adminBuildingEvents,
      };

      for (final eventName in mobileEvents) {
        expect(
          eventSources,
          contains("return '$eventName';"),
          reason: 'Mobile listens for $eventName, backend must broadcast it',
        );
      }
    });

    test('does not miss backend private broadcast events', () {
      final eventsDir = Directory('${backendRoot.path}/app/Events');
      expect(eventsDir.existsSync(), isTrue);

      final privateBackendEvents = <String>{};
      for (final source in _backendEventSources(eventsDir)) {
        if (!source.contains('new PrivateChannel(')) continue;
        final eventName = RegExp(
          r"broadcastAs\(\): string\s*\{\s*return '([^']+)';",
          dotAll: true,
        ).firstMatch(source)?.group(1);
        if (eventName != null) {
          privateBackendEvents.add(eventName);
        }
      }

      final supportedMobileEvents = {
        ...StayHubRealtimeContract.adminMaintenanceEvents,
        ...StayHubRealtimeContract.adminMaintenanceBillingEvents,
        ...StayHubRealtimeContract.tenantEvents,
        ...StayHubRealtimeContract.chatEvents,
        ...StayHubRealtimeContract.adminBuildingEvents,
      };

      expect(
        privateBackendEvents.difference(supportedMobileEvents),
        isEmpty,
        reason: 'Every backend private broadcast event must be handled by mobile',
      );
    });

    test('documents public events that mobile intentionally does not subscribe', () {
      final eventSources = _backendEventSources(
        Directory('${backendRoot.path}/app/Events'),
      ).join('\n');

      for (final eventName
          in StayHubRealtimeContract.intentionallyUnsupportedPublicEvents) {
        expect(eventSources, contains("return '$eventName';"));
      }
    });

    test('websocket service never passes raw private channel names', () {
      final serviceFile = File('${mobileRoot.path}/lib/services/websocket_service.dart');
      expect(serviceFile.existsSync(), isTrue);
      final lines = serviceFile.readAsLinesSync();

      for (var index = 0; index < lines.length; index += 1) {
        if (!lines[index].contains('_client!.privateChannel(')) continue;
        final callSnippet = lines.skip(index).take(4).join('\n');
        expect(
          callSnippet,
          contains('_privateChannelName(channelName)'),
          reason: 'privateChannel call at line ${index + 1} must normalize private-* prefix',
        );
      }

      final serviceSource = lines.join('\n');
      expect(serviceSource, isNot(contains("privateChannel(\n        '")));
      expect(serviceSource, isNot(contains("privateChannel(\n        \"")));
    });

    test('admin building realtime uses authenticated building ids only', () {
      final dashboardFile = File(
        '${mobileRoot.path}/lib/views/dashboard/dashboard_screen.dart',
      );
      expect(dashboardFile.existsSync(), isTrue);
      final dashboardSource = dashboardFile.readAsStringSync();

      expect(dashboardSource, contains('admin.managedBuildingIds'));
      expect(
        dashboardSource,
        isNot(contains('fetchBuildings()')),
        reason:
            'Realtime subscriptions must not use offline/fallback building data.',
      );
    });
  });
}

Directory _findRepoRoot() {
  var current = Directory.current;
  while (true) {
    if (Directory('${current.path}/BE_StayHub').existsSync() &&
        Directory('${current.path}/FE_StayHub_Mobile').existsSync()) {
      return current;
    }

    final parent = current.parent;
    if (parent.path == current.path) {
      throw StateError('Cannot find RepoKyTucXaPhongTro root from ${Directory.current.path}');
    }
    current = parent;
  }
}

Iterable<String> _backendEventSources(Directory eventsDir) {
  return eventsDir
      .listSync()
      .whereType<File>()
      .where((file) => file.path.endsWith('.php'))
      .map((file) => file.readAsStringSync());
}
