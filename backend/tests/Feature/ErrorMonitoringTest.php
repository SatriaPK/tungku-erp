<?php

use App\Actions\Monitoring\SanitizeErrorEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\SentrySdk;
use Sentry\Serializer\PayloadSerializer;
use Sentry\Stacktrace;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Sentry\UserDataBag;

beforeEach(function () {
    $this->originalHub = SentrySdk::getCurrentHub();
    $this->events = [];
    $this->transportFails = false;
    $this->configuredDsn = config('sentry.dsn');
    config(['sentry.dsn' => 'https://public@example.test/1', 'sentry.environment' => 'test-transport', 'sentry.release' => 'test-release']);
    $this->builder = app(ClientBuilder::class);
    config(['sentry.dsn' => $this->configuredDsn]);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->andReturnUsing(function (Event $event): Result {
        if ($this->transportFails) {
            throw new RuntimeException('Fake transport unavailable');
        }
        $this->events[] = $event;

        return new Result(ResultStatus::success(), $event);
    });
    $transport->shouldReceive('close')->andReturn(new Result(ResultStatus::success()));
    $this->builder->setTransport($transport);
    $hub = new Hub($this->builder->getClient());
    app()->instance(HubInterface::class, $hub);
    SentrySdk::setCurrentHub($hub);
    config(['app.debug' => false]);
});

afterEach(function () {
    SentrySdk::setCurrentHub($this->originalHub);
});

test('HTTP errors reach the fake transport while preserving JSON and local logging', function () {
    Log::spy();
    Route::post('/api/monitoring-test', function () {
        throw new RuntimeException('secret@example.test https://example.test/private-result');
    });
    $this->postJson('/api/monitoring-test', ['password' => 'sensitive-password'])
        ->assertStatus(500)->assertJsonPath('message', 'Server Error');
    expect($this->events)->toHaveCount(1);
    $event = $this->events[0];
    expect($event->getExceptions()[0]->getType())->toBe(RuntimeException::class);
    expect($event->getEnvironment())->toBe('test-transport');
    expect($event->getRelease())->toBe('test-release');
    $payload = (new PayloadSerializer($this->builder->getOptions()))->serialize($event);
    expect($payload)->not->toContain('secret@example.test', 'private-result', 'sensitive-password');
    Log::shouldHaveReceived('error')->once();
});

test('redaction removes arbitrary SDK contexts breadcrumbs and stack locals', function () {
    $event = Event::createEvent();
    $event->setRequest(['headers' => ['Authorization' => 'secret-token'], 'data' => ['phone' => '628123456789']]);
    $event->setContext('queue', ['payload' => 'secret-job']);
    $event->setExtra(['result_url' => 'https://example.test/secret-result']);
    $event->setTags(['customer' => 'secret-contact']);
    $event->setUser(UserDataBag::createFromArray(['id' => 'secret-user', 'email' => 'secret@example.test', 'ip_address' => '192.0.2.8']));
    $event->setBreadcrumb([new Breadcrumb('info', 'default', 'http', 'secret-breadcrumb', ['authorization' => 'secret-header'])]);
    $event->setTransaction('/secret-url');
    $frame = new Frame('perform', 'app/Actions/Example.php', 12, vars: ['password' => 'secret-local']);
    $frame->setContextLine('secret-source')->setPreContext(['secret-before'])->setPostContext(['secret-after']);
    $event->setExceptions([new ExceptionDataBag(new RuntimeException('secret-message'), new Stacktrace([$frame]))]);
    SentrySdk::getCurrentHub()->captureEvent($event);
    expect($this->events)->toHaveCount(1);
    $payload = (new PayloadSerializer($this->builder->getOptions()))->serialize($this->events[0]);
    expect($payload)->not->toContain('secret-', '628123456789');
    expect($payload)->toContain('RuntimeException', 'Example.php');
    expect($this->events[0]->getContexts())->toBe([]);
    expect($this->events[0]->getUser())->toBeNull();
    expect($this->events[0]->getBreadcrumbs())->toBe([]);
    expect(SanitizeErrorEvent::handle(Event::createTransaction()))->toBeNull();
});

test('expected API rejections do not produce monitoring events', function () {
    Route::post('/api/monitoring-validation', fn () => request()->validate(['name' => 'required']));
    Route::get('/api/monitoring-forbidden', fn () => abort(403));
    $this->postJson('/api/monitoring-validation', [])->assertUnprocessable();
    $this->getJson('/api/monitoring-forbidden')->assertForbidden();
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    expect($this->events)->toBe([]);
});

test('transport failure preserves the HTTP response and local error log', function () {
    $this->transportFails = true;
    Log::spy();
    Route::get('/api/monitoring-failure', function () {
        throw new RuntimeException('Application failure');
    });
    $this->getJson('/api/monitoring-failure')->assertStatus(500)->assertJsonPath('message', 'Server Error');
    Log::shouldHaveReceived('error')->once();
});

test('disabled monitoring preserves error handling without delivering events', function () {
    expect(config('sentry.dsn'))->toBeNull();
    $disabled = new Hub(app(ClientBuilder::class)->getClient());
    app()->instance(HubInterface::class, $disabled);
    SentrySdk::setCurrentHub($disabled);
    Log::spy();
    Route::get('/api/monitoring-disabled', function () {
        throw new RuntimeException('Disabled monitoring');
    });
    $this->getJson('/api/monitoring-disabled')->assertStatus(500);
    expect($this->events)->toBe([]);
    Log::shouldHaveReceived('error')->once();
});

test('a failed database queue job reports a sanitized error through the worker', function () {
    Queue::connection('database')->pushOn('monitoring-test', new MonitoringFailureJob);
    app('queue.worker')->runNextJob('database', 'monitoring-test', new WorkerOptions(sleep: 0, maxTries: 1));
    $this->assertDatabaseCount('jobs', 0);
    expect($this->events)->toHaveCount(1);
    expect($this->events[0]->getExceptions()[0]->getType())->toBe(RuntimeException::class);
    $payload = (new PayloadSerializer($this->builder->getOptions()))->serialize($this->events[0]);
    expect($payload)->not->toContain('secret-job-payload');
});

class MonitoringFailureJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException('secret-job-payload');
    }
}
