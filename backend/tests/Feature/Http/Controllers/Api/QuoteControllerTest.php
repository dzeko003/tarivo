<?php

declare(strict_types=1);

use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
 * API des devis, de bout en bout : saisie → version de tarif applicable → moteur → JSON.
 * Les primes attendues viennent des oracles indépendants (tests/Fixtures/reference_oracle_*.py).
 */

dataset('reference_profiles', function () {
    yield from referenceProfiles('reference_profiles_2025.csv');
    yield from referenceProfiles('reference_profiles_2026.csv');
});

beforeEach(function () {
    seededTariff('sahel_rc_2025');
    seededTariff('sahel_rc_2026');
});

/** Awa, 24 ans, 2 ans de permis, 7 CV à Douala, contrat au 1er juillet 2025. */
function awaPayload(): array
{
    return quotePayload([
        'driver_birth_date' => '2001-03-10',
        'license_date' => '2023-05-20',
        'zone' => 'douala',
    ]);
}

describe('calcul', function () {
    it("renvoie la prime d'Awa et le détail ligne par ligne, sans enregistrer de devis", function () {
        $response = $this->postJson(route('quotes.calculate'), awaPayload());

        $response->assertOk()->assertExactJson(['data' => [
            'tariff_version' => 'SAHEL-RC-2025',
            'currency' => 'XAF',
            'driver_age' => 24,
            'license_years' => 2,
            'net_premium' => 148_781,
            'fees' => 5_000,
            'taxes' => 26_018,
            'total' => 179_799,
            'lines' => [
                ['code' => 'base_premium', 'label' => 'Prime de base RC — Promenade et affaires, 7 à 10 CV', 'type' => 'base', 'coefficient' => null, 'amount' => 90_000, 'subtotal' => '90000'],
                ['code' => 'usage', 'label' => 'Usage : usage personnel', 'type' => 'coefficient', 'coefficient' => '1.00', 'amount' => null, 'subtotal' => '90000'],
                ['code' => 'driver_age', 'label' => 'Âge du conducteur : 24 ans (21 à 24 ans)', 'type' => 'coefficient', 'coefficient' => '1.25', 'amount' => null, 'subtotal' => '112500'],
                ['code' => 'license_seniority', 'label' => 'Ancienneté du permis : 2 ans (1 à 2 ans)', 'type' => 'coefficient', 'coefficient' => '1.15', 'amount' => null, 'subtotal' => '129375'],
                ['code' => 'zone', 'label' => 'Zone : Douala', 'type' => 'coefficient', 'coefficient' => '1.15', 'amount' => null, 'subtotal' => '148781.25'],
                ['code' => 'bonus_malus', 'label' => 'Coefficient bonus-malus', 'type' => 'coefficient', 'coefficient' => '1.00', 'amount' => null, 'subtotal' => '148781.25'],
                ['code' => 'net_premium', 'label' => 'Prime RC nette (arrondie au franc)', 'type' => 'arrondi', 'coefficient' => null, 'amount' => null, 'subtotal' => '148781'],
                ['code' => 'policy_fee', 'label' => 'Accessoires (coût de police)', 'type' => 'accessoire', 'coefficient' => null, 'amount' => 5_000, 'subtotal' => '153781'],
                ['code' => 'tca', 'label' => "Taxe sur les contrats d'assurance (14,5 % de 153 781 XAF)", 'type' => 'taxe', 'coefficient' => null, 'amount' => 22_298, 'subtotal' => '176079'],
                ['code' => 'fga', 'label' => 'Contribution au fonds de garantie automobile (2,5 % de 148 781 XAF)', 'type' => 'taxe', 'coefficient' => null, 'amount' => 3_720, 'subtotal' => '179799'],
            ],
        ]]);

        $this->assertDatabaseCount('quotes', 0);
    });

    it('renvoie la prime attendue de chaque profil de référence, avec la version de sa date d\'effet', function (array $profile) {
        $response = $this->postJson(route('quotes.calculate'), quotePayloadFromProfile($profile));

        $response->assertOk()
            ->assertJsonPath('data.tariff_version', str_starts_with($profile['effective_date'], '2025') ? 'SAHEL-RC-2025' : 'SAHEL-RC-2026')
            ->assertJsonPath('data.net_premium', (int) $profile['expected_net_premium'])
            ->assertJsonPath('data.total', (int) $profile['expected_total']);
    })->with('reference_profiles');

    it('applique la version publiée dont la période contient la date d\'effet', function (string $effectiveDate, string $expectedVersion) {
        $response = $this->postJson(route('quotes.calculate'), quotePayload(['effective_date' => $effectiveDate]));

        $response->assertOk()->assertJsonPath('data.tariff_version', $expectedVersion);
    })->with([
        'dernier jour de 2025' => ['2025-12-31', 'SAHEL-RC-2025'],
        'premier jour de 2026' => ['2026-01-01', 'SAHEL-RC-2026'],
    ]);

    it('renvoie 422 avec chaque champ obligatoire manquant', function () {
        $response = $this->postJson(route('quotes.calculate'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'vehicle_category' => 'Le champ catégorie de véhicule est obligatoire.',
            'fiscal_power' => 'Le champ puissance fiscale est obligatoire.',
            'usage' => 'Le champ usage est obligatoire.',
            'driver_birth_date' => 'Le champ date de naissance du conducteur est obligatoire.',
            'license_date' => "Le champ date d'obtention du permis est obligatoire.",
            'zone' => 'Le champ zone est obligatoire.',
            'bonus_malus' => 'Le champ coefficient bonus-malus est obligatoire.',
            'effective_date' => "Le champ date d'effet est obligatoire.",
        ]);
    });

    it('renvoie 422 pour une saisie mal formée', function (array $overrides, string $field, string $message) {
        $response = $this->postJson(route('quotes.calculate'), quotePayload($overrides));

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
    })->with([
        'bonus-malus en nombre JSON' => [['bonus_malus' => 0.95], 'bonus_malus', 'Le coefficient bonus-malus doit être envoyé sous forme de texte, par exemple « 0.95 ».'],
        'bonus-malus à 5 décimales' => [['bonus_malus' => '0.95001'], 'bonus_malus', 'Le coefficient bonus-malus doit être un nombre décimal avec 4 décimales au plus, par exemple « 0.95 ».'],
        'catégorie inconnue' => [['vehicle_category' => 'tracteur'], 'vehicle_category', 'La valeur du champ catégorie de véhicule est invalide.'],
        'puissance nulle' => [['fiscal_power' => 0], 'fiscal_power', 'Le champ puissance fiscale doit être au moins égal à 1.'],
        'date au mauvais format' => [['effective_date' => '01/07/2025'], 'effective_date', "Le champ date d'effet doit respecter le format Y-m-d."],
        'durée de 6 mois' => [['duration_months' => 6], 'duration_months', 'Seule la durée de 12 mois est disponible pour le moment.'],
    ]);

    it('renvoie 422 avec toutes les erreurs du barème, par champ', function () {
        $response = $this->postJson(route('quotes.calculate'), quotePayload([
            'driver_birth_date' => '2008-01-01',
            'license_date' => '2025-06-01',
            'zone' => 'tombouctou',
            'bonus_malus' => '4.00',
        ]));

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'driver_birth_date' => "Le conducteur doit avoir au moins 18 ans à la date d'effet.",
            'license_date' => 'Le permis ne peut pas avoir été obtenu avant 18 ans.',
            'zone' => 'Zone inconnue : tombouctou.',
            'bonus_malus' => 'Le coefficient bonus-malus doit être compris entre 0.50 et 3.50.',
        ]);
    });

    it("renvoie 422 sur la date d'effet quand aucun tarif n'est publié pour le pays à cette date", function (array $overrides, string $message) {
        $response = $this->postJson(route('quotes.calculate'), quotePayload($overrides));

        $response->assertUnprocessable()->assertJsonValidationErrors(['effective_date' => $message]);
    })->with([
        'avant le premier barème' => [['effective_date' => '2024-06-01'], "Aucun tarif publié pour le pays CM à la date d'effet du 01/06/2024."],
        'autre pays' => [['country' => 'sn'], "Aucun tarif publié pour le pays SN à la date d'effet du 01/07/2025."],
    ]);
});

