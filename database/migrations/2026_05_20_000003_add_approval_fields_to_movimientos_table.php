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
                $column = $table->string('approval_status')->default('approved');

                if (Schema::hasColumn('movimientos', 'comprobante')) {
                    $column->after('comprobante');
                }
            }

            if (! Schema::hasColumn('movimientos', 'approved_by')) {
                $column = $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

                if (Schema::hasColumn('movimientos', 'approval_status')) {
                    $column->after('approval_status');
                }
            }

            if (! Schema::hasColumn('movimientos', 'approved_at')) {
                $column = $table->timestamp('approved_at')->nullable();

                if (Schema::hasColumn('movimientos', 'approved_by')) {
                    $column->after('approved_by');
                }
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
