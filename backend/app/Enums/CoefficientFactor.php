<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Facteur de risque auquel se rattache une ligne de la table `coefficients`.
 *
 * Deux formes : un coefficient par valeur (`key`) ou par tranche [`band_min`, `band_max`).
 * Le bonus-malus n'en fait pas partie : c'est une valeur saisie, dont seules les bornes
 * sont au barème (colonnes de `tariff_versions`).
 */
enum CoefficientFactor: string
{
    case Usage = 'usage';
    case Zone = 'zone';
    case Age = 'age';
    case License = 'license';

    public function isBanded(): bool
    {
        return match ($this) {
            self::Age, self::License => true,
            self::Usage, self::Zone => false,
        };
    }
}
