<?php

namespace App\Livewire;

use App\Models\Integration;
use App\Models\IntegrationRecord;
use App\Models\IntegrationRun;
use App\Models\System;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;


#[Title('Dashboard')]
class Dashboard extends Component
{
    /** Status colours (fixed status palette — always paired with icon + label). */
    public const STATUS_COLORS = [
        'success' => '#0ca30c',
        'partial' => '#fab219',
        'failed' => '#d03b3b',
    ];

    public const RANGES = [7 => 'Last 7 days', 14 => 'Last 14 days', 30 => 'Last 30 days'];

    #[Url]
    public int $days = 14;

    public function updatedDays(): void
    {
        if (! array_key_exists($this->days, self::RANGES)) {
            $this->days = 14;
        }
    }

    protected function since(): Carbon
    {
        return Carbon::now()->startOfDay()->subDays($this->days - 1);
    }

    /**
     * Finished runs in range (pending ones are still in flight or were
     * abandoned, and have no outcome to count).
     */
    #[Computed]
    public function runs(): Collection
    {
        return IntegrationRun::query()
            ->where('started_at', '>=', $this->since())
            ->whereIn('status', array_keys(self::STATUS_COLORS))
            ->get(['id', 'integration_id', 'status', 'started_at', 'response_payload']);
    }

    /**
     * @return array<string, int|float|null>
     */
    #[Computed]
    public function kpis(): array
    {
        $runs = $this->runs;
        $total = $runs->count();
        $ok = $runs->whereIn('status', ['success', 'partial'])->count();

        return [
            'runs' => $total,
            'success_rate' => $total > 0 ? round($ok / $total * 100, 1) : null,
            'failed_runs' => $runs->where('status', 'failed')->count(),
            'delivered' => IntegrationRecord::query()->where('status', 'synced')->where('synced_at', '>=', $this->since())->count(),
            'delivered_all_time' => IntegrationRecord::query()->where('status', 'synced')->count(),
            'needs_attention' => IntegrationRecord::query()->whereIn('status', ['failed', 'invalid'])->count(),
            'integrations' => Integration::query()->count(),
            'active_integrations' => Integration::query()->where('is_active', true)->count(),
            'systems' => System::query()->count(),
        ];
    }

    /**
     * One row per day: counts per status, oldest first.
     *
     * @return array<int, array{date: Carbon, label: string, success: int, partial: int, failed: int, total: int}>
     */
    #[Computed]
    public function daily(): array
    {
        $byDay = $this->runs->groupBy(fn ($run) => $run->started_at->toDateString());
        $days = [];

        for ($i = 0; $i < $this->days; $i++) {
            $date = $this->since()->addDays($i);
            $runs = $byDay->get($date->toDateString(), collect());

            $row = ['date' => $date, 'label' => $date->format('j M'), 'total' => $runs->count()];

            foreach (array_keys(self::STATUS_COLORS) as $status) {
                $row[$status] = $runs->where('status', $status)->count();
            }

            $days[] = $row;
        }

        return $days;
    }

    /**
     * A "nice" axis ceiling so gridlines land on round numbers.
     *
     * @return array{max: int, ticks: array<int, int>}
     */
    #[Computed]
    public function axis(): array
    {
        $peak = max(1, collect($this->daily)->max('total'));
        $step = match (true) {
            $peak <= 4 => 1,
            $peak <= 10 => 2,
            $peak <= 25 => 5,
            $peak <= 50 => 10,
            default => (int) (10 ** floor(log10($peak)) / 2) ?: 1,
        };
        $max = (int) (ceil($peak / $step) * $step);

        return ['max' => $max, 'ticks' => range(0, $max, $step)];
    }

    #[Computed]
    public function integrations(): Collection
    {
        $recordCounts = IntegrationRecord::query()
            ->selectRaw('integration_id, status, count(*) as total')
            ->groupBy('integration_id', 'status')
            ->get()
            ->groupBy('integration_id');

        return Integration::query()
            ->with(['sourceConnection.system', 'targetConnection.system'])
            ->orderByDesc('consecutive_failures')
            ->orderBy('name')
            ->get()
            ->map(function (Integration $integration) use ($recordCounts) {
                $counts = $recordCounts->get($integration->id, collect())->pluck('total', 'status');

                $integration->setAttribute('synced_count', (int) ($counts['synced'] ?? 0));
                $integration->setAttribute('problem_count', (int) ($counts['failed'] ?? 0) + (int) ($counts['invalid'] ?? 0));
                $integration->setAttribute('next_run_at', filled($integration->schedule_cron) && CronExpression::isValidExpression($integration->schedule_cron)
                    ? Carbon::instance((new CronExpression($integration->schedule_cron))->getNextRunDate())
                    : null);

                return $integration;
            });
    }

    #[Computed]
    public function recentProblems(): Collection
    {
        return IntegrationRun::query()
            ->with('integration:id,name')
            ->whereIn('status', ['failed', 'partial'])
            ->latest('id')
            ->limit(8)
            ->get();
    }
}
