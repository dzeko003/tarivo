<?php

namespace Database\Factories;

use App\Enums\TariffVersionStatus;
use App\Models\TariffVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Version de tarif sans grilles (brouillon par défaut). Pour un barème complet, utiliser TariffSeeder.
 *
 * @extends Factory<TariffVersion>
 */
class TariffVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'TEST-'.fake()->unique()->numerify('######'),
            'country' => 'CM',
            'currency' => 'XAF',
            'label' => 'Barème de test',
            'valid_from' => '2025-01-01',
            'valid_to' => '2026-01-01',
            'status' => TariffVersionStatus::Draft,
            'published_at' => null,
            'policy_fee' => 5_000,
            'bonus_malus_min' => '0.50',
            'bonus_malus_max' => '3.50',
            'minimum_driver_age' => 18,
            'minimum_license_age' => 18,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => TariffVersionStatus::Published,
            'published_at' => '2024-12-15 10:00:00',
        ]);
    }

    public function archived(): static
    {
        return $this->published()->state(['status' => TariffVersionStatus::Archived]);
    }

    /**
     * Période [from, to), to = null = en cours.
     */
    public function validBetween(string $from, ?string $to): static
    {
        return $this->state(['valid_from' => $from, 'valid_to' => $to]);
    }
}
