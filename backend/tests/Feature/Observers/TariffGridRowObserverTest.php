<?php

declare(strict_types=1);

use App\Exceptions\TariffVersionLocked;
use App\Models\BasePremium;
use App\Models\TariffVersion;
use Tests\Fixtures\SahelTariff2025;

it('refuse de modifier les grilles d\'une version publiée', function (Closure $change) {
    $version = seededTariff('sahel_rc_2025');

    expect(fn () => $change($version))
        ->toThrow(TariffVersionLocked::class, 'La version SAHEL-RC-2025 est publiée : ses grilles ne peuvent plus être modifiées.');
    expect($version->fresh()->toGrid())->toEqual(SahelTariff2025::grid());
})->with([
    'ajout d\'une zone' => [fn (TariffVersion $v) => $v->coefficients()->create(['factor' => 'zone', 'key' => 'garoua', 'value' => '1.05', 'label' => 'Garoua'])],
    'changement d\'une prime de base' => [fn (TariffVersion $v) => $v->basePremiums()->first()->update(['amount' => 1])],
    'changement d\'un taux de taxe' => [fn (TariffVersion $v) => $v->taxes()->where('code', 'tca')->first()->update(['rate' => '0.2'])],
    'suppression d\'une tranche d\'âge' => [fn (TariffVersion $v) => $v->coefficients()->where('factor', 'age')->first()->delete()],
]);

it('refuse de déplacer une ligne d\'un brouillon vers une version publiée', function () {
    $published = TariffVersion::factory()->published()->create(['code' => 'V-PUB']);
    $draft = TariffVersion::factory()->create();
    $row = $draft->basePremiums()->create(['vehicle_category' => 'deux_roues', 'cv_min' => 1, 'cv_max' => null, 'amount' => 18_000]);

    expect(fn () => $row->forceFill(['tariff_version_id' => $published->id])->save())
        ->toThrow(TariffVersionLocked::class, 'La version V-PUB est publiée');
    expect(BasePremium::find($row->id)->tariff_version_id)->toBe($draft->id);
});
