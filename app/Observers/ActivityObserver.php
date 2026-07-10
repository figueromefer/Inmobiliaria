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
            'old_values' => ActivityLog::sanitizeValues($old),
            'new_values' => ActivityLog::sanitizeValues($new),
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
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $oldValues = array_intersect_key($model->getOriginal(), $changes);

        $this->log($model, 'updated', $oldValues, $changes);
    }

    public function deleted($model)
    {
        $this->log($model, 'deleted', $model->toArray(), null);
    }

    public function restored($model)
    {
        $this->log($model, 'restored', ['deleted_at' => $model->getOriginal('deleted_at')], $model->toArray());
    }
}
