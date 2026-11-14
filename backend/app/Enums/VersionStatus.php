<?php

namespace App\Enums;

/**
 * Kepastian versi dependensi.
 */
enum VersionStatus: string
{
    case Exact = 'exact';
    case Unresolved = 'unresolved';
}
