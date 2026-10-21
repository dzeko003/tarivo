<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AsBigDecimal;
use App\Enums\CoefficientFactor;
use App\Enums\TariffVersionStatus;
use App\Exceptions\InvalidTariffTransition;
use App\Exceptions\NoApplicableTariffVersion;
use App\Observers\TariffVersionObserver;
use App\Pricing\Data\TariffGrid;
use App\Pricing\Enums\VehicleCategory;
use App\Pricing\Exceptions\InvalidTariffGrid;
use App\Pricing\TariffGridValidator;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Database\Factories\TariffVersionFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une version du barème de Sahel Assurances pour un pays et une période [valid_from, valid_to).
 *
 * Cycle de vie : brouillon (modifiable) → publiée (figée, applicable aux devis) → archivée.
 * Les seuls changements permis sur une version publiée passent par archive() et closeOn() ;
 * tout le reste est bloqué par TariffVersionObserver et par des triggers PostgreSQL.
 *
 * @property int $id
 * @property string $code
 * @property string $country
 * @property string $currency
 * @property string $label
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_to
 * @property TariffVersionStatus $status
 * @property CarbonImmutable|null $published_at
 * @property int $policy_fee
 * @property BigDecimal $bonus_malus_min
 * @property BigDecimal $bonus_malus_max
 * @property int $minimum_driver_age
 * @property int $minimum_license_age
 */
#[Fillable([
    'code', 'country', 'currency', 'label', 'valid_from', 'valid_to', 'status', 'published_at',
    'policy_fee', 'bonus_malus_min', 'bonus_malus_max', 'minimum_driver_age', 'minimum_license_age',
])]
#[ObservedBy(TariffVersionObserver::class)]
class TariffVersion extends Model
{
    /** @use HasFactory<TariffVersionFactory> */
    use HasFactory;

