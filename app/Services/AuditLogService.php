<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\UserAccount;
use Carbon\Carbon;

class AuditLogService
{
    public function record(
        string $action,
        string $table,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?UserAccount $actor = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::create([
            'UserID' => $actor?->UserID,
            'Username' => $actor?->Username,
            'Action' => strtolower($action),
            'AffectedTable' => $table,
            'AffectedRecordID' => $recordId,
            'OldValues' => $oldValues,
            'NewValues' => $newValues,
            'IPAddress' => $ipAddress,
            'CreatedAt' => Carbon::now(),
        ]);
    }
}
