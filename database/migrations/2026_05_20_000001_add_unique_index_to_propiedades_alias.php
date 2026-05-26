<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('propiedades')
            ->select('alias', DB::raw('COUNT(*) as total'))
            ->whereNotNull('alias')
            ->where('alias', '<>', '')
            ->groupBy('alias')
            ->having('total', '>', 1)
            ->pluck('alias')
            ->all();

        if (! empty($duplicates)) {
            throw new RuntimeException(
                'No se puede agregar índice único a propiedades.alias. Alias duplicados: ' . implode(', ', $duplicates)
            );
        }

        Schema::table('propiedades', function (Blueprint $table) {
            $table->unique('alias', 'propiedades_alias_unique');
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropUnique('propiedades_alias_unique');
        });
    }
};
