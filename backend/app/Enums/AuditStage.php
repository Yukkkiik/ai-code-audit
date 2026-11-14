<?php

namespace App\Enums;

/**
 * Tahap pemindaian pada sebuah audit (bukan kolom database; dipetakan ke kolom status & error).
 */
enum AuditStage: string
{
    case Sast = 'sast';
    case Sca = 'sca';

    public function statusColumn(): string
    {
        return $this->value.'_status';
    }

    public function errorColumn(): string
    {
        return $this->value.'_error';
    }
}
