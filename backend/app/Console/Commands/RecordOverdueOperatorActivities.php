<?php

namespace App\Console\Commands;

use App\Actions\Project\RecordOverdueStages;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('operator-activities:record-overdue')]
#[Description('Record newly detected overdue production stages')]
class RecordOverdueOperatorActivities extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RecordOverdueStages $recorder): int
    {
        $counts = $recorder->handle();
        $this->info("Examined: {$counts['examined']}; recorded: {$counts['recorded']}; skipped: {$counts['skipped']}.");

        return self::SUCCESS;
    }
}
