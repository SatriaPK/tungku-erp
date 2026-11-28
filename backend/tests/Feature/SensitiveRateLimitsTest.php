<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->freezeTime();
});

test('failed login counts normalized identities and resets after its retry window', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/auth/login', ['email' => 'CLIENT@example.test', 'password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson('/api/v1/auth/login', ['email' => 'client@example.test', 'password' => 'wrong'])
        ->assertTooManyRequests()->assertHeader('Retry-After')->assertJsonStructure(['message']);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->postJson('/api/v1/auth/login', ['email' => 'client@example.test', 'password' => 'wrong'])->assertUnprocessable();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
    $this->travel(61)->seconds();
    $this->postJson('/api/v1/auth/login', ['email' => 'client@example.test', 'password' => 'wrong'])->assertUnprocessable();
});

test('rotating login emails cannot bypass the aggregate IP limit', function () {
    config(['rate-limits.login_ip' => 2]);
    $this->postJson('/api/v1/auth/login', ['email' => 'one@example.test', 'password' => 'wrong'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', ['email' => 'two@example.test', 'password' => 'wrong'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', ['email' => 'three@example.test', 'password' => 'wrong'])->assertTooManyRequests();
});

test('register limit is per IP and rejected attempts create no user', function () {
    config(['rate-limits.register' => 1]);
    $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
    $this->postJson('/api/v1/auth/register', [])->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.3'])->postJson('/api/v1/auth/register', [])->assertUnprocessable();
    $this->assertDatabaseCount('users', 0);
});

test('frontend can read retry headers on a throttled cross-origin request', function () {
    config(['rate-limits.register' => 1, 'cors.allowed_origins' => ['https://frontend.example.test']]);
    $this->withHeader('Origin', 'https://frontend.example.test');
    $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
    $response = $this->postJson('/api/v1/auth/register', [])
        ->assertTooManyRequests()
        ->assertHeader('Access-Control-Allow-Origin', 'https://frontend.example.test')
        ->assertHeader('Access-Control-Allow-Credentials', 'true')
        ->assertHeader('Retry-After');
    expect(strtolower($response->headers->get('Access-Control-Expose-Headers')))
        ->toContain('retry-after', 'x-ratelimit-limit', 'x-ratelimit-remaining');
    $this->assertDatabaseCount('users', 0);
});

test('inbox writes share a user bucket and rejected bulk read does not mutate notifications', function () {
    config(['rate-limits.authenticated.inbox-write' => 1]);
    $client = User::factory()->client()->create();
    $notification = $client->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'legacy', 'data' => []]);
    Sanctum::actingAs($client);
    $this->patchJson('/api/v1/notifications/'.Str::uuid().'/read')->assertNotFound();
    $this->patchJson('/api/v1/notifications/read-all')->assertTooManyRequests();
    expect($notification->fresh()->read_at)->toBeNull();
    Sanctum::actingAs(User::factory()->client()->create());
    $this->patchJson('/api/v1/notifications/read-all')->assertOk();
    Sanctum::actingAs($client);
    $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 1);
    $this->travel(61)->seconds();
    $this->patchJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated_count', 1);
});

test('invoice and final payment initiation share one bucket regardless of resource ID', function () {
    config(['rate-limits.authenticated.payment-initiation' => 1]);
    $client = User::factory()->client()->create();
    $project = Project::factory()->for($client, 'client')->create();
    $invoice = Invoice::factory()->for($client, 'client')->create(['project_id' => $project->id]);
    $payment = Payment::factory()->create(['project_id' => $project->id, 'type' => 'final']);
    Sanctum::actingAs($client);
    $this->postJson('/api/v1/client/invoices/'.$invoice->id.'/initiate-payment', [])->assertUnprocessable();
    $this->postJson('/api/v1/client/payments/'.$payment->id.'/final/initiate', ['provider' => 'cash'])->assertTooManyRequests();
    expect($payment->fresh()->status->value)->toBe('pending');
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('activity_log', 0);
});

test('sensitive route groups enforce their configured bucket', function (string $role, string $limiter, string $method, string $path) {
    config(['rate-limits.authenticated.'.$limiter => 1]);
    Sanctum::actingAs(User::factory()->create(['role' => $role]));
    $first = $this->json($method, $path);
    expect($first->status())->not->toBe(429);
    $this->json($method, $path)->assertTooManyRequests()->assertHeader('Retry-After');
})->with([
    'booking' => ['client', 'booking-write', 'POST', '/api/v1/client/bookings/confirm'],
    'quotation' => ['client', 'quotation-write', 'POST', '/api/v1/client/quotations'],
    'production' => ['operator', 'production-write', 'POST', '/api/v1/operator/stages/999999/confirm-deadline'],
    'finance' => ['manager', 'finance-write', 'POST', '/api/v1/manager/payments/999999/confirm'],
    'bulk' => ['manager', 'bulk-write', 'POST', '/api/v1/manager/crm/campaigns/999999/dispatch'],
    'expensive read' => ['manager', 'expensive-read', 'GET', '/api/v1/manager/finance/monthly-summary'],
    'inbox read' => ['client', 'inbox-read', 'GET', '/api/v1/notifications'],
]);

test('user throttling does not replace authentication or throttle provider callbacks', function () {
    $this->app['auth']->forgetGuards();
    PaymentGatewayFactory::clearFake();
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->postJson('/api/v1/webhooks/payment/xendit', [])->assertUnauthorized();
    }
    $this->assertDatabaseCount('payments', 0);
});
