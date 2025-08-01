<?php

namespace App\Observers;

use App\Models\Paciente;
use App\Models\LogAuditoria;

class PacienteObserver
{
    public function created(Paciente $paciente): void
    {
        LogAuditoria::logActivity(
            'created',
            'pacientes',
            $paciente->id,
            auth()->id(),
            null,
            $paciente->toArray()
        );
    }

    public function updated(Paciente $paciente): void
    {
        LogAuditoria::logActivity(
            'updated',
            'pacientes',
            $paciente->id,
            auth()->id(),
            $paciente->getOriginal(),
            $paciente->getChanges()
        );
    }

    public function deleted(Paciente $paciente): void
    {
        LogAuditoria::logActivity(
            'deleted',
            'pacientes',
            $paciente->id,
            auth()->id(),
            $paciente->toArray(),
            null
        );
    }

    public function restored(Paciente $paciente): void
    {
        LogAuditoria::logActivity(
            'restored',
            'pacientes',
            $paciente->id,
            auth()->id(),
            null,
            $paciente->toArray()
        );
    }

    public function forceDeleted(Paciente $paciente): void
    {
        LogAuditoria::logActivity(
            'force_deleted',
            'pacientes',
            $paciente->id,
            auth()->id(),
            $paciente->toArray(),
            null
        );
    }
}
