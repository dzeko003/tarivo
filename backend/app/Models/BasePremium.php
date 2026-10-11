<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\TariffGridRowObserver;
use App\Pricing\Data\BasePremiumBand;
use App\Pricing\Enums\VehicleCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prime de base annuelle d'une catégorie de véhicule, par tranche de puissance fiscale [cv_min, cv_max).
 *
 * @property int $id
 * @property int $tariff_version_id
 * @property VehicleCategory $vehicle_category
 * @property int $cv_min
 * @property int|null $cv_max
 * @property int $amount
 */
#[Table('base_premiums')]
#[Fillable(['vehicle_category', 'cv_min', 'cv_max', 'amount'])]
#[WithoutTimestamps]
#[ObservedBy(TariffGridRowObserver::class)]
class BasePremium extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vehicle_category' => VehicleCategory::class,
            'cv_min' => 'integer',
            'cv_max' => 'integer',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TariffVersion, $this>
     */
    public function tariffVersion(): BelongsTo
    {
        return $this->belongsTo(TariffVersion::class);
    }

    public function toBand(): BasePremiumBand
    {
        return new BasePremiumBand($this->vehicle_category, $this->cv_min, $this->cv_max, $this->amount);
    }
}
