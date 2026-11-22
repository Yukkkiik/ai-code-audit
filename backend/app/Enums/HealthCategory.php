<?php

namespace App\Enums;

/**
 * Kategori skor kesehatan kode (D-10), disimpan di `audit_scans.health_category`.
 * Label tampilan (Baik, Sedang, Buruk) dipetakan di sisi klien.
 */
enum HealthCategory: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
}
