<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quote;
use Illuminate\Http\Request;

/**
 * Devis enregistré complet : résumé et détail du calcul tel qu'il a été figé à l'enregistrement.
 *
 * @mixin Quote
 */
class QuoteResource extends QuoteSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            /** Résultat du calcul enregistré (même format que POST /quotes/calculate). */
            'breakdown' => $this->breakdown,
        ];
    }
}
