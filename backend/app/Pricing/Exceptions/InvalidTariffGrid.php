<?php

declare(strict_types=1);

namespace App\Pricing\Exceptions;

use DomainException;

/**
 * Le barème est incomplet : certains devis ne pourraient pas être calculés.
 *
 * Contient tous les problèmes trouvés, pour pouvoir les corriger d'un coup.
 */
final class InvalidTariffGrid extends DomainException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('Barème incomplet : '.implode(' ', $problems));
    }
}
