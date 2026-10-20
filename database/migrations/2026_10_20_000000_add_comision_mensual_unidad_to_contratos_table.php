<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Additive and nullable: existing rows intentionally retain their
            // legacy interpretation of comision_mensual without a backfill.
            $table->string('comision_mensual_unidad', 16)->nullable()->after('comision_mensual');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('comision_mensual_unidad');
        });
    }
};
