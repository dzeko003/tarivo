<?php

declare(strict_types=1);

use App\Models\Quote;
use App\Models\TariffVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Les triggers PostgreSQL bloquent ce que les observers ne voient pas : requêtes en masse,
 * DB::table(), SQL direct. On passe donc volontairement à côté d'Eloquent.
 *
 * Après une erreur, PostgreSQL abandonne la transaction en cours : pour relire la base dans le test,
 * l'écriture refusée est faite dans DB::transaction() (un savepoint).
 */

it('refuse une mise à jour en masse d\'une version publiée', function () {
    $version = TariffVersion::factory()->published()->create(['code' => 'V-PUB']);

    expect(fn () => DB::transaction(fn () => TariffVersion::query()->whereKey($version->id)->update(['policy_fee' => 1])))
        ->toThrow(QueryException::class, 'tariff_version_locked: la version V-PUB (published) ne peut plus être modifiée');
    expect($version->fresh()->policy_fee)->toBe(5_000);
});

it('refuse le retour d\'une version publiée en brouillon', function () {
    $version = TariffVersion::factory()->published()->create();

    expect(fn () => DB::table('tariff_versions')->where('id', $version->id)->update(['status' => 'draft', 'published_at' => null]))
        ->toThrow(QueryException::class, 'tariff_version_locked');
});

it('refuse la suppression d\'une version publiée', function () {
    $version = TariffVersion::factory()->published()->create(['code' => 'V-PUB']);

    expect(fn () => DB::table('tariff_versions')->where('id', $version->id)->delete())
        ->toThrow(QueryException::class, 'tariff_version_locked: la version V-PUB (published) ne peut pas être supprimée');
});

it('refuse toute écriture dans les grilles d\'une version publiée', function (Closure $write, string $table) {
    $version = seededTariff('sahel_rc_2025');

    expect(fn () => $write($version->id))
        ->toThrow(QueryException::class, "tariff_version_locked: les grilles de la version SAHEL-RC-2025 ne peuvent plus être modifiées ({$table})");
})->with([
    'insertion' => [fn (int $id) => DB::table('coefficients')->insert(['tariff_version_id' => $id, 'factor' => 'zone', 'key' => 'garoua', 'value' => '1.05', 'label' => 'Garoua']), 'coefficients'],
    'mise à jour' => [fn (int $id) => DB::table('base_premiums')->where('tariff_version_id', $id)->update(['amount' => 1]), 'base_premiums'],
    'suppression' => [fn (int $id) => DB::table('taxes')->where('tariff_version_id', $id)->delete(), 'taxes'],
]);

it('laisse archiver une version publiée et clôturer sa période ouverte', function () {
    $version = TariffVersion::factory()->published()->validBetween('2026-01-01', null)->create();

    DB::table('tariff_versions')->where('id', $version->id)->update(['status' => 'archived', 'valid_to' => '2027-01-01']);

    expect($version->fresh())
        ->status->value->toBe('archived')
        ->valid_to->toDateString()->toBe('2027-01-01');
});

it('refuse une clôture qui laisserait un devis hors de la période de sa version', function () {
    $version = TariffVersion::factory()->published()->validBetween('2026-01-01', null)->create(['code' => 'V-2026']);
    Quote::factory()->for($version)->create(['effective_date' => '2026-12-31']);

    expect(fn () => DB::table('tariff_versions')->where('id', $version->id)->update(['valid_to' => '2026-12-31']))
        ->toThrow(QueryException::class, 'tariff_version_locked: des devis de la version V-2026 prennent effet après le 2026-12-31');
});
