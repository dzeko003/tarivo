<?php

declare(strict_types=1);

/*
 * Règle 7 : le moteur reste du PHP pur. Les modèles peuvent dépendre de App\Pricing, jamais l'inverse.
 */

arch('le moteur ne dépend ni de Laravel ni des modèles')
    ->expect('App\Pricing')
    ->not->toUse(['Illuminate', 'App\Models', 'App\Casts', 'App\Enums']);

arch('le moteur déclare strict_types')
    ->expect('App\Pricing')
    ->toUseStrictTypes();
