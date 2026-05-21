<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fe_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->unsignedBigInteger('document_id');
            $table->string('xml_document_key', 120)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('response_status', 30)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'document_type', 'document_id']);
            $table->index(['tenant_id', 'response_status']);
            $table->index('xml_document_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fe_submissions');
    }
};