    /** Pays retenu quand une requête n'en précise pas (Sahel Assurances opère au Cameroun). */
    public const DEFAULT_COUNTRY = 'CM';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'status' => TariffVersionStatus::class,
            'published_at' => 'immutable_datetime',
            'policy_fee' => 'integer',
            'bonus_malus_min' => AsBigDecimal::class,
            'bonus_malus_max' => AsBigDecimal::class,
            'minimum_driver_age' => 'integer',
            'minimum_license_age' => 'integer',
        ];
    }

    /**
     * Version publiée qui s'applique à un devis : valid_from <= date d'effet < valid_to.
     * La contrainte d'exclusion de la base garantit qu'il y en a au plus une par pays.
     *
     * @throws NoApplicableTariffVersion
     */
    public static function applicableOn(string $country, DateTimeInterface $effectiveDate): self
    {
        $day = $effectiveDate->format('Y-m-d');

        return self::query()
            ->published()
            ->where('country', $country)
            ->where('valid_from', '<=', $day)
            ->where(fn (Builder $query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', $day))
            ->first()
            ?? throw new NoApplicableTariffVersion($country, $effectiveDate);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', TariffVersionStatus::Published);
    }

    /**
     * Publie un brouillon : il devient applicable et ne peut plus être modifié.
     *
     * @throws InvalidTariffTransition si ce n'est pas un brouillon ou si sa période chevauche une version publiée
     * @throws InvalidTariffGrid si le barème ne couvre pas tous les devis possibles
     */
    public function publish(?DateTimeInterface $publishedAt = null): void
    {
        if ($this->status !== TariffVersionStatus::Draft) {
            throw InvalidTariffTransition::notDraft($this);
        }

        $this->load(['basePremiums', 'coefficients', 'taxes']);
        (new TariffGridValidator)->validate($this->toGrid());

        $overlapping = self::query()
            ->published()
            ->where('country', $this->country)
            ->whereRaw("daterange(valid_from, valid_to, '[)') && daterange(?::date, ?::date, '[)')", [
                $this->valid_from->format('Y-m-d'),
                $this->valid_to?->format('Y-m-d'),
            ])
            ->first();
        if ($overlapping !== null) {
            throw InvalidTariffTransition::overlaps($this, $overlapping);
        }

        $this->status = TariffVersionStatus::Published;
        $this->published_at = CarbonImmutable::instance($publishedAt ?? now());
        $this->save();
    }

    /**
     * Retire une version publiée (remplacée par une correction) ; elle reste disponible
     * pour recalculer les devis qui l'utilisent.
     *
     * @throws InvalidTariffTransition
     */
    public function archive(): void
    {
        if ($this->status !== TariffVersionStatus::Published) {
            throw InvalidTariffTransition::notPublished($this);
        }

        $this->status = TariffVersionStatus::Archived;
        $this->save();
    }

    /**
     * Ferme la période ouverte d'une version publiée, pour laisser place à la version suivante.
     * Un devis déjà enregistré doit rester dans la période de sa version.
     *
     * @throws InvalidTariffTransition
     */
    public function closeOn(DateTimeInterface $validTo): void
    {
        if ($this->status !== TariffVersionStatus::Published) {
            throw InvalidTariffTransition::notPublished($this);
        }
        if ($this->valid_to !== null) {
            throw InvalidTariffTransition::alreadyClosed($this);
        }
        if ($validTo->format('Y-m-d') <= $this->valid_from->format('Y-m-d')) {
            throw InvalidTariffTransition::invalidEnd($this, $validTo);
        }
        if ($this->quotes()->where('effective_date', '>=', $validTo->format('Y-m-d'))->exists()) {
            throw InvalidTariffTransition::quotesAfterEnd($this, $validTo);
        }

        $this->valid_to = CarbonImmutable::instance($validTo)->startOfDay();
        $this->save();
    }

    /**
     * @return HasMany<BasePremium, $this>
     */
    public function basePremiums(): HasMany
    {
        return $this->hasMany(BasePremium::class);
    }

    /**
     * @return HasMany<Coefficient, $this>
     */
    public function coefficients(): HasMany
    {
        return $this->hasMany(Coefficient::class);
    }

    /**
     * @return HasMany<Tax, $this>
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Convertit la version et ses grilles en barème pour le moteur (seule passerelle Eloquent → Pricing).
     *
     * Les listes sont triées de façon stable : catégorie (ordre de l'enum) puis puissance,
     * tranches par borne basse, taxes par position (l'ordre d'application).
     */
    public function toGrid(): TariffGrid
    {
        $this->loadMissing(['basePremiums', 'coefficients', 'taxes']);

        $categoryOrder = array_flip(array_map(fn (VehicleCategory $c) => $c->value, VehicleCategory::cases()));
        $coefficients = fn (CoefficientFactor $factor) => $this->coefficients->where('factor', $factor);

        return new TariffGrid(
            version: $this->code,
            currency: $this->currency,
            basePremiums: $this->basePremiums
                ->sortBy([
                    fn (BasePremium $a, BasePremium $b) => $categoryOrder[$a->vehicle_category->value] <=> $categoryOrder[$b->vehicle_category->value],
                    fn (BasePremium $a, BasePremium $b) => $a->cv_min <=> $b->cv_min,
                ])
                ->map(fn (BasePremium $row) => $row->toBand())
                ->values()
                ->all(),
            usageCoefficients: $coefficients(CoefficientFactor::Usage)
                ->mapWithKeys(fn (Coefficient $row) => [$row->key => $row->toKeyedCoefficient()])
                ->all(),
            ageBands: $coefficients(CoefficientFactor::Age)
                ->sortBy('band_min')
                ->map(fn (Coefficient $row) => $row->toBand())
                ->values()
                ->all(),
            licenseBands: $coefficients(CoefficientFactor::License)
                ->sortBy('band_min')
                ->map(fn (Coefficient $row) => $row->toBand())
                ->values()
                ->all(),
            zoneCoefficients: $coefficients(CoefficientFactor::Zone)
                ->mapWithKeys(fn (Coefficient $row) => [$row->key => $row->toKeyedCoefficient()])
                ->all(),
            bonusMalusMin: $this->bonus_malus_min,
            bonusMalusMax: $this->bonus_malus_max,
            policyFee: $this->policy_fee,
            taxes: $this->taxes
                ->sortBy('position')
                ->map(fn (Tax $row) => $row->toRule())
                ->values()
                ->all(),
            minimumDriverAge: $this->minimum_driver_age,
            minimumLicenseAge: $this->minimum_license_age,
        );
    }
}
