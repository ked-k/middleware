<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record-level settings: which records to send (filters), how to
     * recognise a record across runs (keys, for dedup), how to batch them,
     * and where to find the target's own ID for each record in its response.
     */
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->json('record_filters')->nullable()->after('bulk_mode');
            $table->string('source_key_field')->nullable()->after('record_filters');
            $table->string('target_key_field')->nullable()->after('source_key_field');
            $table->boolean('skip_synced')->default(true)->after('target_key_field');
            $table->boolean('resend_on_change')->default(false)->after('skip_synced');
            $table->unsignedInteger('batch_size')->nullable()->after('resend_on_change');
            $table->string('target_wrapper_path')->nullable()->after('batch_size');
            $table->string('response_collection_path')->nullable()->after('target_wrapper_path');
            $table->string('response_id_path')->nullable()->after('response_collection_path');
        });
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'record_filters', 'source_key_field', 'target_key_field', 'skip_synced', 'resend_on_change',
                'batch_size', 'target_wrapper_path', 'response_collection_path', 'response_id_path',
            ]);
        });
    }
};
