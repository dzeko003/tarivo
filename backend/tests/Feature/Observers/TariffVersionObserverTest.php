<?php

declare(strict_types=1);

use App\Enums\TariffVersionStatus;
use App\Exceptions\TariffVersionLocked;
use App\Models\TariffVersion;

it('laisse modifier un brouillon', function () {
    $version = TariffVersion::factory()->create();

    $version->update(['policy_fee' => 7_500, 'label' => 'Barème corrigé']);

    expect($version->fresh())->policy_fee->toBe(7_500)->label->toBe('Barème corrigé');
});

it('refuse de modifier une version publiée', function (array $changes, string $message) {
    $version = TariffVersion::factory()->published()->create(['code' => 'V-PUB', 'valid_to' => '2026-01-01'])->fresh();
    $before = $version->toArray();

    expect(fn () => $version->update($changes))->toThrow(TariffVersionLocked::class, $message);
    expect($version->fresh()->toArray())->toEqual($before);
})->with([
    'coût de police' => [['policy_fee' => 6_000], 'La version V-PUB est publiée : impossible de modifier policy_fee.'],
    'bornes du bonus-malus' => [['bonus_malus_max' => '4.00'], 'impossible de modifier bonus_malus_max.'],
    'début de période' => [['valid_from' => '2025-02-01'], 'impossible de modifier valid_from.'],
    'fin de période déjà fixée' => [['valid_to' => '2027-01-01'], 'impossible de modifier valid_to.'],
    'retour en brouillon' => [['status' => TariffVersionStatus::Draft], 'impossible de modifier status.'],
]);

it('refuse toute modification d\'une version archivée, y compris sa republication', function () {
    $version = TariffVersion::factory()->archived()->create(['code' => 'V-ARC']);

    expect(fn () => $version->update(['status' => TariffVersionStatus::Published]))
        ->toThrow(TariffVersionLocked::class, 'La version V-ARC est archivée : impossible de modifier status.');
});

it('refuse de supprimer une version publiée', function () {
    $version = TariffVersion::factory()->published()->create(['code' => 'V-PUB']);

    expect(fn () => $version->delete())
        ->toThrow(TariffVersionLocked::class, 'La version V-PUB est publiée : impossible de la supprimer.');
    $this->assertModelExists($version);
});

it('supprime un brouillon avec ses grilles', function () {
    $version = seededTariff('sahel_rc_2025', asDraft: true);

    $version->delete();

    $this->assertModelMissing($version);
    $this->assertDatabaseCount('coefficients', 0);
});
