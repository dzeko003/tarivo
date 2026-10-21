<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\CoefficientFactor;
use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\TariffVersion;
use App\Pricing\Enums\Usage;
use App\Pricing\Enums\VehicleCategory;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listes du formulaire de devis, tirées de la version de tarif applicable à la date d'effet.
 */
class ReferenceDataController extends Controller
{
    /**
     * Données de référence du formulaire de devis.
     *
     * Les zones, les usages et les bornes du bonus-malus dépendent de la version de tarif publiée
     * à la date d'effet (aujourd'hui par défaut). Erreur 422 si aucun tarif n'est publié à cette date.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /** Code pays ISO à deux lettres (CM par défaut). */
            'country' => ['sometimes', 'string', 'size:2', 'alpha'],
            /** Date d'effet envisagée, AAAA-MM-JJ (aujourd'hui par défaut). */
            'effective_date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $country = strtoupper($validated['country'] ?? TariffVersion::DEFAULT_COUNTRY);
        $effectiveDate = isset($validated['effective_date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['effective_date'])->startOfDay()
            : CarbonImmutable::today();

        $version = TariffVersion::applicableOn($country, $effectiveDate);
        $grid = $version->toGrid();

        return response()->json([
            'data' => [
                'country' => $country,
                'effective_date' => $effectiveDate->toDateString(),
                'tariff_version' => [
                    'code' => $version->code,
                    'label' => $version->label,
                    'currency' => $version->currency,
                    'valid_from' => $version->valid_from->toDateString(),
                    'valid_to' => $version->valid_to?->toDateString(),
                ],
                /** @var list<array{value: string, label: string}> */
                'vehicle_categories' => array_map(
                    fn (VehicleCategory $category): array => ['value' => $category->value, 'label' => $category->label()],
                    VehicleCategory::cases(),
                ),
                /** @var list<array{value: string, label: string}> */
                'usages' => array_values(array_map(
                    fn (Usage $usage): array => ['value' => $usage->value, 'label' => $usage->label()],
                    array_filter(Usage::cases(), fn (Usage $usage): bool => $grid->usageCoefficientFor($usage) !== null),
                )),
                /** @var list<array{value: string, label: string}> */
                'zones' => $version->coefficients()
                    ->where('factor', CoefficientFactor::Zone)
                    ->orderBy('id')
                    ->get(['key', 'label'])
                    ->map(fn ($zone): array => ['value' => $zone->key, 'label' => $zone->label])
                    ->all(),
                /** Durées de contrat proposées, en mois. */
                'durations_months' => Quote::AVAILABLE_DURATIONS,
                /** Bornes du coefficient bonus-malus, en chaînes décimales. */
                'bonus_malus' => [
                    'min' => (string) $version->bonus_malus_min,
                    'max' => (string) $version->bonus_malus_max,
                ],
                'minimum_driver_age' => $version->minimum_driver_age,
                'minimum_license_age' => $version->minimum_license_age,
            ],
        ]);
    }
}
