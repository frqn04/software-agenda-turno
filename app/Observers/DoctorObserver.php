<?php

namespace App\Observers;

use App\Models\Doctor;
use App\Models\LogAuditoria;

class DoctorObserver
{
    public function created(Doctor $doctor): void
    {
        LogAuditoria::logActivity(
            'created',
            'doctores',
            $doctor->id,
            auth()->id(),
            null,
            $doctor->toArray()
        );
    }

    public function updated(Doctor $doctor): void
    {
        LogAuditoria::logActivity(
            'updated',
            'doctores',
            $doctor->id,
            auth()->id(),
            $doctor->getOriginal(),
            $doctor->getChanges()
        );
    }

    public function deleted(Doctor $doctor): void
    {
        LogAuditoria::logActivity(
            'deleted',
            'doctores',
            $doctor->id,
            auth()->id(),
            $doctor->toArray(),
            null
        );
    }

    public function restored(Doctor $doctor): void
    {
        LogAuditoria::logActivity(
            'restored',
            'doctores',
            $doctor->id,
            auth()->id(),
            null,
            $doctor->toArray()
        );
    }

    public function forceDeleted(Doctor $doctor): void
    {
        LogAuditoria::logActivity(
            'force_deleted',
            'doctores',
            $doctor->id,
            auth()->id(),
            $doctor->toArray(),
            null
        );
    }
}
