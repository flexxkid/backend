<?php

namespace App\Observers;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditableObserver
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function created(Model $model): void
    {
        $this->log('CREATE', $model, null, $model->toArray());
    }

    public function updated(Model $model): void
    {
        $this->log('UPDATE', $model, $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->log('DELETE', $model, $model->getOriginal(), null);
    }

    private function log(string $action, Model $model, ?array $oldValues, ?array $newValues): void
    {
        $user = Auth::user();

        $this->auditLogService->record(
            $action,
            $model->getTable(),
            (int) $model->getKey(),
            $oldValues,
            $newValues,
            $user,
            Request::ip(),
        );
    }
}
