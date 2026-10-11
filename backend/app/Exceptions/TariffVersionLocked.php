<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TariffVersion;
use DomainException;

/**
 * Tentative de modifier une version de tarif publiée ou archivée, ou ses grilles.
 * Pour corriger un barème publié, on crée une nouvelle version.
 */
final class TariffVersionLocked extends DomainException
{
    /**
     * @param  list<string>  $fields
     */
    public static function fields(TariffVersion $version, array $fields): self
    {
        return new self(sprintf(
            'La version %s est %s : impossible de modifier %s.',
            $version->code, mb_strtolower($version->getOriginal('status')->label()), implode(', ', $fields),
        ));
    }

    public static function deletion(TariffVersion $version): self
    {
        return new self("La version {$version->code} est ".mb_strtolower($version->status->label()).' : impossible de la supprimer.');
    }

    public static function grids(TariffVersion $version): self
    {
        return new self("La version {$version->code} est ".mb_strtolower($version->status->label()).' : ses grilles ne peuvent plus être modifiées.');
    }
}
