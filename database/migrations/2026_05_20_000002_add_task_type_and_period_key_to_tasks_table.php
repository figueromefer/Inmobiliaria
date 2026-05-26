<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'task_type')) {
                $table->string('task_type')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('tasks', 'period_key')) {
                $table->string('period_key')->nullable()->after('task_type');
            }

            $table->index(['task_type', 'period_key'], 'tasks_type_period_index');
            $table->index(['source_type', 'source_id'], 'tasks_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_type_period_index');
            $table->dropIndex('tasks_source_index');

            if (Schema::hasColumn('tasks', 'period_key')) {
                $table->dropColumn('period_key');
            }

            if (Schema::hasColumn('tasks', 'task_type')) {
                $table->dropColumn('task_type');
            }
        });
    }
};
