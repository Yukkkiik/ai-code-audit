<?php

namespace App\Enums;

/**
 * Status permintaan AI remediation.
 */
enum AiStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case RejectedValidation = 'rejected_validation';
}
