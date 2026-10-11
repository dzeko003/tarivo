<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\TariffVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'DEV-'.fake()->unique()->numerify('########'),
            'tariff_version_id' => TariffVersion::factory()->published(),
            'effective_date' => '2025-07-01',
            'duration_months' => 12,
            'input' => [],
            'breakdown' => ['lines' => []],
            'total_ttc' => 100_000,
        ];
    }
}
