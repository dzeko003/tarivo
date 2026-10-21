<?php

declare(strict_types=1);

namespace App\Models;

use App\Pricing\Data\QuoteInput;
use App\Pricing\Enums\Usage;
use App\Pricing\Enums\VehicleCategory;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Devis enregistré, figé : saisie, version de tarif appliquée, détail du calcul et total TTC.
 *
 * `input` garde la saisie telle que l'API l'a validée (voir QuoteRequest::quoteInput()) ;
 * `breakdown` garde la réponse de calcul complète (PricingResultResource), lignes comprises.
 *
 * @property int $id
 * @property string $reference
 * @property int $tariff_version_id
 * @property int|null $user_id
 * @property CarbonImmutable $effective_date
 * @property int $duration_months
 * @property array{country: string, vehicle_category: string, fiscal_power: int, usage: string, driver_birth_date: string, license_date: string, zone: string, bonus_malus: string, effective_date: string, duration_months: int} $input
 * @property array<string, mixed> $breakdown
 * @property int $total_ttc
 * @property CarbonImmutable|null $created_at
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
     * Durées de contrat proposées, en mois. 1, 3 et 6 mois arriveront avec le prorata (étape 6).
     *
     * @var list<int>
     */
    public const AVAILABLE_DURATIONS = [12];

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
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Référence lisible attribuée à la création : DEV-<année>-<numéro sur 6 chiffres>.
     * Le numéro est l'identifiant, réservé dans la séquence avant l'insertion.
     */
    protected static function booted(): void
    {
        static::creating(function (Quote $quote): void {
            if ($quote->reference !== null) {
                return;
            }

            $quote->id ??= (int) DB::scalar("select nextval(pg_get_serial_sequence('quotes', 'id'))");
            $quote->reference = sprintf('DEV-%d-%06d', now()->year, $quote->id);
        });
    }

    /**
     * Données du moteur à partir d'une saisie validée par l'API (ou d'un devis enregistré).
     *
     * @param  array{vehicle_category: string, fiscal_power: int, usage: string, driver_birth_date: string, license_date: string, zone: string, bonus_malus: string, effective_date: string}  $input
     */
    public static function pricingInputFrom(array $input): QuoteInput
    {
        return new QuoteInput(
            vehicleCategory: VehicleCategory::from($input['vehicle_category']),
            fiscalPower: (int) $input['fiscal_power'],
            usage: Usage::from($input['usage']),
            driverBirthDate: new DateTimeImmutable($input['driver_birth_date']),
            licenseDate: new DateTimeImmutable($input['license_date']),
            zone: $input['zone'],
            bonusMalus: BigDecimal::of($input['bonus_malus']),
            effectiveDate: new DateTimeImmutable($input['effective_date']),
        );
    }

    /**
     * Données du moteur pour recalculer ce devis.
     */
    public function pricingInput(): QuoteInput
    {
        return self::pricingInputFrom($this->input);
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
