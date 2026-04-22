<?php

namespace App\Services;

use App\Models\Notifications;
use App\Models\UserAccount;
use Carbon\Carbon;

class NotificationService
{
    public function create(
        ?int $recipientUserId,
        string $title,
        string $message,
        string $type,
        ?string $referenceTable = null,
        ?int $referenceId = null,
    ): Notifications {
        return Notifications::create([
            'RecipientUserID' => $recipientUserId,
            'Title' => $title,
            'Message' => $message,
            'NotificationType' => $type,
            'ReferenceTable' => $referenceTable,
            'ReferenceID' => $referenceId,
            'IsRead' => false,
            'CreatedAt' => Carbon::now(),
        ]);
    }

    public function notifyRole(string $roleName, string $title, string $message, string $type, ?string $referenceTable = null, ?int $referenceId = null): void
    {
        UserAccount::query()
            ->whereHas('role', fn ($query) => $query->where('RoleName', $roleName))
            ->where('AccountStatus', 'active')
            ->get()
            ->each(fn (UserAccount $user) => $this->create($user->UserID, $title, $message, $type, $referenceTable, $referenceId));
    }
}
