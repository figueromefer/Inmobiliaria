<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->latest();
        $q = trim((string) $request->query('q', ''));

        if ($q !== '') {
            $query->where(function ($where) use ($q) {
                $where->where('action', 'like', "%{$q}%")
                    ->orWhere('module', 'like', "%{$q}%")
                    ->orWhere('model_id', 'like', "%{$q}%")
                    ->orWhere('old_values', 'like', "%{$q}%")
                    ->orWhere('new_values', 'like', "%{$q}%")
                    ->orWhereHas('user', function ($userQuery) use ($q) {
                        $userQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $logs = $query->paginate(25)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $modules = ActivityLog::whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
        $actions = ActivityLog::distinct()->orderBy('action')->pluck('action');

        return view('activity_logs.index', compact('logs', 'users', 'modules', 'actions', 'q'));
    }
}
