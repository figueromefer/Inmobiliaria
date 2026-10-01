<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_drafts', function (Blueprint $table) {
            $table->uuid('finalization_key')->nullable()->unique()->after('status');
            $table->unsignedBigInteger('finalization_draft_version_id')->nullable()->after('current_version_id');
            $table->foreign('finalization_draft_version_id', 'contract_drafts_finalization_version_foreign')
                ->references('id')->on('contract_draft_versions')->nullOnDelete();
            $table->unique('contrato_id', 'contract_drafts_contrato_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contract_drafts', function (Blueprint $table) {
            $table->dropUnique('contract_drafts_contrato_unique');
            $table->dropForeign('contract_drafts_finalization_version_foreign');
            $table->dropUnique(['finalization_key']);
            $table->dropColumn(['finalization_key', 'finalization_draft_version_id']);
        });
    }
};
