<?php

namespace App\Enums;

/**
 * Hasil pencocokan versi terpasang dengan range afektasi OSV. Sengaja tidak ada "not_affected": advisori yang
 * dikembalikan OSV untuk sebuah versi tidak boleh dinyatakan tidak berlaku hanya karena range yang kita simpan
 * tidak memuat versi itu (OSV dapat mencocokkan lewat daftar `versions` eksplisit yang tidak kita simpan). Sejak A-38 daftar itu disimpan dan ikut dicocokkan; yang tidak ada di range maupun di daftar tetap `undetermined`.
 */
enum FixMatchStatus: string
{
    case FixAvailable = 'fix_available';
    case NoFixRecorded = 'no_fix_recorded';
    case Undetermined = 'undetermined';
}
