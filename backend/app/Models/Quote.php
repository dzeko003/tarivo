<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Devis enregistré, figé : saisie, version de tarif appliquée, détail du calcul et total TTC.
 *
 * @property int $id
 * @property string $reference
 * @property int $tariff_version_id
 * @property int|null $user_id
 * @property CarbonImmutable $effective_date
 * @property int $duration_months
 * @property array<string, mixed> $input
 * @property array<string, mixed> $breakdown
 * @property int $total_ttc
 */
#[Fillable([
    'reference', 'tariff_version_id', 'user_id', 'effective_date', 'duration_months',
    'input', 'breakdown', 'total_ttc',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_date' => 'immutable_date',
            'duration_months' => 'integer',
            'input' => 'array',
            'breakdown' => 'array',
            'total_ttc' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TariffVersion, $this>
     */
    public function tariffVersion(): BelongsTo
    {
        return $this->belongsTo(TariffVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
