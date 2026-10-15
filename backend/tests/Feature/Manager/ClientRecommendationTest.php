<?php

use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-10 12:00:00', 'Asia/Jakarta'));
    Sanctum::actingAs(User::factory()->manager()->create());
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function recommendationClient(array $attributes = []): User
{
    return User::factory()->client()->create(array_merge([
        'crm_consent_at' => now()->subYear(),
    ], $attributes));
}

function recommendationPayment(User $client, string $status, ?string $paidAt): Payment
{
    $project = Project::factory()->for($client, 'client')->create();

    return Payment::factory()->create([
        'project_id' => $project->id,
        'status' => $status,
        'paid_at' => $paidAt,
    ]);
}

it('recommends only consenting active clients with paid history and at least 90 days of inactivity', function () {
    $stale = recommendationClient();
    recommendationPayment($stale, PaymentStatus::Paid->value, '2026-09-11 11:59:59');

    $recent = recommendationClient();
    recommendationPayment($recent, PaymentStatus::Paid->value, '2026-09-01 12:00:00');
    recommendationPayment($recent, PaymentStatus::Paid->value, '2026-09-11 12:00:01');

    $pendingOnly = recommendationClient();
    recommendationPayment($pendingOnly, PaymentStatus::Pending->value, null);

    recommendationClient(['crm_consent_at' => null]);
    recommendationClient(['is_active' => false]);
    User::factory()->operator()->create(['crm_consent_at' => now()]);

    $this->getJson('/api/v1/manager/crm/recommendations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $stale->id)
        ->assertJsonPath('data.0.has_paid_order_history', true)
        ->assertJsonPath('data.1', null);
});

it('uses the inclusive 90-day Jakarta cutoff and stable pagination', function () {
    $cutoff = recommendationClient();
    recommendationPayment($cutoff, PaymentStatus::Paid->value, '2026-09-11 12:00:00');
    $older = recommendationClient();
    recommendationPayment($older, PaymentStatus::Paid->value, '2026-09-01 12:00:00');
    $tooRecent = recommendationClient();
    recommendationPayment($tooRecent, PaymentStatus::Paid->value, '2026-09-11 12:00:01');

    $this->getJson('/api/v1/manager/crm/recommendations?per_page=1&page=1')
        ->assertOk()
        ->assertJsonPath('data.0.id', $older->id)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('links.next', fn ($url) => str_contains($url, 'page=2'));

    $this->getJson('/api/v1/manager/crm/recommendations?per_page=1&page=2')
        ->assertOk()
        ->assertJsonPath('data.0.id', $cutoff->id)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('validates pagination and restricts access to active managers', function () {
    $this->getJson('/api/v1/manager/crm/recommendations?per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');

    Sanctum::actingAs(User::factory()->operator()->create());
    $this->getJson('/api/v1/manager/crm/recommendations')->assertForbidden();

    Sanctum::actingAs(User::factory()->manager()->inactive()->create());
    $this->getJson('/api/v1/manager/crm/recommendations')->assertForbidden();
});
