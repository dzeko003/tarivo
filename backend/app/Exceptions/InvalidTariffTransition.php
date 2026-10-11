<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\TariffVersion;
use DateTimeInterface;
use DomainException;

/**
 * Changement de cycle de vie refusé (publication, archivage, clôture de la période).
 */
final class InvalidTariffTransition extends DomainException
{
    public static function notDraft(TariffVersion $version): self
    {
        return new self("Seul un brouillon peut être publié : la version {$version->code} est ".mb_strtolower($version->status->label()).'.');
    }

    public static function overlaps(TariffVersion $version, TariffVersion $published): self
    {
        return new self("La période de la version {$version->code} chevauche celle de la version publiée {$published->code}.");
    }

    public static function notPublished(TariffVersion $version): self
    {
        return new self("La version {$version->code} n'est pas publiée.");
    }

    public static function alreadyClosed(TariffVersion $version): self
    {
        return new self("La période de la version {$version->code} est déjà fermée.");
    }

    public static function invalidEnd(TariffVersion $version, DateTimeInterface $validTo): self
    {
        return new self("La version {$version->code} commence le {$version->valid_from->format('d/m/Y')} : elle ne peut pas se terminer le {$validTo->format('d/m/Y')}.");
    }

    public static function quotesAfterEnd(TariffVersion $version, DateTimeInterface $validTo): self
    {
        return new self("Des devis de la version {$version->code} prennent effet à partir du {$validTo->format('d/m/Y')} : la période ne peut pas se terminer avant eux.");
    }
}
