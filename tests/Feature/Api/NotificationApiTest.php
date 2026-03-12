<?php

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserNotification;
use Laravel\Sanctum\Sanctum;

test('user can list unread notifications, mark one as read, and get updated unread count', function () {
    $user = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $firstNotification = Notification::create([
        'type' => 'task',
        'title' => 'Inspect hull',
        'message' => 'Hull inspection assigned',
        'data' => ['task_id' => 11],
    ]);

    $secondNotification = Notification::create([
        'type' => 'lead',
        'title' => 'New lead',
        'message' => 'A new lead is waiting',
        'data' => ['lead_id' => 22],
    ]);

    $firstUserNotification = UserNotification::create([
        'user_id' => $user->id,
        'notification_id' => $firstNotification->id,
        'read' => false,
    ]);

    UserNotification::create([
        'user_id' => $user->id,
        'notification_id' => $secondNotification->id,
        'read' => true,
        'read_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/notifications?unread_only=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $firstUserNotification->id)
        ->assertJsonPath('data.0.notification.id', $firstNotification->id)
        ->assertJsonPath('data.0.notification.title', 'Inspect hull');

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('count', 1);

    $this->postJson("/api/notifications/{$firstUserNotification->id}/read")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Notification marked as read');

    expect($firstUserNotification->fresh()->read)->toBeTrue();
    expect($firstUserNotification->fresh()->read_at)->not->toBeNull();

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('count', 0);
});

test('user can mark all notifications as read', function () {
    $user = User::factory()->create([
        'type' => UserType::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);

    $firstNotification = Notification::create([
        'type' => 'task',
        'title' => 'Task reminder',
        'message' => 'You have a pending task',
    ]);

    $secondNotification = Notification::create([
        'type' => 'security',
        'title' => 'Security update',
        'message' => 'Review the latest security notice',
    ]);

    UserNotification::create([
        'user_id' => $user->id,
        'notification_id' => $firstNotification->id,
        'read' => false,
    ]);

    UserNotification::create([
        'user_id' => $user->id,
        'notification_id' => $secondNotification->id,
        'read' => false,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'All notifications marked as read');

    expect($user->userNotifications()->unread()->count())->toBe(0);
    expect($user->userNotifications()->whereNull('read_at')->count())->toBe(0);
});