describe('enregistrement', function () {
    it('enregistre un devis figé avec sa saisie, sa version, son détail et son total, et renvoie 201', function () {
        $this->travelTo('2026-03-15 09:00:00');
        $calculation = $this->postJson(route('quotes.calculate'), awaPayload())->json('data');

        $response = $this->postJson(route('quotes.store'), awaPayload());

        $quote = Quote::sole();
        $response->assertCreated()
            ->assertJsonPath('data.reference', sprintf('DEV-2026-%06d', $quote->id))
            ->assertJsonPath('data.tariff_version.code', 'SAHEL-RC-2025')
            ->assertJsonPath('data.total_ttc', 179_799)
            ->assertJsonPath('data.breakdown', $calculation);

        expect($quote)
            ->reference->toBe(sprintf('DEV-2026-%06d', $quote->id))
            ->tariffVersion->code->toBe('SAHEL-RC-2025')
            ->effective_date->toDateString()->toBe('2025-07-01')
            ->duration_months->toBe(12)
            ->total_ttc->toBe(179_799)
            ->user_id->toBeNull()
            ->input->toEqual([...awaPayload(), 'country' => 'CM', 'duration_months' => 12])
            ->breakdown->toEqual($calculation);
    });

    it("n'enregistre aucun devis quand la saisie est refusée par le barème", function () {
        $response = $this->postJson(route('quotes.store'), quotePayload(['zone' => 'tombouctou']));

        $response->assertUnprocessable()->assertJsonValidationErrors(['zone']);
        $this->assertDatabaseCount('quotes', 0);
    });
});

