<?php

declare(strict_types=1);

namespace App\Pricing;

use App\Pricing\Data\TariffGrid;
use App\Pricing\Enums\Usage;
use App\Pricing\Enums\VehicleCategory;
use App\Pricing\Exceptions\InvalidTariffGrid;

/**
 * Vérifie qu'un barème couvre tous les devis possibles, avant sa publication (il ne sera plus modifiable).
 *
 * Chaque grille par tranches doit être continue, sans trou ni chevauchement, et se terminer
 * par une tranche sans limite. Chaque usage doit avoir un coefficient, et au moins une zone doit exister.
 */
final class TariffGridValidator
{
    /**
     * @throws InvalidTariffGrid
     */
    public function validate(TariffGrid $grid): void
    {
        $problems = [];

        foreach (VehicleCategory::cases() as $category) {
            $bands = array_values(array_filter($grid->basePremiums, fn ($band) => $band->category === $category));
            $problems = [...$problems, ...self::coverageProblems(
                array_map(fn ($band) => [$band->cvMin, $band->cvMax], $bands),
                from: 1,
                what: "Primes de base « {$category->label()} »",
                unit: 'CV',
            )];
        }

        foreach (Usage::cases() as $usage) {
            if ($grid->usageCoefficientFor($usage) === null) {
                $problems[] = "Coefficient manquant pour l'usage « {$usage->label()} ».";
            }
        }

        $problems = [...$problems, ...self::coverageProblems(
            array_map(fn ($band) => [$band->min, $band->max], $grid->ageBands),
            from: $grid->minimumDriverAge,
            what: "Tranches d'âge",
            unit: 'ans',
        )];

        $problems = [...$problems, ...self::coverageProblems(
            array_map(fn ($band) => [$band->min, $band->max], $grid->licenseBands),
            from: 0,
            what: 'Tranches d\'ancienneté du permis',
            unit: 'ans',
        )];

        if ($grid->zoneCoefficients === []) {
            $problems[] = 'Aucune zone géographique.';
        }

        if ($problems !== []) {
            throw new InvalidTariffGrid($problems);
        }
    }

    /**
     * Les tranches [min, max) doivent couvrir [from, +∞) sans trou ni chevauchement.
     *
     * @param  list<array{int, int|null}>  $bands
     * @return list<string>
     */
    private static function coverageProblems(array $bands, int $from, string $what, string $unit): array
    {
        if ($bands === []) {
            return ["{$what} : aucune tranche."];
        }

        usort($bands, fn (array $a, array $b) => $a[0] <=> $b[0]);

        if ($bands[0][0] > $from) {
            return ["{$what} : rien n'est prévu de {$from} à ".($bands[0][0] - 1)." {$unit}."];
        }

        $problems = [];
        for ($i = 1, $count = count($bands); $i < $count; $i++) {
            $previousMax = $bands[$i - 1][1];
            $min = $bands[$i][0];

            if ($previousMax === null || $min < $previousMax) {
                $problems[] = "{$what} : des tranches se chevauchent à partir de {$min} {$unit}.";
            } elseif ($min > $previousMax) {
                $problems[] = "{$what} : rien n'est prévu de {$previousMax} à ".($min - 1)." {$unit}.";
            }
        }

        if ($bands[array_key_last($bands)][1] !== null) {
            $problems[] = "{$what} : la dernière tranche doit être sans limite.";
        }

        return $problems;
    }
}
