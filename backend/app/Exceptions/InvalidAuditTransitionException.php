<?php

namespace App\Exceptions;

use LogicException;

/**
 * Perubahan status audit yang melanggar alur di FLOWS.md.
 * Menandakan kesalahan program (bukan input pengguna), sehingga tidak ditampilkan ke client.
 */
class InvalidAuditTransitionException extends LogicException {}
