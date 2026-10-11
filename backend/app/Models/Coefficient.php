<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AsBigDecimal;
use App\Enums\CoefficientFactor;
use App\Observers\TariffGridRowObserver;
use App\Pricing\Data\Band;
use App\Pricing\Data\KeyedCoefficient;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Coefficient d'un facteur de risque : par valeur (`key`) ou par tranche [`band_min`, `band_max`).
 *
 * @property int $id
 * @property int $tariff_version_id
 * @property CoefficientFactor $factor
 * @property string|null $key
 * @property int|null $band_min
 * @property int|null $band_max
 * @property BigDecimal $value
 * @property string $label
 */
#[Fillable(['factor', 'key', 'band_min', 'band_max', 'value', 'label'])]
#[WithoutTimestamps]
#[ObservedBy(TariffGridRowObserver::class)]
class Coefficient extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'factor' => CoefficientFactor::class,
            'band_min' => 'integer',
            'band_max' => 'integer',
            'value' => AsBigDecimal::class,
        ];
    }

    /**
     * @return BelongsTo<TariffVersion, $this>
     */
    public function tariffVersion(): BelongsTo
    {
        return $this->belongsTo(TariffVersion::class);
    }

    public function toBand(): Band
    {
        if (! $this->factor->isBanded() || $this->band_min === null) {
            throw new LogicException("Le coefficient {$this->id} ({$this->factor->value}) n'est pas une tranche.");
        }

        return new Band($this->band_min, $this->band_max, $this->value, $this->label);
    }

    public function toKeyedCoefficient(): KeyedCoefficient
    {
        if ($this->factor->isBanded()) {
            throw new LogicException("Le coefficient {$this->id} ({$this->factor->value}) est une tranche.");
        }

        return new KeyedCoefficient($this->value, $this->label);
    }
}
