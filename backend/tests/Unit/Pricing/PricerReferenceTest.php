<?php

declare(strict_types=1);

use App\Pricing\Enums\LineType;
use App\Pricing\Pricer;
use Tests\Fixtures\QuoteInputBuilder;
use Tests\Fixtures\SahelTariff2025;

/*
 * Profils de référence : primes attendues calculées INDÉPENDAMMENT du moteur
 * (tests/Fixtures/reference_oracle_2025.py). Si un montant change, ce test casse.
 */
dataset('reference_profiles_2025', fn () => referenceProfiles('reference_profiles_2025.csv'));

it('calcule la prime attendue pour chaque profil de référence', function (array $profile) {
    $result = (new Pricer)->price(quoteInputFromProfile($profile), SahelTariff2025::grid());

    expect($result->netPremium)->toBe((int) $profile['expected_net_premium'])
        ->and($result->total)->toBe((int) $profile['expected_total']);
})->with('reference_profiles_2025');

it("détaille le calcul d'Awa ligne par ligne", function () {
    $input = QuoteInputBuilder::make([
        'fiscal_power' => 7,
        'birth_date' => '2001-03-10',
        'license_date' => '2023-05-20',
        'zone' => 'douala',
        'effective_date' => '2025-07-01',
    ]);

    $result = (new Pricer)->price($input, SahelTariff2025::grid());

    $summary = array_map(fn ($line) => [$line->code, $line->label, (string) $line->subtotal], $result->lines);

    expect($summary)->toBe([
        ['base_premium', 'Prime de base RC — Promenade et affaires, 7 à 10 CV', '90000'],
        ['usage', 'Usage : usage personnel', '90000.00'],
        ['driver_age', 'Âge du conducteur : 24 ans (21 à 24 ans)', '112500.0000'],
        ['license_seniority', 'Ancienneté du permis : 2 ans (1 à 2 ans)', '129375.000000'],
        ['zone', 'Zone : Douala', '148781.25000000'],
        ['bonus_malus', 'Coefficient bonus-malus', '148781.2500000000'],
        ['net_premium', 'Prime RC nette (arrondie au franc)', '148781'],
        ['policy_fee', 'Accessoires (coût de police)', '153781'],
        ['tca', "Taxe sur les contrats d'assurance (14,5 % de 153 781 XAF)", '176079'],
        ['fga', 'Contribution au fonds de garantie automobile (2,5 % de 148 781 XAF)', '179799'],
    ]);

    expect($result)
        ->tariffVersion->toBe('SAHEL-RC-2025')
        ->currency->toBe('XAF')
        ->driverAge->toBe(24)
        ->licenseYears->toBe(2)
        ->netPremium->toBe(148_781)
        ->fees->toBe(5_000)
        ->taxes->toBe(26_018)
        ->total->toBe(179_799);

    expect(array_map(fn ($l) => $l->type, $result->lines))
        ->toContain(LineType::Base, LineType::Coefficient, LineType::Rounding, LineType::Fee, LineType::Tax);
});
