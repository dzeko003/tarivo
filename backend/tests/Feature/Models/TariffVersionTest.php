<?php

declare(strict_types=1);

use App\Enums\TariffVersionStatus;
use App\Exceptions\InvalidTariffTransition;
use App\Exceptions\NoApplicableTariffVersion;
use App\Models\Quote;
use App\Models\TariffVersion;
use App\Pricing\Exceptions\InvalidTariffGrid;
use App\Pricing\Pricer;
use Tests\Fixtures\SahelTariff2025;

/*
 * Le barème chargé depuis la base doit donner exactement les mêmes primes que les oracles
 * indépendants : c'est ce qui garantit que la base ne perd rien (échelle des coefficients comprise).
 */

dataset('reference_profiles_2025', fn () => referenceProfiles('reference_profiles_2025.csv'));
dataset('reference_profiles_2026', fn () => referenceProfiles('reference_profiles_2026.csv'));

it('reconstruit depuis la base un barème 2025 identique au barème de référence', function () {
    $version = seededTariff('sahel_rc_2025');

    $grid = $version->toGrid();

    expect($grid)->toEqual(SahelTariff2025::grid());
});

it('calcule depuis la base la prime attendue des profils de référence 2025', function (array $profile) {
    $grid = seededTariff('sahel_rc_2025')->toGrid();

    $result = (new Pricer)->price(quoteInputFromProfile($profile), $grid);

    expect($result->netPremium)->toBe((int) $profile['expected_net_premium'])
        ->and($result->total)->toBe((int) $profile['expected_total']);
})->with('reference_profiles_2025');

it('calcule depuis la base la prime attendue des profils de référence 2026', function (array $profile) {
    $grid = seededTariff('sahel_rc_2026')->toGrid();

    $result = (new Pricer)->price(quoteInputFromProfile($profile), $grid);

    expect($result->netPremium)->toBe((int) $profile['expected_net_premium'])
        ->and($result->total)->toBe((int) $profile['expected_total']);
})->with('reference_profiles_2026');

it('applique les taxes 2026 dans leur ordre, droit de timbre fixe en dernier', function () {
    $grid = seededTariff('sahel_rc_2026')->toGrid();

    $result = (new Pricer)->price(quoteInputFromProfile([
        'category' => 'promenade_affaires', 'fiscal_power' => '7', 'usage' => 'personnel',
        'birth_date' => '1985-06-15', 'license_date' => '2005-09-01', 'zone' => 'autres_villes',
        'bonus_malus' => '1.00', 'effective_date' => '2026-07-01',
    ]), $grid);

    $taxLines = array_slice($result->lines, -3);
    expect(array_map(fn ($line) => [$line->code, $line->label, (string) $line->amount], $taxLines))->toBe([
        ['tca', "Taxe sur les contrats d'assurance (14,5 % de 100 000 XAF)", '14500'],
        ['fga', 'Contribution au fonds de garantie automobile (2,5 % de 94 000 XAF)', '2350'],
        ['timbre', 'Droit de timbre', '1000'],
    ]);
});

describe('sélection par date d\'effet', function () {
    it('choisit la version publiée dont la période [valid_from, valid_to) contient la date d\'effet', function (string $effectiveDate, string $expectedCode) {
        seededTariff('sahel_rc_2025');
        seededTariff('sahel_rc_2026');

        $version = TariffVersion::applicableOn('CM', new DateTimeImmutable($effectiveDate));

        expect($version->code)->toBe($expectedCode);
    })->with([
        'premier jour de 2025' => ['2025-01-01', 'SAHEL-RC-2025'],
        'dernier jour de 2025' => ['2025-12-31', 'SAHEL-RC-2025'],
        'premier jour de 2026 (fin exclue de 2025)' => ['2026-01-01', 'SAHEL-RC-2026'],
        'période ouverte de 2026' => ['2031-06-15', 'SAHEL-RC-2026'],
    ]);

    it('refuse une date d\'effet antérieure à toute version publiée', function () {
        seededTariff('sahel_rc_2025');

        expect(fn () => TariffVersion::applicableOn('CM', new DateTimeImmutable('2024-12-31')))
            ->toThrow(NoApplicableTariffVersion::class, "Aucun tarif publié pour le pays CM à la date d'effet du 31/12/2024.");
    });

    it('ignore les brouillons, les versions archivées et les autres pays', function () {
        TariffVersion::factory()->validBetween('2025-01-01', null)->create();
        TariffVersion::factory()->archived()->validBetween('2025-01-01', null)->create();
        TariffVersion::factory()->published()->validBetween('2025-01-01', null)->create(['country' => 'SN', 'currency' => 'XOF']);

        expect(fn () => TariffVersion::applicableOn('CM', new DateTimeImmutable('2025-07-01')))
            ->toThrow(NoApplicableTariffVersion::class);
    });
});

