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
            $needle = '%' . mb_strtolower($q) . '%';

            $query->where(function ($where) use ($needle) {
                $where->whereRaw('LOWER(COALESCE(action, "")) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(module, "")) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(model_type, "")) LIKE ?', [$needle])
                    ->orWhereRaw('CAST(model_id AS CHAR) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(CAST(old_values AS CHAR), "")) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(CAST(new_values AS CHAR), "")) LIKE ?', [$needle])
                    ->orWhereHas('user', function ($userQuery) use ($needle) {
                        $userQuery->whereRaw('LOWER(COALESCE(name, "")) LIKE ?', [$needle])
                            ->orWhereRaw('LOWER(COALESCE(email, "")) LIKE ?', [$needle]);
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
