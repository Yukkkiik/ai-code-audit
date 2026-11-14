<?php

namespace App\Enums;

/**
 * Hubungan dependensi terhadap proyek root.
 */
enum DependencyRelation: string
{
    case Direct = 'direct';
    case Transitive = 'transitive';
}
