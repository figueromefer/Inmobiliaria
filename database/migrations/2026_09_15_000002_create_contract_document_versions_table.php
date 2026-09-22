<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_draft_version_id')
                ->constrained('contract_draft_versions')
                ->cascadeOnDelete();
            $table->unsignedInteger('document_version');
            $table->string('template_key', 100);
            $table->string('template_id')->nullable();
            $table->char('snapshot_hash', 64);
            $table->uuid('idempotency_key')->unique();
            $table->string('drive_file_id')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('status', 50)->default('not_requested');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_draft_version_id', 'document_version'], 'cdv_draft_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_document_versions');
    }
};
