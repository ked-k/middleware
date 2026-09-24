<?php

namespace App\Actions;

use App\Models\Connection;
use App\Models\Endpoint;
use App\Models\Integration;
use App\Models\IntegrationRecord;
use App\Models\IntegrationRun;
use App\Models\ValueMap;
use App\Support\Mapping\RecordFilter;
use App\Support\Mapping\RecordMapper;
use App\Support\Mapping\Transforms;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Executes an integration: fetch the source endpoint, pick out the records,
 * filter/dedupe them, map each through the field-mapping pipeline, deliver
 * them to the target (one per request, or in batches), capture the ID the
 * target assigns to each, and record everything — the run (audit trail)
 * and each record's delivery state (the sync ledger).
 *
 * A record's journey:
 *   fetched → filtered out? → duplicate in this run? → already synced? →
 *   mapped (invalid if a required field is empty or a lookup fails) →
 *   sent → synced / failed
 *
 * preview() runs the same pipeline without sending or writing anything.
 */
class RunIntegration
{
    /**
     * Consecutive failures at which a warning is logged.
     */
    protected const ALERT_THRESHOLD = 3;

    /**
     * How long a run may hold the per-integration lock before it's
     * considered abandoned and released automatically.
     */
    protected const LOCK_SECONDS = 300;

    /**
     * Cap on how many mapped records are kept on the run row as a sample of
     * what was sent.
     */
    protected const SAMPLE_SIZE = 3;

