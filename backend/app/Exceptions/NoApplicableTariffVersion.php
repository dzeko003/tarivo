<?php

declare(strict_types=1);

namespace App\Exceptions;

use DateTimeInterface;
use DomainException;

/**
 * Aucune version de tarif publiée ne couvre ce pays à cette date d'effet.
 */
final class NoApplicableTariffVersion extends DomainException
{
    public function __construct(public readonly string $country, public readonly DateTimeInterface $effectiveDate)
    {
        parent::__construct("Aucun tarif publié pour le pays {$country} à la date d'effet du {$effectiveDate->format('d/m/Y')}.");
    }
}
