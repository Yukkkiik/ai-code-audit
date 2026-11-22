<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Output tool SAST tidak dapat dipakai (JSON rusak/terpotong, struktur tidak sesuai).
 * Pesan aman untuk dicatat; tidak memuat isi output tool.
 */
class SastToolException extends RuntimeException {}
