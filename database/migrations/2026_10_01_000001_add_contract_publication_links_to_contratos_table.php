<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_draft_version_id')->nullable()->unique()->after('id');
            $table->foreign('contract_draft_version_id', 'contratos_draft_version_foreign')
                ->references('id')->on('contract_draft_versions')->nullOnDelete();

            $table->unsignedBigInteger('contract_document_version_id')->nullable()->unique()->after('contract_draft_version_id');
            $table->foreign('contract_document_version_id', 'contratos_document_version_foreign')
                ->references('id')->on('contract_document_versions')->nullOnDelete();

            $table->unsignedBigInteger('previous_contract_id')->nullable()->after('contract_document_version_id');
            $table->foreign('previous_contract_id', 'contratos_previous_contract_foreign')
                ->references('id')->on('contratos')->nullOnDelete();
            $table->index('previous_contract_id', 'contratos_previous_contract_index');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropIndex('contratos_previous_contract_index');
            $table->dropForeign('contratos_previous_contract_foreign');
            $table->dropForeign('contratos_document_version_foreign');
            $table->dropForeign('contratos_draft_version_foreign');
            $table->dropUnique(['contract_draft_version_id']);
            $table->dropUnique(['contract_document_version_id']);
            $table->dropColumn([
                'contract_draft_version_id',
                'contract_document_version_id',
                'previous_contract_id',
            ]);
        });
    }
};