describe('consultation', function () {
    it('renvoie un devis enregistré avec son détail', function () {
        $reference = $this->postJson(route('quotes.store'), awaPayload())->json('data.reference');
        $quote = Quote::where('reference', $reference)->sole();

        $response = $this->getJson(route('quotes.show', $quote));

        $response->assertOk()
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.currency', 'XAF')
            ->assertJsonPath('data.input.zone', 'douala')
            ->assertJsonPath('data.breakdown.total', 179_799)
            ->assertJsonCount(10, 'data.breakdown.lines');
    });

    it('renvoie 404 pour un devis inconnu', function () {
        $this->getJson(route('quotes.show', 999_999))->assertNotFound();
    });
});

describe('historique', function () {
    it('liste les devis du plus récent au plus ancien, sans le détail du calcul, page par page', function () {
        $references = collect(['2025-03-01', '2025-09-01', '2026-02-01'])
            ->map(fn (string $date) => $this->postJson(route('quotes.store'), quotePayload(['effective_date' => $date]))->json('data.reference'));

        $response = $this->getJson(route('quotes.index', ['per_page' => 2]));

        $response->assertOk()
            ->assertJsonPath('data.*.reference', [$references[2], $references[1]])
            ->assertJsonMissingPath('data.0.breakdown')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    });

    it('filtre par référence, période d\'effet et version de tarif', function (array $filters, array $expectedDates) {
        collect(['2025-03-01', '2025-09-01', '2026-02-01'])
            ->each(fn (string $date) => $this->postJson(route('quotes.store'), quotePayload(['effective_date' => $date]))->assertCreated());
        $filters = array_map(fn (string $value) => $value === '{first}' ? Quote::oldest('id')->value('reference') : $value, $filters);

        $response = $this->getJson(route('quotes.index', $filters));

        $response->assertOk()->assertJsonPath('data.*.effective_date', $expectedDates);
    })->with([
        'référence exacte' => [['reference' => '{first}'], ['2025-03-01']],
        'début de période' => [['effective_from' => '2025-09-01'], ['2026-02-01', '2025-09-01']],
        'période complète' => [['effective_from' => '2025-04-01', 'effective_to' => '2025-12-31'], ['2025-09-01']],
        'version de tarif' => [['tariff_version' => 'SAHEL-RC-2025'], ['2025-09-01', '2025-03-01']],
    ]);

    it('renvoie 422 quand la fin de période précède son début', function () {
        $response = $this->getJson(route('quotes.index', ['effective_from' => '2025-09-01', 'effective_to' => '2025-01-01']));

        $response->assertUnprocessable()->assertJsonValidationErrors([
            'effective_to' => "La date d'effet maximale doit être postérieure ou égale à la date d'effet minimale.",
        ]);
    });
});

