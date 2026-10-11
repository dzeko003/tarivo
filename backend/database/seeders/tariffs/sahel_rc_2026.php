<?php

/*
 * Barème RC auto 2026 de « Sahel Assurances », compagnie FICTIVE (Cameroun, XAF).
 * Montants, coefficients et taux inventés : ni tarif minimum CIMA, ni tarif d'un assureur réel.
 *
 * Changements par rapport à 2025 : primes de base revalorisées (~ +4 %), Douala 1,15 → 1,20,
 * 18-20 ans 1,40 → 1,45, 75 ans et plus 1,25 → 1,30, coût de police 5 000 → 6 000,
 * nouveau droit de timbre fixe de 1 000.
 * Primes attendues : tests/Fixtures/reference_oracle_2026.py (calcul indépendant).
 */

return [
    'version' => [
        'code' => 'SAHEL-RC-2026',
        'country' => 'CM',
        'currency' => 'XAF',
        'label' => 'Barème RC auto 2026',
        'valid_from' => '2026-01-01',
        'valid_to' => null,
        'status' => 'published',
        'published_at' => '2025-12-15 10:00:00',
        'policy_fee' => 6_000,
        'bonus_malus_min' => '0.50',
        'bonus_malus_max' => '3.50',
        'minimum_driver_age' => 18,
        'minimum_license_age' => 18,
    ],
    'base_premiums' => [
        ['vehicle_category' => 'promenade_affaires', 'cv_min' => 1, 'cv_max' => 3, 'amount' => 57_000],
        ['vehicle_category' => 'promenade_affaires', 'cv_min' => 3, 'cv_max' => 7, 'amount' => 75_000],
        ['vehicle_category' => 'promenade_affaires', 'cv_min' => 7, 'cv_max' => 11, 'amount' => 94_000],
        ['vehicle_category' => 'promenade_affaires', 'cv_min' => 11, 'cv_max' => 15, 'amount' => 123_000],
        ['vehicle_category' => 'promenade_affaires', 'cv_min' => 15, 'cv_max' => 24, 'amount' => 151_000],
        ['vehicle_category' => 'promenade_affaires', 'cv_min' => 24, 'cv_max' => null, 'amount' => 188_000],

        ['vehicle_category' => 'transport_propre_compte', 'cv_min' => 1, 'cv_max' => 7, 'amount' => 99_000],
        ['vehicle_category' => 'transport_propre_compte', 'cv_min' => 7, 'cv_max' => 11, 'amount' => 125_000],
        ['vehicle_category' => 'transport_propre_compte', 'cv_min' => 11, 'cv_max' => null, 'amount' => 167_000],

        ['vehicle_category' => 'transport_public_marchandises', 'cv_min' => 1, 'cv_max' => 11, 'amount' => 157_000],
        ['vehicle_category' => 'transport_public_marchandises', 'cv_min' => 11, 'cv_max' => null, 'amount' => 219_000],

        ['vehicle_category' => 'transport_public_voyageurs', 'cv_min' => 1, 'cv_max' => 7, 'amount' => 172_000],
        ['vehicle_category' => 'transport_public_voyageurs', 'cv_min' => 7, 'cv_max' => 11, 'amount' => 204_000],
        ['vehicle_category' => 'transport_public_voyageurs', 'cv_min' => 11, 'cv_max' => null, 'amount' => 250_000],

        ['vehicle_category' => 'deux_roues', 'cv_min' => 1, 'cv_max' => 3, 'amount' => 19_000],
        ['vehicle_category' => 'deux_roues', 'cv_min' => 3, 'cv_max' => null, 'amount' => 31_500],
    ],
    'coefficients' => [
        ['factor' => 'usage', 'key' => 'personnel', 'value' => '1.00', 'label' => 'usage personnel'],
        ['factor' => 'usage', 'key' => 'professionnel', 'value' => '1.15', 'label' => 'usage professionnel'],

        ['factor' => 'age', 'band_min' => 18, 'band_max' => 21, 'value' => '1.45', 'label' => '18 à 20 ans'],
        ['factor' => 'age', 'band_min' => 21, 'band_max' => 25, 'value' => '1.25', 'label' => '21 à 24 ans'],
        ['factor' => 'age', 'band_min' => 25, 'band_max' => 65, 'value' => '1.00', 'label' => '25 à 64 ans'],
        ['factor' => 'age', 'band_min' => 65, 'band_max' => 75, 'value' => '1.10', 'label' => '65 à 74 ans'],
        ['factor' => 'age', 'band_min' => 75, 'band_max' => null, 'value' => '1.30', 'label' => '75 ans et plus'],

        ['factor' => 'license', 'band_min' => 0, 'band_max' => 1, 'value' => '1.30', 'label' => "moins d'1 an"],
        ['factor' => 'license', 'band_min' => 1, 'band_max' => 3, 'value' => '1.15', 'label' => '1 à 2 ans'],
        ['factor' => 'license', 'band_min' => 3, 'band_max' => null, 'value' => '1.00', 'label' => '3 ans et plus'],

        ['factor' => 'zone', 'key' => 'douala', 'value' => '1.20', 'label' => 'Douala'],
        ['factor' => 'zone', 'key' => 'yaounde', 'value' => '1.10', 'label' => 'Yaoundé'],
        ['factor' => 'zone', 'key' => 'autres_villes', 'value' => '1.00', 'label' => 'autres villes'],
        ['factor' => 'zone', 'key' => 'zone_rurale', 'value' => '0.90', 'label' => 'zone rurale'],
    ],
    'taxes' => [
        ['position' => 1, 'code' => 'tca', 'label' => "Taxe sur les contrats d'assurance", 'rate' => '0.145', 'base' => 'prime_nette_et_accessoires'],
        ['position' => 2, 'code' => 'fga', 'label' => 'Contribution au fonds de garantie automobile', 'rate' => '0.025', 'base' => 'prime_nette'],
        ['position' => 3, 'code' => 'timbre', 'label' => 'Droit de timbre', 'fixed_amount' => 1_000],
    ],
];
