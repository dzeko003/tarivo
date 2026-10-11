<?php

use App\Models\TariffVersion;
use App\Pricing\Data\BreakdownLine;
use App\Pricing\Data\QuoteInput;
use App\Pricing\Data\TariffGrid;
use App\Pricing\Enums\Usage;
use App\Pricing\Enums\VehicleCategory;
use App\Pricing\PricingContext;
use Brick\Math\BigDecimal;
use Database\Seeders\TariffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\QuoteInputBuilder;
use Tests\Fixtures\SahelTariff2025;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Les tests Feature utilisent l'application Laravel et la base PostgreSQL de test (vidée à chaque test).
| Les tests Unit du moteur (tests/Unit/Pricing) restent en PHP pur : ni Laravel ni base de données.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toEqualDecimal', function (string $expected) {
    expect($this->value)->toBeInstanceOf(BigDecimal::class);
    expect($this->value->isEqualTo($expected))
        ->toBeTrue("{$this->value} n'est pas égal à {$expected}");

    return $this;
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Contexte de calcul sur le barème Sahel 2025, avec un devis neutre modifié par $overrides.
 *
 * @param  array<string, mixed>  $overrides  voir QuoteInputBuilder::make()
 */
function pricingContext(array $overrides = [], ?TariffGrid $grid = null): PricingContext
{
    return new PricingContext(QuoteInputBuilder::make($overrides), $grid ?? SahelTariff2025::grid());
}

/**
 * Contexte déjà démarré avec une prime de base de 100 000, pour tester un coefficient isolément.
 *
 * @param  array<string, mixed>  $overrides
 */
function startedContext(array $overrides = []): PricingContext
{
    $context = pricingContext($overrides);
    $context->start('base_premium', 'Base de test', 100_000);

    return $context;
}

/**
 * Dernière ligne du détail d'un contexte.
 */
function lastLine(PricingContext $context): BreakdownLine
{
    $lines = $context->lines();

    return $lines[array_key_last($lines)];
}

/**
 * Profils de référence d'un fichier CSV de tests/Fixtures (primes attendues calculées par un oracle
 * indépendant du moteur), indexés par nom de profil pour les datasets Pest.
 *
 * @return Generator<string, array{array<string, string>}>
 */
function referenceProfiles(string $csvFile): Generator
{
    $file = new SplFileObject(__DIR__.'/Fixtures/'.$csvFile);
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

    $header = null;
    foreach ($file as $row) {
        if ($header === null) {
            $header = $row;

            continue;
        }
        $profile = array_combine($header, $row);
        yield $profile['name'] => [$profile];
    }
}

/**
 * @param  array<string, string>  $profile  une ligne de referenceProfiles()
 */
function quoteInputFromProfile(array $profile): QuoteInput
{
    return QuoteInputBuilder::make([
        'category' => VehicleCategory::from($profile['category']),
        'fiscal_power' => (int) $profile['fiscal_power'],
        'usage' => Usage::from($profile['usage']),
        'birth_date' => $profile['birth_date'],
        'license_date' => $profile['license_date'],
        'zone' => $profile['zone'],
        'bonus_malus' => $profile['bonus_malus'],
        'effective_date' => $profile['effective_date'],
    ]);
}

/**
 * Barème complet de database/seeders/tariffs/ (publié comme dans le fichier, ou laissé en brouillon).
 */
function seededTariff(string $name, bool $asDraft = false): TariffVersion
{
    return (new TariffSeeder)->seedTariff($name, $asDraft)->fresh();
}
