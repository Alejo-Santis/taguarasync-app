<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fe_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resolution_id')->constrained('fe_resolutions')->cascadeOnDelete();
            $table->unsignedBigInteger('next_consecutive')->default(0);
            $table->timestamps();

            $table->unique('resolution_id');
            $table->index('tenant_id');
        });

        // Data migration: seed from existing fe_resolutions.current_number
        DB::statement('
            INSERT INTO fe_sends (tenant_id, resolution_id, next_consecutive, created_at, updated_at)
            SELECT tenant_id, id, current_number, NOW(), NOW()
            FROM fe_resolutions
        ');
    }

    public function down(): void
    {
        // Restore data back to fe_resolutions before dropping
        DB::statement('
            UPDATE fe_resolutions r
            SET current_number = COALESCE(
                (SELECT s.next_consecutive FROM fe_sends s WHERE s.resolution_id = r.id LIMIT 1),
                0
            )
        ');

        Schema::dropIfExists('fe_sends');
    }
};
