<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_draft_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_draft_id')
                ->constrained('contract_drafts')
                ->cascadeOnDelete();
            $table->unsignedInteger('draft_version');
            $table->string('schema_version', 100);
            $table->json('canonical_payload');
            $table->char('payload_hash', 64);
            $table->json('raw_legacy_payload')->nullable();
            $table->string('source', 50)->nullable();
            $table->string('action', 50)->default('saved');
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_draft_id', 'draft_version']);
            $table->index('payload_hash');
        });

        Schema::table('contract_drafts', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('contract_draft_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_drafts', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::dropIfExists('contract_draft_versions');
    }
};
