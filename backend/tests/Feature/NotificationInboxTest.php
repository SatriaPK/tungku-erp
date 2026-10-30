<?php

use App\Actions\Notification\DeliverNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

function inboxNotification(User $user, array $attributes = []): DatabaseNotification
{
    return $user->notifications()->create([...[
        'id' => (string) Str::uuid(), 'type' => PaymentConfirmedNotification::class,
        'data' => ['type' => 'payment_confirmed', 'payment_id' => 1, 'order_id' => 'ORDER', 'amount' => 100000],
    ], ...$attributes]);
}

test('each active role reads only its own inbox and GET does not mark it read', function (string $role) {
    $this->freezeTime();
    $user = User::factory()->create(['role' => $role]);
    $other = User::factory()->create();
    $notice = inboxNotification($user);
    inboxNotification($other);
    $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $notice->id)->assertJsonPath('data.0.type', 'payment_confirmed')
        ->assertJsonPath('data.0.created_at', now()->toIso8601String())->assertJsonPath('data.0.read_at', null);
    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 1);
    expect($notice->fresh()->read_at)->toBeNull();
})->with(['manager', 'operator', 'client']);

test('guest and inactive accounts cannot access any inbox operation', function () {
    $uuid = (string) Str::uuid();
    foreach (['/api/v1/notifications', '/api/v1/notifications/unread-count'] as $url) {
        $this->getJson($url)->assertUnauthorized();
    }
    $this->patchJson('/api/v1/notifications/read-all')->assertUnauthorized();
    $this->patchJson('/api/v1/notifications/'.$uuid.'/read')->assertUnauthorized();
    $this->actingAs(User::factory()->inactive()->create());
    $this->getJson('/api/v1/notifications')->assertForbidden();
    $this->getJson('/api/v1/notifications/unread-count')->assertForbidden();
    $this->patchJson('/api/v1/notifications/read-all')->assertForbidden();
    $this->patchJson('/api/v1/notifications/'.$uuid.'/read')->assertForbidden();
});

test('read is owner scoped and repeated requests preserve the first read time', function () {
    $this->freezeTime();
    $user = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $own = inboxNotification($user);
    $foreign = inboxNotification($other);
    $this->actingAs($user)->patchJson('/api/v1/notifications/'.$foreign->id.'/read')->assertNotFound();
    $this->patchJson('/api/v1/notifications/'.Str::uuid().'/read')->assertNotFound();
    $this->patchJson('/api/v1/notifications/not-a-uuid/read')->assertNotFound();
    $firstRead = now()->toIso8601String();
    $this->patchJson('/api/v1/notifications/'.$own->id.'/read')->assertOk()->assertJsonPath('data.read_at', $firstRead);
    $this->travel(5)->minutes();
    $this->patchJson('/api/v1/notifications/'.$own->id.'/read')->assertOk()->assertJsonPath('data.read_at', $firstRead);
    expect($foreign->fresh()->read_at)->toBeNull();
});

test('pagination is stable and unread filters and empty responses are consistent', function () {
    $this->freezeTime();
    $user = User::factory()->client()->create();
    $ids = [];
    for ($i = 0; $i < 17; $i++) {
        $ids[] = inboxNotification($user)->id;
    }
    rsort($ids);
    $read = inboxNotification($user, ['read_at' => now(), 'created_at' => now()->subDay()]);
    $response = $this->actingAs($user)->getJson('/api/v1/notifications?unread=1');
    $response->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 17);
    expect(array_column($response->json('data'), 'id'))->toBe(array_slice($ids, 0, 15));
    $this->getJson('/api/v1/notifications?unread=1&page=2')->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/notifications?unread=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $read->id);
    $this->getJson('/api/v1/notifications?per_page=101')->assertUnprocessable();
    $this->getJson('/api/v1/notifications?per_page=0')->assertUnprocessable();
    $this->getJson('/api/v1/notifications?unread=invalid')->assertUnprocessable();
    $this->actingAs(User::factory()->client()->create())->getJson('/api/v1/notifications')->assertJsonPath('data', []);
    $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
});

test('mark all affects only the current owner snapshot and later deliveries stay unread even with an old occurrence time', function () {
    $this->freezeTime();
    $user = User::factory()->client()->create();
    $other = User::factory()->client()->create();
    $first = inboxNotification($user);
    $alreadyRead = inboxNotification($user, ['read_at' => now()->subDay()]);
    $foreign = inboxNotification($other);
    $later = NotificationDelivery::factory()->create(['recipient_id' => $user->id, 'occurred_at' => now()->subWeek()]);
    $this->actingAs($user)->patchJson('/api/v1/notifications/read-all', ['notifiable_id' => $other->id])
        ->assertOk()->assertJsonPath('data.updated_count', 1);
    $this->patchJson('/api/v1/notifications/read-all')->assertJsonPath('data.updated_count', 0);
    app(DeliverNotification::class)->handle($later->id);
    expect($user->notifications()->findOrFail($later->notification_id)->read_at)->toBeNull();
    expect($first->fresh()->read_at)->not->toBeNull();
    expect($foreign->fresh()->read_at)->toBeNull();
    expect($alreadyRead->fresh()->read_at->toIso8601String())->toBe(now()->subDay()->toIso8601String());
    $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 1);
});

test('legacy and versioned payloads expose only allowlisted data and safe target references', function () {
    $user = User::factory()->client()->create();
    inboxNotification($user, ['data' => ['type' => 'payment_confirmed', 'payment_id' => 3, 'amount' => 100,
        'order_id' => 'ORDER', 'result_url' => 'secret-url', 'snap_token' => 'secret-token']]);
    inboxNotification($user, ['type' => 'Unknown\\PrivateNotification', 'data' => ['email' => 'private-email']]);
    $event = new BusinessEventNotification('project:123:created', 'project_created', ['project_id' => 123, 'status' => 'pending_payment']);
    inboxNotification($user, ['type' => BusinessEventNotification::class, 'data' => [
        ...$event->toArray($user), 'target' => ['type' => 'url', 'id' => 'private-url'],
        'metadata' => ['project_id' => 123, 'status' => 'pending_payment', 'result_url' => 'locked-result'],
    ]]);
    $response = $this->actingAs($user)->getJson('/api/v1/notifications');
    $response->assertOk()->assertJsonCount(3, 'data');
    expect($response->getContent())->not->toContain('secret-url', 'secret-token', 'private-email', 'private-url', 'locked-result', 'Unknown');
    $rows = collect($response->json('data'))->keyBy('type');
    expect($rows['notification']['data'])->toBe([]);
    expect($rows['project_created']['data']['target'])->toBe(['type' => 'project', 'id' => 123]);
    expect($rows['payment_confirmed']['data'])->toBe(['type' => 'payment_confirmed', 'payment_id' => 3, 'amount' => 100, 'order_id' => 'ORDER']);
});
