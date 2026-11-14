<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditsRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }
}
