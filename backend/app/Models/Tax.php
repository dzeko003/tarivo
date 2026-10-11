<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AsBigDecimal;
use App\Observers\TariffGridRowObserver;
use App\Pricing\Data\TaxRule;
use App\Pricing\Enums\TaxBase;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Taxe ou contribution d'une version de tarif : un taux sur une assiette, ou un montant fixe.
 * `position` donne l'ordre d'application.
 *
 * @property int $id
 * @property int $tariff_version_id
 * @property int $position
 * @property string $code
 * @property string $label
 * @property BigDecimal|null $rate
 * @property TaxBase|null $base
 * @property int|null $fixed_amount
 */
#[Fillable(['position', 'code', 'label', 'rate', 'base', 'fixed_amount'])]
#[WithoutTimestamps]
#[ObservedBy(TariffGridRowObserver::class)]
class Tax extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'rate' => AsBigDecimal::class,
            'base' => TaxBase::class,
            'fixed_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TariffVersion, $this>
     */
    public function tariffVersion(): BelongsTo
    {
        return $this->belongsTo(TariffVersion::class);
    }

    public function toRule(): TaxRule
    {
        return new TaxRule($this->code, $this->label, $this->rate, $this->base, $this->fixed_amount);
    }
}
