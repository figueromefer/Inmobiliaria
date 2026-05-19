<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('recurrence', 20)->nullable()->after('priority');
            $table->date('next_run_date')->nullable()->after('recurrence');
            $table->date('last_generated_at')->nullable()->after('next_run_date');
            $table->unsignedBigInteger('parent_task_id')->nullable()->after('last_generated_at');

            $table->index(['recurrence', 'next_run_date']);
            $table->foreign('parent_task_id')
                ->references('id')
                ->on('tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->dropIndex(['recurrence', 'next_run_date']);
            $table->dropColumn([
                'recurrence',
                'next_run_date',
                'last_generated_at',
                'parent_task_id',
            ]);
        });
    }
};
