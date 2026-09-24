<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('schedule_cron')->nullable()->after('is_active');
            $table->string('last_run_status')->nullable()->after('schedule_cron');
            $table->timestamp('last_run_at')->nullable()->after('last_run_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn(['schedule_cron', 'last_run_status', 'last_run_at']);
        });
    }
};
