<?php

namespace App\Enums;

/**
 * Tool SAST penghasil temuan.
 */
enum FindingTool: string
{
    case Semgrep = 'semgrep';
    case Eslint = 'eslint';
}
