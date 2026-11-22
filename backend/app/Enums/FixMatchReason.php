<?php

namespace App\Enums;

/**
 * Alasan pencocokan versi perbaikan tidak dapat dipastikan (status `undetermined`).
 */
enum FixMatchReason: string
{
    case DetailUnavailable = 'detail_unavailable';
    case NoRangeData = 'no_range_data';
    case NotInListedRanges = 'not_in_listed_ranges';
    case UnevaluableRange = 'unevaluable_range';
    case InvalidVersion = 'invalid_version';
}
