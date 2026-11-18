<?php

namespace App\Console\Commands;

use App\Services\PackageRecommendationClient;
use Illuminate\Console\Command;

class TrainPackageRecommendations extends Command
{
    protected $signature = 'ml:train-package-recommendations';

    protected $description = 'Ask the Python microservice to fit the current catalog';

    public function handle(PackageRecommendationClient $service): int
    {
        try {
            $result = $service->train();
        } catch (\RuntimeException) {
            $this->error('Python training unavailable. Check the service URL and at least two valid catalog packages.');

            return self::FAILURE;
        }
        $this->info('Python trained from '.($result['report']['sample_count'] ?? 0).' catalog packages.');
        $this->line('The service refits the latest catalog on inference; no model is stored or executed in Laravel.');

        return self::SUCCESS;
    }
}