describe('publication', function () {
    it('publie un brouillon complet à la date indiquée', function () {
        $version = seededTariff('sahel_rc_2025', asDraft: true);

        $version->publish(new DateTimeImmutable('2024-12-20 09:30:00'));

        expect($version->fresh())
            ->status->toBe(TariffVersionStatus::Published)
            ->published_at->toDateTimeString()->toBe('2024-12-20 09:30:00');
    });

    it('refuse de publier un barème incomplet et le laisse en brouillon', function () {
        $version = seededTariff('sahel_rc_2025', asDraft: true);
        $version->coefficients()->where('factor', 'zone')->delete();

        expect(fn () => $version->publish())->toThrow(InvalidTariffGrid::class, 'Aucune zone géographique.');
        expect($version->fresh()->status)->toBe(TariffVersionStatus::Draft);
    });

    it('refuse de publier une version qui chevauche une version publiée du même pays', function () {
        seededTariff('sahel_rc_2025');
        $draft = seededTariff('sahel_rc_2026', asDraft: true);
        $draft->update(['valid_from' => '2025-10-01']);

        expect(fn () => $draft->publish())
            ->toThrow(InvalidTariffTransition::class, 'La période de la version SAHEL-RC-2026 chevauche celle de la version publiée SAHEL-RC-2025.');
        expect($draft->fresh()->status)->toBe(TariffVersionStatus::Draft);
    });

    it('refuse de republier une version déjà publiée', function () {
        $version = seededTariff('sahel_rc_2025');

        expect(fn () => $version->publish())
            ->toThrow(InvalidTariffTransition::class, 'Seul un brouillon peut être publié : la version SAHEL-RC-2025 est publiée.');
    });
});

describe('archivage et clôture', function () {
    it('archive une version publiée', function () {
        $version = seededTariff('sahel_rc_2025');

        $version->archive();

        expect($version->fresh()->status)->toBe(TariffVersionStatus::Archived);
    });

    it('refuse d\'archiver un brouillon', function () {
        $version = TariffVersion::factory()->create(['code' => 'BROUILLON']);

        expect(fn () => $version->archive())
            ->toThrow(InvalidTariffTransition::class, "La version BROUILLON n'est pas publiée.");
    });

    it('clôture la période ouverte d\'une version publiée pour laisser place à la suivante', function () {
        $version = seededTariff('sahel_rc_2026');

        $version->closeOn(new DateTimeImmutable('2027-01-01'));

        expect($version->fresh()->valid_to->toDateString())->toBe('2027-01-01');
    });

    it('refuse de clôturer une période déjà fermée', function () {
        $version = seededTariff('sahel_rc_2025');

        expect(fn () => $version->closeOn(new DateTimeImmutable('2025-06-01')))
            ->toThrow(InvalidTariffTransition::class, 'La période de la version SAHEL-RC-2025 est déjà fermée.');
    });

    it('refuse une clôture qui laisserait un devis enregistré hors de la période de sa version', function () {
        $version = seededTariff('sahel_rc_2026');
        Quote::factory()->for($version)->create(['effective_date' => '2026-09-01']);

        expect(fn () => $version->closeOn(new DateTimeImmutable('2026-09-01')))
            ->toThrow(InvalidTariffTransition::class, 'Des devis de la version SAHEL-RC-2026 prennent effet à partir du 01/09/2026');
        expect($version->fresh()->valid_to)->toBeNull();
    });
});
