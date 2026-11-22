<?php

namespace App\Enums;

/**
 * Petunjuk besarnya lompatan dari versi terpasang ke versi perbaikan. Hanya petunjuk, bukan rekomendasi:
 * `prerelease` berarti salah satu versi (terpasang atau perbaikan) adalah prerelease.
 */
enum VersionChange: string
{
    case Patch = 'patch';
    case Minor = 'minor';
    case Major = 'major';
    case Prerelease = 'prerelease';
}
