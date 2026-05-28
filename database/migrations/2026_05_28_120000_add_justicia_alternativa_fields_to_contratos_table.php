<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('origen')->default('privado')->after('urldoc');
            $table->string('expediente_justicia_alternativa')->nullable()->after('origen');
            $table->timestamp('imported_at')->nullable()->after('expediente_justicia_alternativa');
            $table->json('raw_justicia_alternativa')->nullable()->after('imported_at');

            $table->unique(
                'expediente_justicia_alternativa',
                'contratos_expediente_ja_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropUnique('contratos_expediente_ja_unique');
            $table->dropColumn([
                'origen',
                'expediente_justicia_alternativa',
                'imported_at',
                'raw_justicia_alternativa',
            ]);
        });
    }
};
