<?php

namespace App\Policies;

use App\Models\AuditScan;
use App\Models\User;

class AuditScanPolicy
{
    /**
     * Audit milik user hanya untuk pemiliknya; audit guest hanya untuk pemegang guest token.
     */
    public function view(?User $user, AuditScan $audit, ?string $guestToken = null): bool
    {
        if ($audit->user_id !== null) {
            return $user !== null && $user->id === $audit->user_id;
        }

        return $guestToken !== null && $audit->matchesGuestToken($guestToken);
    }
}
