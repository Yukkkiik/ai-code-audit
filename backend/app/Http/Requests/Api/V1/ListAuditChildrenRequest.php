<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AuditScan;
use App\Services\Audit\AuditAccessGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dasar untuk daftar resource turunan audit. Akses diperiksa SEBELUM validasi (authorize() berjalan lebih
 * dulu), sehingga filter yang salah tidak membedakan audit yang ada dari yang tidak.
 */
abstract class ListAuditChildrenRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AuditScan $audit */
        $audit = $this->route('audit');

        app(AuditAccessGuard::class)->authorize($this, $audit);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function paginationRules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.ListAuditsRequest::MAX_PER_PAGE],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', ListAuditsRequest::DEFAULT_PER_PAGE);
    }
}
