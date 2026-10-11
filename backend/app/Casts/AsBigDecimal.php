<?php

declare(strict_types=1);

namespace App\Casts;

use Brick\Math\BigDecimal;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Colonne `numeric` ↔ BigDecimal, sans jamais passer par un float.
 *
 * PostgreSQL renvoie les `numeric` sous forme de chaîne : « 1.15 » reste « 1.15 », échelle comprise.
 *
 * @implements CastsAttributes<BigDecimal|null, BigDecimal|string|int|null>
 */
class AsBigDecimal implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        return $value === null ? null : BigDecimal::of($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value)) {
            throw new InvalidArgumentException("{$key} : passer une chaîne ou un BigDecimal, jamais un float.");
        }

        return (string) BigDecimal::of($value);
    }
}
