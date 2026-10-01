<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_drafts', function (Blueprint $table) {
            $table->unsignedBigInteger('renewal_of_contract_id')->nullable()->after('editing_contract_id');
            $table->foreign('renewal_of_contract_id', 'contract_drafts_renewal_contract_foreign')
                ->references('id')->on('contratos')->nullOnDelete();
            $table->index(['renewal_of_contract_id', 'purpose', 'status'], 'contract_drafts_renewal_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('contract_drafts', function (Blueprint $table) {
            $table->dropIndex('contract_drafts_renewal_lookup_index');
            $table->dropForeign('contract_drafts_renewal_contract_foreign');
            $table->dropColumn('renewal_of_contract_id');
        });
    }
};
