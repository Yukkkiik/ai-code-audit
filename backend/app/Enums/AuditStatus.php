<?php

namespace App\Enums;

/**
 * Status keseluruhan sesi audit.
 */
enum AuditStatus: string
{
    case Queued = 'queued';
    case Ingesting = 'ingesting';
    case Scanning = 'scanning';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';
}
