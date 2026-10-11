<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cycle de vie d'une version de tarif : brouillon → publiée (figée) → archivée.
 */
enum TariffVersionStatus: string
{
    /** En cours de saisie, encore modifiable. */
    case Draft = 'draft';

    /** Applicable aux devis de sa période ; ne peut plus être modifiée. */
    case Published = 'published';

    /** Remplacée ; conservée pour recalculer les anciens devis. */
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Published => 'Publiée',
            self::Archived => 'Archivée',
        };
    }
}
