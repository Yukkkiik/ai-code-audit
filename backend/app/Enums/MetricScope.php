<?php

namespace App\Enums;

/**
 * Cakupan baris metrik kode.
 */
enum MetricScope: string
{
    case File = 'file';
    case Function = 'function';
}