    public function execute(Integration $integration, string $trigger = 'manual', int $attempt = 1): IntegrationRun
    {
        $this->loadRelations($integration);

        $lock = Cache::lock("integration-run:{$integration->id}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            return IntegrationRun::create([
                'integration_id' => $integration->id,
                'status' => 'failed',
                'trigger' => $trigger,
                'attempt' => $attempt,
                'error' => 'Skipped: another run of this integration is already in progress.',
                'started_at' => Date::now(),
                'finished_at' => Date::now(),
                'duration_ms' => 0,
            ]);
        }

        $startedAt = Date::now();

        $run = IntegrationRun::create([
            'integration_id' => $integration->id,
            'status' => 'pending',
            'trigger' => $trigger,
            'attempt' => $attempt,
            'started_at' => $startedAt,
        ]);

        try {
            $sourcePayload = $this->fetch($integration->sourceConnection, $integration->sourceEndpoint);
            $items = $this->prepare($integration, $sourcePayload);
            $ready = array_values(array_filter($items, fn ($i) => $i['status'] === 'ready'));

            $delivery = $this->deliver($integration, $ready);

            foreach ($items as $item) {
                if ($item['status'] === 'invalid') {
                    $this->remember($integration, $run, $item, 'invalid', null, implode(' | ', $item['errors']));
                }
            }

            foreach ($delivery['results'] as $result) {
                $this->remember($integration, $run, $result['item'], $result['ok'] ? 'synced' : 'failed', $result['target_key'], $result['error']);
            }

            $stats = $this->stats($items) + [
                'sent' => count($ready),
                'succeeded' => $delivery['succeeded'],
                'failed' => count($ready) - $delivery['succeeded'],
            ];

            $errors = array_merge(
                array_map(fn ($i) => $this->label($i).': '.implode('; ', $i['errors']), array_filter($items, fn ($i) => $i['status'] === 'invalid')),
                $delivery['errors'],
            );

            $run->forceFill([
                'status' => match (true) {
                    $stats['failed'] > 0 => 'failed',
                    $stats['invalid'] > 0 => 'partial',
                    default => 'success',
                },
                'request_payload' => [
                    'record_count' => count($items),
                    'sample' => $delivery['sample'],
                ],
                'response_payload' => $stats + ['last_response' => $delivery['last_response']],
                'error' => $this->summarizeErrors($errors),
            ]);
        } catch (Throwable $e) {
            $run->forceFill(['status' => 'failed', 'error' => $e->getMessage()]);
        } finally {
            $lock->release();
        }

        return $this->finish($integration, $run, $startedAt);
    }

    /**
     * Dry run: map records exactly as a real run would, without sending
     * anything or touching the ledger. Pass $sourcePayload to test against a
     * pasted example instead of calling the live source.
     *
     * @param  array<string, mixed>|null  $sourcePayload
     * @return array{stats: array<string, int>, items: array<int, array<string, mixed>>, request_body: mixed}
     */
    public function preview(Integration $integration, ?array $sourcePayload = null, int $limit = 25): array
    {
        $this->loadRelations($integration);

        $sourcePayload ??= $this->fetch($integration->sourceConnection, $integration->sourceEndpoint);
        $items = $this->prepare($integration, $sourcePayload);
        $ready = array_values(array_filter($items, fn ($i) => $i['status'] === 'ready'));

        $firstBody = null;
        if ($ready !== []) {
            $firstBody = $this->usesBatches($integration)
                ? $this->wrapBatch($integration, array_column(array_slice($ready, 0, $this->batchSize($integration, count($ready))), 'payload'))
                : $ready[0]['payload'];
        }

        return [
            'stats' => $this->stats($items) + ['requests' => $this->requestCount($integration, count($ready))],
            'items' => array_map(fn ($i) => Arr::only($i, ['index', 'key', 'status', 'errors', 'payload', 'reason']), array_slice($items, 0, $limit)),
            'request_body' => $firstBody,
        ];
    }

    /**
     * Turn a source response into a list of prepared items, each with a
     * status: ready | filtered | duplicate | already_synced | invalid.
     *
     * @param  array<string, mixed>  $sourcePayload
     * @return array<int, array<string, mixed>>
     */
    protected function prepare(Integration $integration, array $sourcePayload): array
    {
        $records = $this->extractRecords($integration, $sourcePayload);
        $mapper = $this->mapper($integration);
        $filters = $integration->record_filters ?? [];
        $keyField = $integration->source_key_field;

        $ledger = $keyField && $integration->exists
            ? $integration->records()->where('status', 'synced')->pluck('payload_hash', 'source_key')->all()
            : [];

        $seen = [];
        $items = [];

        foreach ($records as $index => $record) {
            $record = (array) $record;
            $key = $keyField ? Transforms::read($keyField, $record, $sourcePayload) : null;
            $key = is_scalar($key) && (string) $key !== '' ? (string) $key : null;

            $item = ['index' => $index, 'key' => $key, 'status' => 'ready', 'errors' => [], 'payload' => null, 'hash' => null, 'reason' => null];

            if ($filters !== [] && ! RecordFilter::passes($record, $filters, $sourcePayload)) {
                $items[] = ['status' => 'filtered', 'reason' => 'Filtered out: '.RecordFilter::reason($record, $filters, $sourcePayload)] + $item;

                continue;
            }

            if ($key !== null && isset($seen[$key])) {
                $items[] = ['status' => 'duplicate', 'reason' => "Same {$keyField} as record #{$seen[$key]} in this response."] + $item;

                continue;
            }

            if ($key !== null) {
                $seen[$key] = $index;
            }

            ['payload' => $payload, 'errors' => $errors] = $mapper->map($record, $sourcePayload);
            $item['payload'] = $payload;
            $item['hash'] = hash('sha256', json_encode($payload));

            if ($errors !== []) {
                $items[] = ['status' => 'invalid', 'errors' => $errors] + $item;

                continue;
            }

            if ($key !== null && $integration->skip_synced && array_key_exists($key, $ledger)) {
                $changed = $ledger[$key] !== $item['hash'];

                if (! ($integration->resend_on_change && $changed)) {
                    $items[] = ['status' => 'already_synced', 'reason' => 'Already delivered in an earlier run.'] + $item;

                    continue;
                }
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * A bulk integration reads a list from the response; a single one treats
     * the whole response as one record.
     *
     * @return array<int, mixed>
     */
    protected function extractRecords(Integration $integration, array $sourcePayload): array
    {
        if (! $integration->is_bulk) {
            return [$sourcePayload];
        }

        $records = $integration->source_collection_path
            ? Arr::get($sourcePayload, $integration->source_collection_path)
            : $sourcePayload;

        if (! is_array($records) || ($records !== [] && ! array_is_list($records))) {
            throw new RuntimeException(
                'Expected a list of records at "'.($integration->source_collection_path ?: '(the response root)')."\" but didn't find one.",
            );
        }

        return $records;
    }

    /**
     * Send ready items and report a result per item.
     *
     * @param  array<int, array<string, mixed>>  $ready
     * @return array{succeeded: int, results: array<int, array<string, mixed>>, errors: array<int, string>, sample: mixed, last_response: string|null}
     */
    protected function deliver(Integration $integration, array $ready): array
    {
        $out = ['succeeded' => 0, 'results' => [], 'errors' => [], 'sample' => null, 'last_response' => null];

        if ($ready === []) {
            return $out;
        }

        if ($this->usesBatches($integration)) {
            foreach (array_chunk($ready, $this->batchSize($integration, count($ready))) as $chunkNo => $chunk) {
                $body = $this->wrapBatch($integration, array_column($chunk, 'payload'));
                $out['sample'] ??= $this->wrapBatch($integration, array_column(array_slice($chunk, 0, self::SAMPLE_SIZE), 'payload'));

                try {
                    $response = $this->send($integration->targetConnection, $integration->targetEndpoint, $body);
                    $out['last_response'] = mb_strimwidth($response->body(), 0, 2000, '…');
                    $ok = $response->successful();
                    $error = $ok ? null : $this->httpErrorMessage($response);
                    $targetKeys = $ok ? $this->batchTargetKeys($integration, (array) $response->json()) : [];
                } catch (Throwable $e) {
                    [$ok, $error, $targetKeys] = [false, $e->getMessage(), []];
                }

                if (! $ok) {
                    $out['errors'][] = 'Batch '.($chunkNo + 1).' ('.count($chunk)." records): {$error}";
                }

                foreach ($chunk as $item) {
                    $targetValue = $integration->target_key_field ? Arr::get($item['payload'], $integration->target_key_field) : null;

                    $out['results'][] = [
                        'item' => $item,
                        'ok' => $ok,
                        'target_key' => $targetValue !== null ? ($targetKeys[(string) $targetValue] ?? null) : null,
                        'error' => $error,
                    ];
                    $out['succeeded'] += $ok ? 1 : 0;
                }
            }

            return $out;
        }

        // One request per record.
        $out['sample'] = array_column(array_slice($ready, 0, self::SAMPLE_SIZE), 'payload');

        foreach ($ready as $item) {
            try {
                $response = $this->send($integration->targetConnection, $integration->targetEndpoint, $item['payload']);
                $out['last_response'] = mb_strimwidth($response->body(), 0, 2000, '…');
                $ok = $response->successful();
                $error = $ok ? null : $this->httpErrorMessage($response);
                $targetKey = $ok && $integration->response_id_path ? Arr::get((array) $response->json(), $integration->response_id_path) : null;
            } catch (Throwable $e) {
                [$ok, $error, $targetKey] = [false, $e->getMessage(), null];
            }

            if (! $ok) {
                $out['errors'][] = $this->label($item).": {$error}";
            }

            $out['results'][] = ['item' => $item, 'ok' => $ok, 'target_key' => is_scalar($targetKey) ? (string) $targetKey : null, 'error' => $error];
            $out['succeeded'] += $ok ? 1 : 0;
        }

        return $out;
    }

    /**
     * From a batch response, build "target key → target-assigned ID", e.g.
     * SyncLab's data.samples[*]: sid → lab_no.
     *
     * @return array<string, string|null>
     */
    protected function batchTargetKeys(Integration $integration, array $response): array
    {
        if (! $integration->response_collection_path || ! $integration->target_key_field) {
            return [];
        }

        $keys = [];

        foreach ((array) Arr::get($response, $integration->response_collection_path, []) as $row) {
            $match = Arr::get((array) $row, $integration->target_key_field);
            $id = $integration->response_id_path ? Arr::get((array) $row, $integration->response_id_path) : $match;

            if (is_scalar($match)) {
                $keys[(string) $match] = is_scalar($id) ? (string) $id : null;
            }
        }

        return $keys;
    }

    /**
     * Write/refresh a record's row in the sync ledger. Records with no source
     * key can't be tracked across runs and are skipped.
     *
     * @param  array<string, mixed>  $item
     */
    protected function remember(Integration $integration, IntegrationRun $run, array $item, string $status, ?string $targetKey, ?string $error): void
    {
        if ($item['key'] === null) {
            return;
        }

        $existing = IntegrationRecord::firstOrNew(['integration_id' => $integration->id, 'source_key' => $item['key']]);

        $existing->fill([
            'status' => $status,
            'target_key' => $targetKey ?? $existing->target_key,
            'payload_hash' => $item['hash'],
            'attempts' => $existing->attempts + 1,
            'last_error' => $status === 'synced' ? null : mb_strimwidth((string) $error, 0, 2000, '…'),
            'last_run_id' => $run->id,
            'synced_at' => $status === 'synced' ? Date::now() : $existing->synced_at,
        ])->save();
    }

    protected function usesBatches(Integration $integration): bool
    {
        return $integration->is_bulk && $integration->bulk_mode === 'single_request';
    }

    protected function batchSize(Integration $integration, int $total): int
    {
        return max(1, $integration->batch_size ?: $total);
    }

    protected function requestCount(Integration $integration, int $ready): int
    {
        if ($ready === 0) {
            return 0;
        }

        return $this->usesBatches($integration) ? (int) ceil($ready / $this->batchSize($integration, $ready)) : $ready;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return array<mixed>
     */
    protected function wrapBatch(Integration $integration, array $payloads): array
    {
        if (blank($integration->target_wrapper_path)) {
            return $payloads;
        }

        $body = [];
        Arr::set($body, $integration->target_wrapper_path, $payloads);

        return $body;
    }

    protected function mapper(Integration $integration): RecordMapper
    {
        $lookupIds = $integration->fieldMappings->flatMap->lookupIds()->unique()->values();

        $lookups = ValueMap::query()->whereIn('id', $lookupIds)->get()
            ->mapWithKeys(fn (ValueMap $map) => [(string) $map->id => $map->toLookup()])
            ->all();

        return new RecordMapper(
            $integration->fieldMappings->map->toRule()->all(),
            new Transforms(fn ($id) => $lookups[(string) $id] ?? null),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, int>
     */
    protected function stats(array $items): array
    {
        $count = fn (string $status) => count(array_filter($items, fn ($i) => $i['status'] === $status));

        return [
            'fetched' => count($items),
            'filtered' => $count('filtered'),
            'duplicates' => $count('duplicate'),
            'already_synced' => $count('already_synced'),
            'invalid' => $count('invalid'),
            'ready' => $count('ready'),
        ];
    }

    protected function label(array $item): string
    {
        return $item['key'] !== null ? "Record {$item['key']}" : "Record #{$item['index']}";
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function summarizeErrors(array $errors): ?string
    {
        if ($errors === []) {
            return null;
        }

        $shown = array_slice($errors, 0, 5);
        $remaining = count($errors) - count($shown);

        return mb_strimwidth(implode(' | ', $shown), 0, 4000, '…').($remaining > 0 ? " …and {$remaining} more" : '');
    }

    /**
     * Stamp timing/status onto a run, roll the integration's last-run and
     * failure-streak state forward, and alert on a repeated failure streak.
     */
    protected function finish(Integration $integration, IntegrationRun $run, CarbonInterface $startedAt): IntegrationRun
    {
        $finishedAt = Date::now();

        $run->forceFill([
            'finished_at' => $finishedAt,
            'duration_ms' => $startedAt->diffInMilliseconds($finishedAt),
        ])->save();

        $consecutiveFailures = $run->status === 'failed' ? $integration->consecutive_failures + 1 : 0;

        $integration->forceFill([
            'last_run_status' => $run->status,
            'last_run_at' => $run->finished_at,
            'consecutive_failures' => $consecutiveFailures,
        ])->save();

        if ($consecutiveFailures >= self::ALERT_THRESHOLD) {
            Log::warning("Integration \"{$integration->name}\" (#{$integration->id}) has failed {$consecutiveFailures} times in a row.", [
                'integration_id' => $integration->id,
                'run_id' => $run->id,
                'error' => $run->error,
            ]);
        }

        return $run->fresh();
    }

    protected function loadRelations(Integration $integration): void
    {
        $integration->loadMissing([
            'sourceConnection.authProfile', 'sourceConnection.system',
            'sourceEndpoint',
            'targetConnection.authProfile', 'targetConnection.system',
            'targetEndpoint',
            'fieldMappings',
        ]);
    }

    /**
     * Call the source endpoint and decode its JSON response.
     *
     * @return array<string, mixed>
     */
    protected function fetch(Connection $connection, Endpoint $endpoint): array
    {
        $response = $this->client($connection)->send($endpoint->method, $this->url($connection, $endpoint));
        $response->throw();

        return (array) $response->json();
    }

    /**
     * Push a payload to the target endpoint.
     *
     * @param  array<mixed>  $payload
     */
    protected function send(Connection $connection, Endpoint $endpoint, array $payload): Response
    {
        $client = $this->client($connection);
        $url = $this->url($connection, $endpoint);

        return in_array($endpoint->method, ['GET', 'DELETE'], true)
            ? $client->send($endpoint->method, $url, ['query' => $payload])
            : $client->send($endpoint->method, $url, ['json' => $payload]);
    }

    protected function client(Connection $connection)
    {
        return ($connection->authProfile?->toHttpClient() ?? Http::asJson())->acceptJson()->timeout(60);
    }

    protected function url(Connection $connection, Endpoint $endpoint): string
    {
        return rtrim((string) $connection->system->base_url, '/').'/'.ltrim($endpoint->path, '/');
    }

    protected function httpErrorMessage(Response $response): string
    {
        return "HTTP {$response->status()}: ".mb_strimwidth($response->body(), 0, 500, '…');
    }
}
