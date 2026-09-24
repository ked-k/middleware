<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the single transform_type/transform_param pair with an ordered
     * pipeline of transform steps, and add per-field validation flags.
     * Existing single transforms are carried over as one-step pipelines.
     */
    public function up(): void
    {
        Schema::table('field_mappings', function (Blueprint $table) {
            $table->string('source_field')->nullable()->change();
            $table->json('transforms')->nullable()->after('target_field');
            $table->boolean('is_required')->default(false)->after('transforms');
            $table->boolean('skip_if_empty')->default(false)->after('is_required');
        });

        DB::table('field_mappings')->orderBy('id')->each(function ($row) {
            $steps = $row->transform_type && $row->transform_type !== 'none'
                ? [['type' => $row->transform_type, 'param' => $row->transform_param]]
                : [];

            DB::table('field_mappings')->where('id', $row->id)->update(['transforms' => json_encode($steps)]);
        });

        Schema::table('field_mappings', function (Blueprint $table) {
            $table->dropColumn(['transform_type', 'transform_param']);
        });
    }

    public function down(): void
    {
        Schema::table('field_mappings', function (Blueprint $table) {
            $table->string('transform_type')->default('none')->after('target_field');
            $table->string('transform_param')->nullable()->after('transform_type');
        });

        DB::table('field_mappings')->orderBy('id')->each(function ($row) {
            $first = json_decode((string) $row->transforms, true)[0] ?? null;

            DB::table('field_mappings')->where('id', $row->id)->update([
                'transform_type' => $first['type'] ?? 'none',
                'transform_param' => $first['param'] ?? null,
            ]);
        });

        Schema::table('field_mappings', function (Blueprint $table) {
            $table->dropColumn(['transforms', 'is_required', 'skip_if_empty']);
        });
    }
};
