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
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('source_connection_id')->constrained('connections')->restrictOnDelete();
            $table->foreignId('source_endpoint_id')->constrained('endpoints')->restrictOnDelete();
            $table->foreignId('target_connection_id')->constrained('connections')->restrictOnDelete();
            $table->foreignId('target_endpoint_id')->constrained('endpoints')->restrictOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
