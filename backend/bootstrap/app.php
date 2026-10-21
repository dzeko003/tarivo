<?php

use App\Exceptions\NoApplicableTariffVersion;
use App\Pricing\Exceptions\InvalidQuoteInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Erreurs métier de la saisie d'un devis : même réponse 422 (message + errors par champ)
        // que la validation Laravel, pour que le formulaire les affiche sous chaque champ.
        $exceptions->map(
            InvalidQuoteInput::class,
            fn (InvalidQuoteInput $e) => ValidationException::withMessages($e->errors),
        );
        $exceptions->map(
            NoApplicableTariffVersion::class,
            fn (NoApplicableTariffVersion $e) => ValidationException::withMessages(['effective_date' => $e->getMessage()]),
        );
    })->create();
