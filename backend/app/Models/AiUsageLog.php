<?php

namespace App\Models;

use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penghitung pemakaian AI harian per user (dasar kuota).
 */
#[Fillable([
    'user_id', 'usage_date', 'request_count', 'success_count', 'failed_count', 'fallback_count',
])]
class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory;

    /**
     * Nilai awal yang sama dengan default kolom di database.
     *
     * @var array<string, int>
     */
    protected $attributes = [
        'request_count' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'fallback_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'request_count' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
            'fallback_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
