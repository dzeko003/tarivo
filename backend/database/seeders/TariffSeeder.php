<?php

namespace Database\Seeders;

use App\Enums\TariffVersionStatus;
use App\Models\TariffVersion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Charge les barèmes de Sahel Assurances décrits dans database/seeders/tariffs/.
 *
 * Même parcours qu'un tarificateur : brouillon, saisie des grilles, puis publication
 * (qui vérifie que le barème est complet et ne chevauche aucune version publiée).
 */
class TariffSeeder extends Seeder
{
    /** @var list<string> */
    public const TARIFFS = ['sahel_rc_2025', 'sahel_rc_2026'];

    public function run(): void
    {
        foreach (self::TARIFFS as $name) {
            $this->seedTariff($name);
        }
    }

    /**
     * Crée une version de tarif et toutes ses grilles, en une transaction.
     *
     * @param  bool  $asDraft  laisser la version en brouillon, quel que soit le statut du fichier
     */
    public function seedTariff(string $name, bool $asDraft = false): TariffVersion
    {
        /** @var array{version: array<string, mixed>, base_premiums: list<array<string, mixed>>, coefficients: list<array<string, mixed>>, taxes: list<array<string, mixed>>} $tariff */
        $tariff = require __DIR__."/tariffs/{$name}.php";

        return DB::transaction(function () use ($tariff, $asDraft) {
            ['status' => $status, 'published_at' => $publishedAt] = $tariff['version'];

            $version = TariffVersion::create([...$tariff['version'], 'status' => TariffVersionStatus::Draft, 'published_at' => null]);
            $version->basePremiums()->createMany($tariff['base_premiums']);
            $version->coefficients()->createMany($tariff['coefficients']);
            $version->taxes()->createMany($tariff['taxes']);

            if (! $asDraft && $status === TariffVersionStatus::Published->value) {
                $version->publish(CarbonImmutable::parse($publishedAt));
            }

            return $version;
        });
    }
}
