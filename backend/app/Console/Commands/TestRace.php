<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

#[Signature('test:race {url=http://erp-tungku.test/api/target : Target URL for the concurrent POST requests} {--token= : Optional bearer token} {--payload= : JSON object sent in each request}')]
#[Description('Send five concurrent HTTP POST requests to test a race condition')]
class TestRace extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $token = $this->option('token');
        $payload = json_decode((string) ($this->option('payload') ?: '{}'), true);

        if (! is_array($payload)) {
            $this->error('The --payload option must be a valid JSON object.');

            return self::FAILURE;
        }

        $headers = ['Accept' => 'application/json'];
        if (is_string($token) && $token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $this->warn('Run this against a real web server such as Laragon (Apache/Nginx), not `php artisan serve`.');
        $this->warn('The server must handle requests concurrently for this to exercise database race conditions.');
        $this->line("Sending five concurrent POST requests to {$url}...");

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (int $requestNumber) => $pool
                    ->as("request_{$requestNumber}")
                    ->withHeaders($headers)
                    ->timeout(30)
                    ->post($url, $payload),
                range(1, 5),
            ));
        } catch (\Throwable $exception) {
            $this->error('One or more requests could not be completed: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($responses as $name => $response) {
            if ($response instanceof \Throwable) {
                $this->error("{$name}: request failed - {$response->getMessage()}");

                continue;
            }

            $this->line(sprintf('%s: HTTP %d', $name, $response->status()));
        }

        return self::SUCCESS;
    }
}
