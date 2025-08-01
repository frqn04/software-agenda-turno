<?php

namespace App\Observers;

use App\Models\LogAuditoria;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        LogAuditoria::logActivity('created', $model, null, $model->toArray());
    }

    public function updated(Model $model): void
    {
        $oldValues = $model->getOriginal();
        $newValues = $model->getChanges();

        if (!empty($newValues)) {
            LogAuditoria::logActivity('updated', $model, $oldValues, $newValues);
        }
    }

    public function deleted(Model $model): void
    {
        LogAuditoria::logActivity('deleted', $model, $model->toArray(), null);
    }

    public function restored(Model $model): void
    {
        LogAuditoria::logActivity('restored', $model, null, $model->toArray());
    }

    public function forceDeleted(Model $model): void
    {
        LogAuditoria::logActivity('force_deleted', $model, $model->toArray(), null);
    }
}
