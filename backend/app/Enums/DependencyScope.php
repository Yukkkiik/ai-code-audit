<?php

namespace App\Enums;

/**
 * Jenis dependensi dari manifest/lockfile.
 */
enum DependencyScope: string
{
    case Production = 'production';
    case Development = 'development';
    case Optional = 'optional';
    case Peer = 'peer';
}
