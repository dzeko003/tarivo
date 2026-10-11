<?php

declare(strict_types=1);

use App\Pricing\Data\Band;
use App\Pricing\Data\TariffGrid;
use App\Pricing\Enums\Usage;
use App\Pricing\Enums\VehicleCategory;
use App\Pricing\Exceptions\InvalidTariffGrid;
use App\Pricing\TariffGridValidator;
use Brick\Math\BigDecimal;
use Tests\Fixtures\SahelTariff2025;

/**
 * Barème 2025 dont on remplace certaines grilles.
 *
 * @param  array<string, mixed>  $overrides  nom du paramètre de TariffGrid => valeur
 */
function gridWith(array $overrides): TariffGrid
{
    return new TariffGrid(...[...get_object_vars(SahelTariff2025::grid()), ...$overrides]);
}

/**
 * @return list<string> problèmes trouvés ([] si le barème est complet)
 */
function gridProblems(TariffGrid $grid): array
{
    try {
        (new TariffGridValidator)->validate($grid);
    } catch (InvalidTariffGrid $e) {
        return $e->problems;
    }

    return [];
}

function withoutBasePremium(VehicleCategory $category, ?int $cvMin = null): array
{
    return array_values(array_filter(
        SahelTariff2025::grid()->basePremiums,
        fn ($band) => ! ($band->category === $category && ($cvMin === null || $band->cvMin === $cvMin)),
    ));
}

it('accepte un barème complet', function () {
    expect(gridProblems(SahelTariff2025::grid()))->toBe([]);
});

it('refuse un barème incomplet, avec un message qui situe le problème', function (Closure $overrides, string $problem) {
    expect(gridProblems(gridWith($overrides())))->toBe([$problem]);
})->with([
    'trou dans les puissances' => [
        fn () => ['basePremiums' => withoutBasePremium(VehicleCategory::PrivateAndBusiness, cvMin: 7)],
        "Primes de base « Promenade et affaires » : rien n'est prévu de 7 à 10 CV.",
    ],
    'catégorie sans prime de base' => [
        fn () => ['basePremiums' => withoutBasePremium(VehicleCategory::TwoWheeler)],
        'Primes de base « Deux-roues » : aucune tranche.',
    ],
    'première tranche de puissance après 1 CV' => [
        fn () => ['basePremiums' => withoutBasePremium(VehicleCategory::TwoWheeler, cvMin: 1)],
        "Primes de base « Deux-roues » : rien n'est prévu de 1 à 2 CV.",
    ],
    'aucune tranche dès l\'âge minimum' => [
        fn () => ['ageBands' => array_slice(SahelTariff2025::grid()->ageBands, 1)],
        "Tranches d'âge : rien n'est prévu de 18 à 20 ans.",
    ],
    'tranches d\'âge qui se chevauchent' => [
        fn () => ['ageBands' => [...SahelTariff2025::grid()->ageBands, new Band(70, 75, BigDecimal::of('1.20'), 'test')]],
        "Tranches d'âge : des tranches se chevauchent à partir de 70 ans.",
    ],
    'dernière tranche de permis bornée' => [
        fn () => ['licenseBands' => [
            ...array_slice(SahelTariff2025::grid()->licenseBands, 0, 2),
            new Band(3, 60, BigDecimal::of('1.00'), '3 à 59 ans'),
        ]],
        "Tranches d'ancienneté du permis : la dernière tranche doit être sans limite.",
    ],
    'usage sans coefficient' => [
        fn () => ['usageCoefficients' => [Usage::Personal->value => SahelTariff2025::grid()->usageCoefficients[Usage::Personal->value]]],
        "Coefficient manquant pour l'usage « Usage professionnel ».",
    ],
    'aucune zone' => [
        fn () => ['zoneCoefficients' => []],
        'Aucune zone géographique.',
    ],
]);

it('remonte tous les problèmes en une seule fois', function () {
    $grid = gridWith(['zoneCoefficients' => [], 'licenseBands' => []]);

    expect(gridProblems($grid))->toBe([
        "Tranches d'ancienneté du permis : aucune tranche.",
        'Aucune zone géographique.',
    ]);
});
