<?php

namespace App\Enums;

/**
 * Alasan versi dependensi tidak dapat dipastikan.
 */
enum UnresolvedReason: string
{
    case RangeWithoutLockfile = 'range_without_lockfile';
    case NonRegistrySource = 'non_registry_source';
    case MissingVersion = 'missing_version';
    case UnsupportedLockfile = 'unsupported_lockfile';
}
