<?php

namespace Database\Seeders;

use App\Models\AuthProfile;
use App\Models\Connection;
use App\Models\Endpoint;
use App\Models\Integration;
use App\Models\System;
use App\Models\ValueMap;
use Illuminate\Database\Seeder;

/**
 * Sets up the NIMS → SyncLab sample-referral integration end to end:
 * systems, endpoints (with example payloads as schemas), placeholder auth
 * profiles, connections, a specimen-type lookup table, and the integration
 * with its field mappings.
 *
 *   php artisan db:seed --class=NimsSyncLabExampleSeeder
 *
 * Safe to re-run: existing rows are found by name and left alone, so edits
 * made in the UI (credentials, lookup values, mappings) are not overwritten.
 *
 * Values to confirm before going live (marked "CONFIRM" below):
 *  - SyncLab sample_type_id for each NIMS specimen type (lookup table)
 *  - testing_facility_id (SyncLab's docs example uses 1)
 *  - auth credentials for both systems
 */
class NimsSyncLabExampleSeeder extends Seeder
{
    public function run(): void
    {
        // --- NIMS (source) ------------------------------------------------
        $nims = System::firstOrCreate(['name' => 'NIMS (Africa CDC)'], [
            'description' => 'Africa CDC NIMS — cross-border sample referrals.',
            'base_url' => 'https://nimsdev.africacdc.org/api/v1',
            'is_active' => true,
        ]);

        $incomingSamples = Endpoint::firstOrCreate(
            ['system_id' => $nims->id, 'method' => 'GET', 'path' => 'SampleReferralCrossBorder/referral/incoming/samples'],
            [
                'name' => 'Incoming referral samples',
                'description' => 'Samples referred to this institution. Records are under "data".',
                'response_schema' => [
                    'success' => true,
                    'institution' => 'Kenya National Reference Laboratory',
                    'data' => [[
                        'sample_id' => 'SAMPLE-001', 'specimen_type' => 'Blood', 'collection_date' => '2023-01-15 00:00:00',
                        'age' => 35, 'gender' => 'M', 'status' => 'Result Added', 'sampling_purpose' => 'Diagnostic',
                        'volume' => 5.2, 'country' => 'Kenya', 'state' => 'Nairobi', 'region' => 'Central', 'district' => 'Westlands',
                        'latitude' => null, 'longitude' => null, 'created_by' => 4,
                        'package_no' => 'ExREF250924-003K_1', 'request_no' => 'ExREF250924-003K',
                        'vaccination' => null, 'Symptom_onset_date' => null, 'delivered_at' => null,
                        'pathogen' => 'SARS-CoV-2', 'identifier' => 'ExS25-0001O', 'symptoms' => 'Fever, Cough',
                    ]],
                    'message' => 'Referral samples fetched successfully',
                ],
                'is_active' => true,
            ],
        );

        // CONFIRM: NIMS auth scheme — set it in the UI once known.
        $nimsAuth = AuthProfile::firstOrCreate(['system_id' => $nims->id, 'name' => 'NIMS API'], ['type' => 'none', 'credentials' => []]);
        $nimsConnection = Connection::firstOrCreate(['system_id' => $nims->id, 'name' => 'NIMS dev'], ['auth_profile_id' => $nimsAuth->id, 'status' => 'untested']);

        // --- SyncLab (target) ---------------------------------------------
        $synclab = System::firstOrCreate(['name' => 'SyncLab'], [
            'description' => 'SyncLab sample tracking. Source facility is derived from the API token — never send source_facility_id.',
            'base_url' => 'https://synclab.licts.org/api/v1/tracking',
            'is_active' => true,
        ]);

        $sampleFields = [
            'sid' => 'MULAGO-20260825-00001', 'testing_facility_id' => 1, 'sample_type_id' => 1,
            'sample_type_name' => 'Whole Blood', 'container_type_name' => 'EDTA Tube', 'collection_site' => 'Mulago Hospital',
            'collection_date' => '2026-08-25', 'collected_by' => 'John Doe', 'collector_contact' => '0700123456',
            'volume' => 5, 'volume_unit' => 'mL', 'comments' => 'Sample collected and submitted for laboratory testing.',
        ];

        Endpoint::firstOrCreate(
            ['system_id' => $synclab->id, 'method' => 'POST', 'path' => 'packages/samples/create'],
            [
                'name' => 'Register a single sample',
                'description' => 'Returns 201 with data.sid and data.lab_no.',
                'request_schema' => $sampleFields,
                'response_schema' => ['status' => 201, 'status_desc' => 'Sample created successfully', 'data' => ['sid' => 'MULAGO-20260825-00001', 'lab_no' => null]],
                'is_active' => true,
            ],
        );

        $bulkCreate = Endpoint::firstOrCreate(
            ['system_id' => $synclab->id, 'method' => 'POST', 'path' => 'packages/samples/create/bulk'],
            [
                'name' => 'Register samples in bulk',
                'description' => 'Max 100 samples per request, wrapped in "samples". Returns data.samples[*].sid / lab_no.',
                'request_schema' => ['samples' => [$sampleFields]],
                'response_schema' => ['status' => 201, 'status_desc' => 'Samples created successfully', 'data' => ['count' => 1, 'samples' => [['sid' => 'MULAGO-20260825-00001', 'lab_no' => 'LAB-47773']]]],
                'is_active' => true,
            ],
        );

        // CONFIRM: paste the facility's SyncLab API token into this profile.
        $synclabAuth = AuthProfile::firstOrCreate(['system_id' => $synclab->id, 'name' => 'SyncLab API token'], ['type' => 'bearer', 'credentials' => ['token' => '']]);
        $synclabConnection = Connection::firstOrCreate(['system_id' => $synclab->id, 'name' => 'SyncLab'], ['auth_profile_id' => $synclabAuth->id, 'status' => 'untested']);

        // --- Lookup: NIMS specimen_type → SyncLab sample_type_id ------------
        // Only "Blood → 1" is taken from SyncLab's docs (1 = Whole Blood).
        // CONFIRM the rest — records with a blank target fail with a clear
        // message until it is filled in on the Lookup tables page.
        $specimenTypes = ValueMap::firstOrCreate(['name' => 'NIMS specimen type → SyncLab sample_type_id'], [
            'description' => 'Fill in the SyncLab sample_type_id for each NIMS specimen type.',
            'entries' => [
                ['from' => 'Blood', 'to' => '1'],
                ['from' => 'Swab', 'to' => null],
                ['from' => 'Nasopharyngeal Swab', 'to' => null],
                ['from' => 'Urine', 'to' => null],
                ['from' => 'Stool', 'to' => null],
            ],
            'fallback' => 'fail',
            'case_insensitive' => true,
        ]);

        // --- The integration -----------------------------------------------
        $integration = Integration::firstOrCreate(['name' => 'NIMS referrals → SyncLab samples'], [
            'description' => 'Registers incoming NIMS referral samples in SyncLab, 100 per request, once each.',
            'source_connection_id' => $nimsConnection->id,
            'source_endpoint_id' => $incomingSamples->id,
            'target_connection_id' => $synclabConnection->id,
            'target_endpoint_id' => $bulkCreate->id,
            'is_active' => true,
            'is_bulk' => true,
            'source_collection_path' => 'data',
            'bulk_mode' => 'single_request',
            'batch_size' => 100,
            'target_wrapper_path' => 'samples',
            'response_collection_path' => 'data.samples',
            'response_id_path' => 'lab_no',
            // NIMS sample_id repeats across referrals (e.g. SAMPLE-0012 twice);
            // identifier (ExS25-…) is unique, so it is the key and becomes sid.
            'source_key_field' => 'identifier',
            'target_key_field' => 'sid',
            'skip_synced' => true,
            'resend_on_change' => false,
            'record_filters' => [['field' => 'identifier', 'operator' => 'not_empty', 'value' => null]],
        ]);

        if (! $integration->wasRecentlyCreated) {
            return;
        }

        $mappings = [
            ['identifier', 'sid', [['trim']], true, false],
            // CONFIRM: SyncLab testing facility ID.
            [null, 'testing_facility_id', [['constant', '1'], ['to_integer']], true, false],
            ['specimen_type', 'sample_type_id', [['trim'], ['lookup', (string) $specimenTypes->id], ['to_integer']], true, false],
            ['specimen_type', 'sample_type_name', [['trim']], false, true],
            [null, 'collection_site', [['template', '[{district}, ][{state}, ]{country}']], false, true],
            ['collection_date', 'collection_date', [['date_format', 'Y-m-d']], true, false],
            ['volume', 'volume', [['to_number']], false, true],
            ['volume', 'volume_unit', [['when_present', 'mL']], false, true],
            [null, 'comments', [['template', 'Referred via NIMS from {$root.institution}. Request {request_no}[, package {package_no}]. [Pathogen: {pathogen}. ][Purpose: {sampling_purpose}. ][Symptoms: {symptoms}. ]NIMS status: {status}.']], false, true],
        ];

        foreach ($mappings as $order => [$source, $target, $steps, $required, $skipIfEmpty]) {
            $integration->fieldMappings()->create([
                'source_field' => $source,
                'target_field' => $target,
                'transforms' => array_map(fn ($s) => ['type' => $s[0], 'param' => $s[1] ?? null], $steps),
                'is_required' => $required,
                'skip_if_empty' => $skipIfEmpty,
                'sort_order' => $order + 1,
            ]);
        }
    }
}
