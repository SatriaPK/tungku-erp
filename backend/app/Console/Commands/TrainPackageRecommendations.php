<?php

namespace App\Console\Commands;

use App\Services\PackageRecommendationModel;
use Illuminate\Console\Command;

class TrainPackageRecommendations extends Command
{
    protected $signature = 'ml:train-package-recommendations';

    protected $description = 'Fit a nearest-neighbor model using the current non-demo database catalog';

    public function handle(PackageRecommendationModel $service): int
    {
        try {
            $model = $service->train();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Model trained from '.$model['report']['sample_count'].' database packages; no generated rows.');
        $this->line('Excluded packages: '.$model['report']['excluded_active_packages']);
        $this->line('Artifact: '.$service->path());
        foreach ($model['report']['warnings'] as $warning) {
            $this->warn($warning);
        }
        $this->warn($model['report']['evaluation']);

        return self::SUCCESS;
    }
}
