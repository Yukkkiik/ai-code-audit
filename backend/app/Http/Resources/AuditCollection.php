<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Daftar audit paginated dengan `meta` sesuai API_CONTRACT.md §1 (tanpa `links`).
 */
class AuditCollection extends ResourceCollection
{
    /**
     * @var string
     */
    public $collects = AuditResource::class;

    /**
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'current_page' => $paginated['current_page'],
                'per_page' => $paginated['per_page'],
                'total' => $paginated['total'],
                'last_page' => $paginated['last_page'],
            ],
        ];
    }
}
