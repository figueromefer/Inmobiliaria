<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityObserver
{
    protected function log($model, string $action, $old = null, $new = null)
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'module' => class_basename($model),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    public function created($model)
    {
        $this->log($model, 'created', null, $model->toArray());
    }

    public function updated($model)
    {
        $this->log($model, 'updated', $model->getOriginal(), $model->getChanges());
    }

    public function deleted($model)
    {
        $this->log($model, 'deleted', $model->toArray(), null);
    }
}
