<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use App\Http\Resources\PricingResultResource;
use App\Http\Resources\QuoteResource;
use App\Http\Resources\QuoteSummaryResource;
use App\Models\Quote;
use App\Models\TariffVersion;
use App\Pricing\Data\PricingResult;
use App\Pricing\Pricer;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

/**
 * Devis : calcul, enregistrement, historique et recalcul.
 *
 * Le prix vient toujours du moteur (App\Pricing) avec la version de tarif publiée applicable
 * à la date d'effet ; un devis enregistré n'est jamais recalculé ni modifié en silence.
 */
class QuoteController extends Controller
{
    public function __construct(private readonly Pricer $pricer) {}

    /**
     * Calculer une prime sans l'enregistrer.
     *
     * Renvoie la prime TTC et le détail du calcul. Erreur 422 si la saisie est invalide, si elle
     * sort du barème (âge, permis, zone, bonus-malus) ou si aucun tarif n'est publié à la date d'effet.
     */
    public function calculate(QuoteRequest $request): PricingResultResource
    {
        [, $result] = $this->price($request->quoteInput());

        return new PricingResultResource($result);
    }

    /**
     * Calculer et enregistrer un devis.
     *
     * Le devis est figé : il garde la saisie, la version de tarif, le détail du calcul et le total.
     */
    public function store(QuoteRequest $request): QuoteResource
    {
        $input = $request->quoteInput();
        [$version, $result] = $this->price($input);

        $quote = Quote::create([
            'tariff_version_id' => $version->id,
            'effective_date' => $input['effective_date'],
            'duration_months' => $input['duration_months'],
            'input' => $input,
            'breakdown' => PricingResultResource::payload($result, $request),
            'total_ttc' => $result->total,
        ]);

        /** @status 201 */
        return new QuoteResource($quote->setRelation('tariffVersion', $version));
    }

    /**
     * Historique des devis enregistrés, du plus récent au plus ancien.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            /** Début de la référence, ex. « DEV-2026 ». */
            'reference' => ['sometimes', 'string', 'max:30'],
            'effective_from' => ['sometimes', 'date_format:Y-m-d'],
            'effective_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            /** Code de la version de tarif, ex. « SAHEL-RC-2026 ». */
            'tariff_version' => ['sometimes', 'string', 'max:50'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ], [
            'effective_to.after_or_equal' => "La date d'effet maximale doit être postérieure ou égale à la date d'effet minimale.",
        ]);

        $quotes = Quote::query()
            ->with('tariffVersion')
            ->when($filters['reference'] ?? null, fn ($query, string $reference) => $query->whereLike('reference', addcslashes($reference, '%_\\').'%'))
            ->when($filters['effective_from'] ?? null, fn ($query, string $from) => $query->where('effective_date', '>=', $from))
            ->when($filters['effective_to'] ?? null, fn ($query, string $to) => $query->where('effective_date', '<=', $to))
            ->when($filters['tariff_version'] ?? null, fn ($query, string $code) => $query->whereRelation('tariffVersion', 'code', $code))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 20)
            ->withQueryString();

        return QuoteSummaryResource::collection($quotes);
    }

    /**
     * Détail d'un devis enregistré.
     */
    public function show(Quote $quote): QuoteResource
    {
        return new QuoteResource($quote->load('tariffVersion'));
    }

    /**
     * Recalculer un devis avec sa version de tarif et comparer.
     *
     * Ne modifie jamais le devis : `identical` indique si le nouveau calcul redonne exactement
     * le détail et le total enregistrés. Un écart est journalisé.
     */
    public function recompute(Request $request, Quote $quote): JsonResponse
    {
        $quote->load('tariffVersion');
        $result = $this->pricer->price($quote->pricingInput(), $quote->tariffVersion->toGrid());
        $recomputed = PricingResultResource::payload($result, $request);

        $identical = self::sortedKeys($recomputed) === self::sortedKeys($quote->breakdown)
            && $result->total === $quote->total_ttc;

        if (! $identical) {
            Log::warning('Recalcul de devis divergent', [
                'quote' => $quote->reference,
                'tariff_version' => $quote->tariffVersion->code,
                'stored_total' => $quote->total_ttc,
                'recomputed_total' => $result->total,
            ]);
        }

        return response()->json([
            'data' => [
                'reference' => $quote->reference,
                'tariff_version' => $quote->tariffVersion->code,
                /**
                 * Vrai si le recalcul redonne exactement le détail et le total enregistrés.
                 *
                 * @var bool
                 */
                'identical' => $identical,
                'stored_total' => $quote->total_ttc,
                'recomputed_total' => $result->total,
                /** Total recalculé moins total enregistré. */
                'difference' => $result->total - $quote->total_ttc,
                'recomputed' => new PricingResultResource($result),
            ],
        ]);
    }

    /**
     * Calcule la prime avec la version publiée applicable au pays et à la date d'effet.
     *
     * @param  array{country: string, vehicle_category: string, fiscal_power: int, usage: string, driver_birth_date: string, license_date: string, zone: string, bonus_malus: string, effective_date: string, duration_months: int}  $input
     * @return array{TariffVersion, PricingResult}
     */
    private function price(array $input): array
    {
        $version = TariffVersion::applicableOn($input['country'], new DateTimeImmutable($input['effective_date']));

        return [$version, $this->pricer->price(Quote::pricingInputFrom($input), $version->toGrid())];
    }

    /**
     * Tableau dont les clés sont triées à tous les niveaux : PostgreSQL (jsonb) ne conserve pas
     * l'ordre des clés, seule leur valeur compte pour la comparaison.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function sortedKeys(array $data): array
    {
        ksort($data);

        return array_map(fn (mixed $value): mixed => is_array($value) ? self::sortedKeys($value) : $value, $data);
    }
}
