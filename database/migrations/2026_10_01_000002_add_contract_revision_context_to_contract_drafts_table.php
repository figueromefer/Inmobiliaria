<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_drafts', function (Blueprint $table) {
            // A revision is a separate editable draft. contrato_id remains reserved
            // for the single initial publication linked by the existing unique key.
            $table->unsignedBigInteger('editing_contract_id')->nullable()->after('contrato_id');
            $table->foreign('editing_contract_id', 'contract_drafts_editing_contract_foreign')
                ->references('id')->on('contratos')->nullOnDelete();
            $table->string('purpose', 30)->nullable()->after('source');
            $table->index(['editing_contract_id', 'purpose'], 'contract_drafts_editing_contract_purpose_index');
        });
    }

    public function down(): void
    {
        Schema::table('contract_drafts', function (Blueprint $table) {
            $table->dropIndex('contract_drafts_editing_contract_purpose_index');
            $table->dropForeign('contract_drafts_editing_contract_foreign');
            $table->dropColumn(['editing_contract_id', 'purpose']);
        });
    }
};
