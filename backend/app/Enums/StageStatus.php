<?php

namespace App\Enums;

/**
 * Status tahap SAST / SCA pada sebuah audit.
 */
enum StageStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
