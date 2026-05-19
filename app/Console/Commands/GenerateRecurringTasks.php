<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'tareas:generar-recurrentes {--date= : Fecha de referencia en formato YYYY-MM-DD}';

    protected $description = 'Genera nuevas tareas a partir de tareas recurrentes vencidas.';

    public function handle(): int
    {
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->startOfDay();

        $recurringTasks = Task::query()
            ->whereNotNull('recurrence')
            ->whereNotNull('next_run_date')
            ->whereDate('next_run_date', '<=', $today->toDateString())
            ->get();

        $created = 0;

        foreach ($recurringTasks as $task) {
            $dueDate = Carbon::parse($task->next_run_date)->startOfDay();

            $alreadyExists = Task::query()
                ->where('parent_task_id', $task->id)
                ->whereDate('due_date', $dueDate->toDateString())
                ->exists();

            if (! $alreadyExists) {
                Task::create([
                    'title' => $task->title,
                    'description' => $task->description,
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'pending',
                    'priority' => $task->priority,
                    'source_type' => $task->source_type,
                    'source_id' => $task->source_id,
                    'assigned_to' => $task->assigned_to,
                    'created_by' => $task->created_by,
                    'parent_task_id' => $task->id,
                ]);

                $created++;
            }

            $task->update([
                'last_generated_at' => $today->toDateString(),
                'next_run_date' => $this->nextRunDate($dueDate, $task->recurrence)->toDateString(),
            ]);
        }

        $this->info("Tareas recurrentes generadas: {$created}");

        return self::SUCCESS;
    }

    private function nextRunDate(Carbon $date, string $recurrence): Carbon
    {
        return match ($recurrence) {
            'weekly' => $date->copy()->addWeek(),
            'yearly' => $date->copy()->addYear(),
            'monthly' => $date->copy()->addMonthNoOverflow(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }
}
