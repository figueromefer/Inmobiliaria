<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('movimientos', 'approval_status')) {
                $table->string('approval_status')->default('approved')->after('comprobante');
            }

            if (! Schema::hasColumn('movimientos', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('movimientos', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }

            if (Schema::hasColumn('movimientos', 'approved_at')) {
                $table->dropColumn('approved_at');
            }

            if (Schema::hasColumn('movimientos', 'approval_status')) {
                $table->dropColumn('approval_status');
            }
        });
    }
};
