<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fe_submissions', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('document_id');
            $table->boolean('is_non_recoverable')->default(false)->after('response_status');
        });
    }

    public function down(): void
    {
        Schema::table('fe_submissions', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'is_non_recoverable']);
        });
    }
};
