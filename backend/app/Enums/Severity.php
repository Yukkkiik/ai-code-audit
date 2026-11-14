<?php

namespace App\Enums;

/**
 * Tingkat keparahan temuan SAST.
 */
enum Severity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';
}
