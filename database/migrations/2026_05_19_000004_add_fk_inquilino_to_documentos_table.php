<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->unsignedBigInteger('fk_inquilino')->nullable()->after('fk_propiedad');
            $table->foreign('fk_inquilino')
                ->references('id')
                ->on('inquilinos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign(['fk_inquilino']);
            $table->dropColumn('fk_inquilino');
        });
    }
};
