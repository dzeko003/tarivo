<?php

declare(strict_types=1);

use App\Models\Quote;
use App\Models\TariffVersion;
use Illuminate\Database\QueryException;

/*
 * Règles du barème garanties par PostgreSQL lui-même (CHECK, EXCLUDE, clés étrangères),
 * indépendamment du code applicatif qui écrit en base.
 */

it('refuse deux versions publiées du même pays sur des périodes qui se chevauchent', function () {
    TariffVersion::factory()->published()->validBetween('2025-01-01', '2026-01-01')->create();

    expect(fn () => TariffVersion::factory()->published()->validBetween('2025-07-01', null)->create())
        ->toThrow(QueryException::class, 'tariff_versions_no_overlap');
});

it('accepte des versions publiées contiguës, la fin de l\'une étant le début de la suivante', function () {
    TariffVersion::factory()->published()->validBetween('2025-01-01', '2026-01-01')->create();

    $next = TariffVersion::factory()->published()->validBetween('2026-01-01', null)->create();

    $this->assertModelExists($next);
});

it('accepte un brouillon sur la période d\'une version publiée', function () {
    TariffVersion::factory()->published()->validBetween('2025-01-01', '2026-01-01')->create();

    $draft = TariffVersion::factory()->validBetween('2025-01-01', '2026-01-01')->create();

    $this->assertModelExists($draft);
});

it('refuse une période dont la fin précède le début', function () {
    expect(fn () => TariffVersion::factory()->validBetween('2026-01-01', '2025-01-01')->create())
        ->toThrow(QueryException::class, 'tariff_versions_period_check');
});

it('refuse deux primes de base dont les tranches de puissance se chevauchent pour une catégorie', function () {
    $version = TariffVersion::factory()->create();
    $version->basePremiums()->create(['vehicle_category' => 'promenade_affaires', 'cv_min' => 3, 'cv_max' => 7, 'amount' => 72_000]);

    expect(fn () => $version->basePremiums()->create(['vehicle_category' => 'promenade_affaires', 'cv_min' => 6, 'cv_max' => 11, 'amount' => 90_000]))
        ->toThrow(QueryException::class, 'base_premiums_no_overlap');
});

it('refuse deux tranches d\'âge qui se chevauchent dans une version', function () {
    $version = TariffVersion::factory()->create();
    $version->coefficients()->create(['factor' => 'age', 'band_min' => 25, 'band_max' => null, 'value' => '1.00', 'label' => '25 ans et plus']);

    expect(fn () => $version->coefficients()->create(['factor' => 'age', 'band_min' => 21, 'band_max' => 26, 'value' => '1.25', 'label' => '21 à 25 ans']))
        ->toThrow(QueryException::class, 'coefficients_no_band_overlap');
});

it('refuse un coefficient mal formé', function (array $attributes, string $constraint) {
    $version = TariffVersion::factory()->create();

    expect(fn () => $version->coefficients()->create([...$attributes, 'label' => 'test']))
        ->toThrow(QueryException::class, $constraint);
})->with([
    'zone avec une tranche' => [['factor' => 'zone', 'key' => 'douala', 'band_min' => 0, 'value' => '1.15'], 'coefficients_shape_check'],
    'âge sans tranche' => [['factor' => 'age', 'key' => '18', 'value' => '1.40'], 'coefficients_shape_check'],
    'plus de 4 décimales' => [['factor' => 'zone', 'key' => 'douala', 'value' => '1.15001'], 'coefficients_value_check'],
    'coefficient nul' => [['factor' => 'zone', 'key' => 'douala', 'value' => '0'], 'coefficients_value_check'],
]);

it('refuse une taxe qui a à la fois un taux et un montant fixe', function () {
    $version = TariffVersion::factory()->create();

    expect(fn () => $version->taxes()->create([
        'position' => 1, 'code' => 'tca', 'label' => 'TCA', 'rate' => '0.145', 'base' => 'prime_nette', 'fixed_amount' => 1_000,
    ]))->toThrow(QueryException::class, 'taxes_shape_check');
});

it('empêche de supprimer une version de tarif utilisée par un devis, même en brouillon', function () {
    $quote = Quote::factory()->for(TariffVersion::factory())->create();

    expect(fn () => $quote->tariffVersion->delete())
        ->toThrow(QueryException::class, 'quotes_tariff_version_id_foreign');
});
