<?php

namespace App\Enums;

/**
 * Alasan pencocokan versi perbaikan tidak dapat dipastikan (status `undetermined`).
 *
 * `not_in_listed_ranges`: versi terpasang tidak ada di range maupun di daftar `versions` eksplisit yang tersimpan.
 * `unevaluable_range`: ada range yang tidak dapat dievaluasi, atau daftar `versions` tidak diketahui (NULL).
 * `no_range_data`: tidak ada range SEMVER/ECOSYSTEM dan tidak ada `versions` eksplisit.
 */
enum FixMatchReason: string
{
    case DetailUnavailable = 'detail_unavailable';
    case NoRangeData = 'no_range_data';
    case NotInListedRanges = 'not_in_listed_ranges';
    case UnevaluableRange = 'unevaluable_range';
    case InvalidVersion = 'invalid_version';
}
