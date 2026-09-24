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
            $table->boolean('is_bulk')->default(false)->after('is_active');
            $table->string('source_collection_path')->nullable()->after('is_bulk');
            $table->string('bulk_mode')->default('per_item')->after('source_collection_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn(['is_bulk', 'source_collection_path', 'bulk_mode']);
        });
    }
};
