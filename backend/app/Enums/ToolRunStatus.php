<?php

namespace App\Enums;

/**
 * Hasil eksekusi satu tool SAST.
 */
enum ToolRunStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
