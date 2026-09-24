<?php

namespace Tests\Feature;

use App\Actions\RunIntegration;
use App\Models\Integration;
use App\Models\ValueMap;
use Database\Seeders\NimsSyncLabExampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NimsToSyncLabIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NimsSyncLabExampleSeeder::class);
        $this->integration = Integration::where('name', 'NIMS referrals → SyncLab samples')->firstOrFail();
    }

    protected function nimsPayload(): array
    {
        $record = fn (string $identifier, string $sampleId, string $specimen, array $extra = []) => array_merge([
            'sample_id' => $sampleId, 'specimen_type' => $specimen, 'collection_date' => '2023-01-15 00:00:00',
            'status' => 'Result Added', 'sampling_purpose' => 'Diagnostic', 'volume' => 5.2,
            'country' => 'Kenya', 'state' => 'Nairobi', 'district' => 'Westlands',
            'package_no' => 'ExREF250924-003K_1', 'request_no' => 'ExREF250924-003K',
            'pathogen' => 'SARS-CoV-2', 'identifier' => $identifier, 'symptoms' => 'Fever, Cough',
        ], $extra);

        return [
            'success' => true,
            'institution' => 'Kenya National Reference Laboratory',
            'data' => [
                $record('ExS25-0001O', 'SAMPLE-001', 'Blood'),
                $record('ExS25-0005U', 'SAMPLE-0012', 'Blood'),
                $record('ExS25-0064E', 'SAMPLE-0012', 'Blood'), // same sample_id, different identifier
                $record('ExS25-0002A', 'SAMPLE-002', 'Swab', ['volume' => null, 'symptoms' => '["Cough","Sore throat"]']),
                $record('ExS25-0005U', 'SAMPLE-0012', 'Blood'), // exact duplicate identifier
            ],
            'message' => 'Referral samples fetched successfully',
        ];
    }

    protected function fillLookup(): void
    {
        ValueMap::first()->update(['entries' => [
            ['from' => 'Blood', 'to' => '1'],
            ['from' => 'Swab', 'to' => '2'],
        ]]);
    }

    protected function fakeHttp(): void
    {
        Http::fake([
            'nimsdev.africacdc.org/*' => Http::response($this->nimsPayload()),
            'synclab.licts.org/*' => function (Request $request) {
                $samples = collect($request->data()['samples'])->map(fn ($s) => [
                    'sid' => $s['sid'],
                    'lab_no' => 'LAB-'.substr(md5($s['sid']), 0, 5),
                ])->all();

                return Http::response(['status' => 201, 'data' => ['count' => count($samples), 'samples' => $samples]], 201);
            },
        ]);
    }

    public function test_preview_maps_records_without_sending(): void
    {
        Http::fake();

        $preview = app(RunIntegration::class)->preview($this->integration, $this->nimsPayload());

        Http::assertNothingSent();

        // Swab has no SyncLab ID yet in the seeded lookup → invalid.
        $this->assertSame(['fetched' => 5, 'filtered' => 0, 'duplicates' => 1, 'already_synced' => 0, 'invalid' => 1, 'ready' => 3, 'requests' => 1], $preview['stats']);

        $first = $preview['request_body']['samples'][0];
        $this->assertSame('ExS25-0001O', $first['sid']);
        $this->assertSame(1, $first['testing_facility_id']);
        $this->assertSame(1, $first['sample_type_id']);
        $this->assertSame('2023-01-15', $first['collection_date']);
        $this->assertSame('Westlands, Nairobi, Kenya', $first['collection_site']);
        $this->assertSame('mL', $first['volume_unit']);
        $this->assertStringContainsString('Kenya National Reference Laboratory', $first['comments']);
        $this->assertArrayNotHasKey('source_facility_id', $first);
    }

    public function test_run_registers_samples_in_bulk_once_and_captures_lab_numbers(): void
    {
        $this->fillLookup();
        $this->fakeHttp();

        $run = app(RunIntegration::class)->execute($this->integration);

        $this->assertSame('success', $run->status, (string) $run->error);
        $this->assertSame(4, $run->response_payload['succeeded']);

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'synclab.licts.org/api/v1/tracking/packages/samples/create/bulk')) {
                return false;
            }

            $swab = collect($request->data()['samples'])->firstWhere('sid', 'ExS25-0002A');

            return count($request->data()['samples']) === 4
                && $swab['sample_type_id'] === 2
                && ! array_key_exists('volume', $swab)        // null volume omitted
                && ! array_key_exists('volume_unit', $swab)   // …and so is its unit
                && str_contains($swab['comments'], 'Symptoms: Cough, Sore throat.');
        });

        $this->assertSame(4, $this->integration->records()->where('status', 'synced')->count());
        $this->assertStringStartsWith('LAB-', $this->integration->records()->where('source_key', 'ExS25-0001O')->value('target_key'));

        // Second run: everything already delivered, nothing re-sent.
        Http::fake([
            'nimsdev.africacdc.org/*' => Http::response($this->nimsPayload()),
            'synclab.licts.org/*' => Http::response([], 500),
        ]);

        $second = app(RunIntegration::class)->execute($this->integration->fresh());

        $this->assertSame('success', $second->status);
        $this->assertSame(4, $second->response_payload['already_synced']);
        $this->assertSame(0, $second->response_payload['sent']);
    }

    public function test_batches_are_chunked(): void
    {
        $this->fillLookup();
        $this->fakeHttp();
        $this->integration->update(['batch_size' => 3]);

        app(RunIntegration::class)->execute($this->integration->fresh());

        Http::assertSentCount(3); // 1 NIMS fetch + 2 SyncLab batches (3 + 1)
    }

    public function test_unmapped_lookup_marks_record_invalid_and_run_partial(): void
    {
        $this->fakeHttp();

        $run = app(RunIntegration::class)->execute($this->integration);

        $this->assertSame('partial', $run->status);
        $this->assertSame('invalid', $this->integration->records()->where('source_key', 'ExS25-0002A')->value('status'));
        $this->assertStringContainsString('Swab', (string) $run->error);
    }
}
