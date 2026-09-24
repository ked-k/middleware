<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sync ledger: one row per source record an integration has seen,
     * keyed by the source's own ID. Lets scheduled runs skip what was already
     * delivered, and stores the ID the target assigned (e.g. SyncLab lab_no).
     */
    public function up(): void
    {
        Schema::create('integration_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('source_key');
            $table->string('target_key')->nullable();
            $table->string('status');
            $table->string('payload_hash', 64)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('last_run_id')->nullable()->constrained('integration_runs')->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'source_key']);
            $table->index(['integration_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_records');
    }
};
