<?php

namespace App\Enums;

/**
 * Sumber repositori yang diaudit.
 */
enum SourceType: string
{
    case Zip = 'zip';
    case Git = 'git';
}
