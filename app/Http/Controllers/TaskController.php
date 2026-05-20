<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = Task::where('status', '!=', 'archived')
            ->orderBy('due_date')
            ->get();

        $archivedCount = Task::where('status', 'archived')->count();

        return view('tasks.index', compact('tasks', 'archivedCount'));
    }

    public function archived()
    {
        $tasks = Task::where('status', 'archived')
            ->orderByDesc('updated_at')
            ->get();

        return view('tasks.archived', compact('tasks'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'due_date' => 'nullable|date',
        ]);

        Task::create([
            'title' => $request->title,
            'due_date' => $request->due_date,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Tarea creada');
    }

    public function updateStatus(Task $task, Request $request)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,done,archived',
        ]);

        $task->update([
            'status' => $request->status,
        ]);

        return back()->with('success', 'Estatus actualizado');
    }
}
