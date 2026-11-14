<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * URL repositori Git ditolak oleh kebijakan keamanan. Pesannya aman ditampilkan ke pengguna.
 */
class InvalidRepositoryUrlException extends InvalidArgumentException {}
