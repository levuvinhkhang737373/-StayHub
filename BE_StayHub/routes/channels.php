<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('admin-maintenance', function ($user) {
    return $user instanceof \App\Models\Admin;
}, ['guards' => ['admin']]);

Broadcast::channel('tenant.{id}', function ($user, $id) {
    return $user instanceof \App\Models\Tenant && (int) $user->id === (int) $id;
}, ['guards' => ['tenant']]);
