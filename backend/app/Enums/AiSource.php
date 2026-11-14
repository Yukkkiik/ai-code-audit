<?php

namespace App\Enums;

/**
 * Sumber hasil remediation.
 */
enum AiSource: string
{
    case Llm = 'llm';
    case StaticTemplate = 'static_template';
}
