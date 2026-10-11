<?php

declare(strict_types=1);

use App\Models\TariffVersion;

it('relit un numeric avec l\'échelle saisie, sans passer par un float', function () {
    $version = TariffVersion::factory()->create(['bonus_malus_min' => '0.50', 'bonus_malus_max' => '3.5']);

    $version = $version->fresh();

    expect((string) $version->bonus_malus_min)->toBe('0.50')
        ->and((string) $version->bonus_malus_max)->toBe('3.5');
});

it('refuse un float pour une colonne numeric', function () {
    expect(fn () => TariffVersion::factory()->make(['bonus_malus_min' => 0.5]))
        ->toThrow(InvalidArgumentException::class, 'jamais un float');
});
