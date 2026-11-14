<?php

namespace App\Enums;

/**
 * Status pengecekan dependensi ke OSV.dev.
 */
enum OsvQueryStatus: string
{
    case NotQueried = 'not_queried';
    case Queried = 'queried';
    case Failed = 'failed';
}
