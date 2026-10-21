<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Quote;
use App\Models\TariffVersion;
use App\Pricing\Enums\Usage;
use App\Pricing\Enums\VehicleCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saisie d'un devis, commune au calcul simple et à l'enregistrement.
 *
 * Ici : la forme des données (types, formats, valeurs connues). Les règles qui dépendent du barème
 * (âge minimum, tranches couvertes, zone, bornes du bonus-malus) sont vérifiées par le moteur
 * (QuoteInputValidator) et renvoyées dans le même format 422.
 */
class QuoteRequest extends FormRequest
{
    /**
     * Ouvert à tous jusqu'à l'authentification (étape 8).
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'country' => TariffVersion::DEFAULT_COUNTRY,
            'duration_months' => 12,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /** Code pays ISO à deux lettres. */
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'vehicle_category' => ['required', Rule::enum(VehicleCategory::class)],
            /** Puissance fiscale en CV. */
            'fiscal_power' => ['required', 'integer', 'min:1', 'max:999'],
            'usage' => ['required', Rule::enum(Usage::class)],
            /** Format AAAA-MM-JJ. */
            'driver_birth_date' => ['required', 'date_format:Y-m-d'],
            /** Format AAAA-MM-JJ. */
            'license_date' => ['required', 'date_format:Y-m-d'],
            /** Code de zone, voir GET /reference-data. */
            'zone' => ['required', 'string', 'max:50'],
            /** Chaîne décimale, jamais un nombre JSON : « 0.95 », « 1.00 », « 1.25 ». */
            'bonus_malus' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,4})?$/'],
            /** Date de début du contrat (AAAA-MM-JJ) : elle détermine la version de tarif appliquée. */
            'effective_date' => ['required', 'date_format:Y-m-d'],
            /** Durée du contrat en mois. */
            'duration_months' => ['required', 'integer', Rule::in(Quote::AVAILABLE_DURATIONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bonus_malus.string' => 'Le coefficient bonus-malus doit être envoyé sous forme de texte, par exemple « 0.95 ».',
            'bonus_malus.regex' => 'Le coefficient bonus-malus doit être un nombre décimal avec 4 décimales au plus, par exemple « 0.95 ».',
            'duration_months.in' => 'Seule la durée de 12 mois est disponible pour le moment.',
        ];
    }

    /**
     * Saisie validée et normalisée : c'est elle qui est enregistrée dans `quotes.input`
     * et qui sert à recalculer le devis.
     *
     * @return array{country: string, vehicle_category: string, fiscal_power: int, usage: string, driver_birth_date: string, license_date: string, zone: string, bonus_malus: string, effective_date: string, duration_months: int}
     */
    public function quoteInput(): array
    {
        return [
            'country' => strtoupper($this->string('country')->value()),
            'vehicle_category' => $this->string('vehicle_category')->value(),
            'fiscal_power' => $this->integer('fiscal_power'),
            'usage' => $this->string('usage')->value(),
            'driver_birth_date' => $this->string('driver_birth_date')->value(),
            'license_date' => $this->string('license_date')->value(),
            'zone' => $this->string('zone')->value(),
            'bonus_malus' => $this->string('bonus_malus')->value(),
            'effective_date' => $this->string('effective_date')->value(),
            'duration_months' => $this->integer('duration_months'),
        ];
    }
}
