<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Pricing\Data\BreakdownLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une ligne du détail de calcul.
 *
 * @property BreakdownLine $resource
 */
class BreakdownLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $line = $this->resource;

        return [
            'code' => $line->code,
            'label' => $line->label,
            /** base, coefficient, arrondi, accessoire ou taxe. */
            'type' => $line->type,
            /** Coefficient appliqué (lignes de type « coefficient »), en chaîne décimale. */
            'coefficient' => $line->coefficient === null ? null : (string) $line->coefficient,
            /** Montant ajouté (base, accessoire, taxe), en francs entiers. */
            'amount' => $line->amount?->toInt(),
            /** Total cumulé après cette ligne, en chaîne décimale exacte. */
            'subtotal' => (string) $line->subtotal->strippedOfTrailingZeros(),
        ];
    }
}
