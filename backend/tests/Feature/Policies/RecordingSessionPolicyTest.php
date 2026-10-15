<?php

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\RecordingSession;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->room = Room::factory()->create();
    $this->client = User::factory()->client()->create();
    $this->otherClient = User::factory()->client()->create();
    $this->manager = User::factory()->manager()->create();
    $this->operator = User::factory()->operator()->create();
});

test('clients can view and cancel only their own sessions', function () {
    $own = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->create();
    $other = RecordingSession::factory()->for($this->room)->for($this->otherClient, 'client')->create();

    expect(Gate::forUser($this->client)->allows('view', $own))->toBeTrue()
        ->and(Gate::forUser($this->client)->allows('cancel', $own))->toBeTrue()
        ->and(Gate::forUser($this->client)->denies('view', $other))->toBeTrue()
        ->and(Gate::forUser($this->client)->denies('cancel', $other))->toBeTrue();
});

test('managers can view and cancel every session', function () {
    $session = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->create();

    expect(Gate::forUser($this->manager)->allows('view', $session))->toBeTrue()
        ->and(Gate::forUser($this->manager)->allows('cancel', $session))->toBeTrue();
});

test('operators can view sessions assigned to them', function () {
    $project = Project::query()->create([
        'code' => 'POL-'.fake()->unique()->numerify('#####'),
        'client_id' => $this->client->id,
        'name' => 'Policy project',
        'package_snapshot' => [],
        'price' => 0,
        'po_amount' => 0,
        'status' => 'pending_payment',
    ]);

    $assignedStage = ProjectStage::query()->create([
        'project_id' => $project->id,
        'stage' => 'recording',
        'sequence' => 1,
        'operator_id' => $this->operator->id,
        'status' => 'ready',
    ]);
    $assigned = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->create([
        'project_stage_id' => $assignedStage->id,
    ]);
    $unassigned = RecordingSession::factory()->for($this->room)->for($this->client, 'client')->create();

    expect(Gate::forUser($this->operator)->allows('view', $assigned))->toBeTrue()
        ->and(Gate::forUser($this->operator)->denies('view', $unassigned))->toBeTrue()
        ->and(Gate::forUser($this->operator)->denies('cancel', $assigned))->toBeTrue();
});

test('only booking roles can view any sessions', function () {
    expect(Gate::forUser($this->client)->allows('viewAny', RecordingSession::class))->toBeTrue()
        ->and(Gate::forUser($this->manager)->allows('viewAny', RecordingSession::class))->toBeTrue()
        ->and(Gate::forUser($this->operator)->allows('viewAny', RecordingSession::class))->toBeTrue();
});
