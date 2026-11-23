<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Daftar hasil SAST paginated: `meta` berisi paginasi (API_CONTRACT.md §1, tanpa `links`) dan `scan`
 * (status, pesan, skor, status tool, catatan) agar daftar kosong tidak terbaca sebagai "tidak ada masalah".
 */
abstract class SastListCollection extends ResourceCollection
{
    /**
     * @param  array<string, mixed>  $scan
     */
    public function __construct($resource, private readonly array $scan)
    {
        parent::__construct($resource);
    }

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
                'scan' => $this->scan,
            ],
        ];
    }
}
