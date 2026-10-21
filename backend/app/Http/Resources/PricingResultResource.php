<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Pricing\Data\PricingResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Résultat d'un calcul de prime, avec le détail ligne par ligne.
 *
 * Montants en francs CFA entiers. Coefficients et sous-totaux en chaînes décimales exactes :
 * avant l'arrondi, un sous-total peut avoir des décimales (« 148781.25 »).
 * C'est aussi le contenu enregistré dans `quotes.breakdown`.
 *
 * @property PricingResult $resource
 */
class PricingResultResource extends JsonResource
{
    /**
     * Le résultat tel que l'API le renvoie, en tableau PHP simple : c'est ce qui est enregistré
     * dans `quotes.breakdown` et comparé lors d'un recalcul.
     *
     * @return array<string, mixed>
     */
    public static function payload(PricingResult $result, Request $request): array
    {
        return (new self($result))->response($request)->getData(true)['data'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        return [
            /** Code de la version de tarif appliquée. */
            'tariff_version' => $result->tariffVersion,
            'currency' => $result->currency,
            /** Âge du conducteur à la date d'effet, en années révolues. */
            'driver_age' => $result->driverAge,
            /** Ancienneté du permis à la date d'effet, en années révolues. */
            'license_years' => $result->licenseYears,
            /** Prime RC nette, arrondie au franc. */
            'net_premium' => $result->netPremium,
            /** Accessoires (coût de police). */
            'fees' => $result->fees,
            'taxes' => $result->taxes,
            /** Prime TTC. */
            'total' => $result->total,
            'lines' => BreakdownLineResource::collection($result->lines),
        ];
    }
}
