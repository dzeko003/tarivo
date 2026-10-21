<?php

declare(strict_types=1);

/*
 * Listes du formulaire de devis : elles viennent de la version de tarif applicable à la date d'effet.
 */

beforeEach(function () {
    seededTariff('sahel_rc_2025');
    seededTariff('sahel_rc_2026');
});

it("renvoie les listes de la version applicable à la date d'effet", function () {
    $response = $this->getJson(route('reference-data', ['effective_date' => '2026-03-01']));

    $response->assertOk()->assertExactJson(['data' => [
        'country' => 'CM',
        'effective_date' => '2026-03-01',
        'tariff_version' => [
            'code' => 'SAHEL-RC-2026',
            'label' => 'Barème RC auto 2026',
            'currency' => 'XAF',
            'valid_from' => '2026-01-01',
            'valid_to' => null,
        ],
        'vehicle_categories' => [
            ['value' => 'promenade_affaires', 'label' => 'Promenade et affaires'],
            ['value' => 'transport_propre_compte', 'label' => 'Transport pour propre compte'],
            ['value' => 'transport_public_marchandises', 'label' => 'Transport public de marchandises'],
            ['value' => 'transport_public_voyageurs', 'label' => 'Transport public de voyageurs'],
            ['value' => 'deux_roues', 'label' => 'Deux-roues'],
        ],
        'usages' => [
            ['value' => 'personnel', 'label' => 'Usage personnel'],
            ['value' => 'professionnel', 'label' => 'Usage professionnel'],
        ],
        'zones' => [
            ['value' => 'douala', 'label' => 'Douala'],
            ['value' => 'yaounde', 'label' => 'Yaoundé'],
            ['value' => 'autres_villes', 'label' => 'autres villes'],
            ['value' => 'zone_rurale', 'label' => 'zone rurale'],
        ],
        'durations_months' => [12],
        'bonus_malus' => ['min' => '0.50', 'max' => '3.50'],
        'minimum_driver_age' => 18,
        'minimum_license_age' => 18,
    ]]);
});

it("utilise la date du jour quand aucune date d'effet n'est donnée", function () {
    $this->travelTo('2025-07-01 10:00:00');

    $response = $this->getJson(route('reference-data'));

    $response->assertOk()
        ->assertJsonPath('data.effective_date', '2025-07-01')
        ->assertJsonPath('data.tariff_version.code', 'SAHEL-RC-2025');
});

it("renvoie 422 sur la date d'effet quand aucun tarif n'est publié à cette date", function () {
    $response = $this->getJson(route('reference-data', ['effective_date' => '2024-06-01']));

    $response->assertUnprocessable()->assertJsonValidationErrors([
        'effective_date' => "Aucun tarif publié pour le pays CM à la date d'effet du 01/06/2024.",
    ]);
});
