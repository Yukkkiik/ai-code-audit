<?php

namespace App\Enums;

/**
 * Bahasa kode yang dianalisis (JS/TS saja).
 */
enum CodeLanguage: string
{
    case Javascript = 'javascript';
    case Typescript = 'typescript';
}
