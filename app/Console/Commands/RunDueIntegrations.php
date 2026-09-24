<?php

namespace App\Console\Commands;

use App\Jobs\RunIntegrationJob;
use App\Models\Integration;
use Cron\CronExpression;
use Illuminate\Console\Command;

class RunDueIntegrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'integrations:run-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue every active, scheduled integration whose cron expression is currently due';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Integration::query()
            ->where('is_active', true)
            ->whereNotNull('schedule_cron')
            ->get()
            ->each(function (Integration $integration) {
                if (! CronExpression::isValidExpression($integration->schedule_cron)) {
                    $this->warn("Skipping integration #{$integration->id} ({$integration->name}): invalid cron expression.");

                    return;
                }

                if (! (new CronExpression($integration->schedule_cron))->isDue()) {
                    return;
                }

                $this->info("Queuing integration #{$integration->id}: {$integration->name}");

                RunIntegrationJob::dispatch($integration->id, trigger: 'scheduled');
            });

        return self::SUCCESS;
    }
}
