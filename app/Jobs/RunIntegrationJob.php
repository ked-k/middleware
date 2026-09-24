<?php

namespace App\Jobs;

use App\Actions\RunIntegration;
use App\Models\Integration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a single integration attempt on the queue, so a batch of scheduled
 * integrations (or a retry) doesn't block the process that triggered it.
 */
class RunIntegrationJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    public function __construct(
        public int $integrationId,
        public string $trigger = 'scheduled',
        public int $attempt = 1,
    ) {}

    public function handle(RunIntegration $runner): void
    {
        $integration = Integration::find($this->integrationId);

        if (! $integration) {
            return;
        }

        $runner->execute($integration, trigger: $this->trigger, attempt: $this->attempt);
    }
}
