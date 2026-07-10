<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin-maintenance', function ($user) {
    return $user instanceof \App\Models\Admin;
}, ['guards' => ['admin']]);

Broadcast::channel('admin-building.{buildingId}', function ($user, $buildingId) {
    if (! ($user instanceof \App\Models\Admin)) {
        return false;
    }
    return \App\Helpers\AdminScope::ensureBuildingAccess($user, (int) $buildingId);
}, ['guards' => ['admin']]);

Broadcast::channel('tenant.{id}', function ($user, $id) {
    return $user instanceof \App\Models\Tenant && (int) $user->id === (int) $id;
}, ['guards' => ['tenant']]);

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\ChatConversation::query()->find((int) $conversationId);

    if (! $conversation) {
        return false;
    }

    if ((int) $conversation->conversation_type === \App\Models\ChatConversation::TYPE_SUPER_ADMIN_MANAGER) {
        return $user instanceof \App\Models\Admin
            && (
                (\App\Helpers\AdminScope::isSuperAdmin($user) && (int) $conversation->super_admin_id === (int) $user->id)
                || (\App\Helpers\AdminScope::isBuildingManager($user) && (int) $conversation->manager_admin_id === (int) $user->id)
            );
    }

    if ($user instanceof \App\Models\Tenant) {
        return (int) $conversation->tenant_id === (int) $user->id;
    }

    if ($user instanceof \App\Models\Admin) {
        return (int) $conversation->manager_admin_id === (int) $user->id;
    }

    return false;
}, ['guards' => ['admin', 'tenant']]);

Broadcast::channel('chat.admin.{adminId}', function ($user, $adminId) {
    return $user instanceof \App\Models\Admin && (int) $user->id === (int) $adminId;
}, ['guards' => ['admin']]);

Broadcast::channel('chat.tenant.{tenantId}', function ($user, $tenantId) {
    return $user instanceof \App\Models\Tenant && (int) $user->id === (int) $tenantId;
}, ['guards' => ['tenant']]);

Broadcast::channel('admin-payments', function ($user) {
    return $user instanceof \App\Models\Admin;
}, ['guards' => ['admin']]);
