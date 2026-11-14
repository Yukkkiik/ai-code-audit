<?php

namespace App\Enums;

/**
 * Jenis berkas sumber data dependensi.
 */
enum ManifestType: string
{
    case PackageJson = 'package_json';
    case PackageLockV2 = 'package_lock_v2';
    case PackageLockV3 = 'package_lock_v3';
}