describe('recalcul', function () {
    it('redonne exactement le devis enregistré pour chaque profil de référence', function (array $profile) {
        $reference = $this->postJson(route('quotes.store'), quotePayloadFromProfile($profile))->json('data.reference');
        $quote = Quote::where('reference', $reference)->sole();

        $response = $this->postJson(route('quotes.recompute', $quote));

        $response->assertOk()
            ->assertJsonPath('data.identical', true)
            ->assertJsonPath('data.difference', 0)
            ->assertJsonPath('data.recomputed_total', (int) $profile['expected_total']);
        expect($response->json('data.recomputed'))->toEqual($quote->breakdown);
    })->with('reference_profiles');

    it('recalcule avec la version du devis, même archivée depuis', function () {
        $reference = $this->postJson(route('quotes.store'), awaPayload())->json('data.reference');
        $quote = Quote::where('reference', $reference)->sole();
        $quote->tariffVersion->archive();

        $response = $this->postJson(route('quotes.recompute', $quote));

        $response->assertOk()
            ->assertJsonPath('data.tariff_version', 'SAHEL-RC-2025')
            ->assertJsonPath('data.identical', true);
    });

    it('signale et journalise un écart sans modifier le devis', function () {
        Log::spy();
        $reference = $this->postJson(route('quotes.store'), awaPayload())->json('data.reference');
        DB::table('quotes')->where('reference', $reference)->update(['total_ttc' => 180_000]);
        $quote = Quote::where('reference', $reference)->sole();
        $storedBreakdown = $quote->breakdown;

        $response = $this->postJson(route('quotes.recompute', $quote));

        $response->assertOk()
            ->assertJsonPath('data.identical', false)
            ->assertJsonPath('data.stored_total', 180_000)
            ->assertJsonPath('data.recomputed_total', 179_799)
            ->assertJsonPath('data.difference', -201);
        expect($quote->fresh())
            ->total_ttc->toBe(180_000)
            ->breakdown->toEqual($storedBreakdown);
        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => $message === 'Recalcul de devis divergent' && $context['quote'] === $reference,
        );
    });

    it('signale un détail enregistré différent, même à total égal', function () {
        $reference = $this->postJson(route('quotes.store'), awaPayload())->json('data.reference');
        $quote = Quote::where('reference', $reference)->sole();
        $breakdown = $quote->breakdown;
        $breakdown['lines'][4]['coefficient'] = '1.20';
        DB::table('quotes')->where('id', $quote->id)->update(['breakdown' => json_encode($breakdown)]);

        $response = $this->postJson(route('quotes.recompute', $quote));

        $response->assertOk()
            ->assertJsonPath('data.identical', false)
            ->assertJsonPath('data.difference', 0);
    });

    it('renvoie 404 pour un devis inconnu', function () {
        $this->postJson(route('quotes.recompute', 999_999))->assertNotFound();
    });
});
