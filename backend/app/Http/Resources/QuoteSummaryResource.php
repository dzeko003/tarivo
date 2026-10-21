<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Devis enregistré, sans le détail du calcul (historique).
 *
 * @mixin Quote
 */
class QuoteSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** Référence lisible du devis, ex. « DEV-2026-000042 ». */
            'reference' => $this->reference,
            'effective_date' => $this->effective_date->toDateString(),
            'duration_months' => $this->duration_months,
            'tariff_version' => [
                'code' => $this->tariffVersion->code,
                'label' => $this->tariffVersion->label,
            ],
            'currency' => $this->tariffVersion->currency,
            /** Prime TTC en francs CFA entiers. */
            'total_ttc' => $this->total_ttc,
            /** Saisie enregistrée, telle que validée par l'API. */
            'input' => $this->input,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
