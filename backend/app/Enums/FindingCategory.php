<?php

namespace App\Enums;

/**
 * Kategori temuan SAST.
 */
enum FindingCategory: string
{
    case Security = 'security';
    case Quality = 'quality';
    case BestPractice = 'best_practice';
}
