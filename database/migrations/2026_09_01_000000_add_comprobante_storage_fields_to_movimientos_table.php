<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('movimientos', 'comprobante')) {
            Schema::table('movimientos', function (Blueprint $table) {
                $table->string('comprobante')->nullable();
            });
        }

        Schema::table('movimientos', function (Blueprint $table) {
            $table->string('comprobante_disk')->nullable()->after('comprobante');
            $table->string('comprobante_nombre_original')->nullable()->after('comprobante_disk');
            $table->string('comprobante_mime', 100)->nullable()->after('comprobante_nombre_original');
            $table->unsignedBigInteger('comprobante_size')->nullable()->after('comprobante_mime');
        });

        DB::table('movimientos')
            ->whereNotNull('comprobante')
            ->where('comprobante', '!=', '')
            ->update(['comprobante_disk' => 'public']);
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropColumn([
                'comprobante_disk',
                'comprobante_nombre_original',
                'comprobante_mime',
                'comprobante_size',
            ]);
        });
    }
};
