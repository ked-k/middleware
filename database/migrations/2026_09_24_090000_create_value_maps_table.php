<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable lookup tables that translate one system's codes/labels into
     * another's (e.g. NIMS "Blood" → SyncLab sample_type_id 1).
     */
    public function up(): void
    {
        Schema::create('value_maps', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->json('entries');
            $table->string('fallback')->default('fail');
            $table->string('fallback_value')->nullable();
            $table->boolean('case_insensitive')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('value_maps');
    }
};
