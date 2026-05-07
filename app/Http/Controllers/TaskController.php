<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = Task::orderBy('due_date')->get();
        return view('tasks.index', compact('tasks'));
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
        $task->update([
            'status' => $request->status,
        ]);

        return back();
    }
}
