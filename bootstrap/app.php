<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\Idempotent;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => EnsureRole::class, 'idempotent' => Idempotent::class]);

        // Mobil ilova so'rovlarida ham do'kon konteksti o'rnatiladi.
        $middleware->api(append: [SetTenantContext::class]);

        // Marshrut modellari (masalan {branch}) bog'lanishidan oldin do'kon aniqlanishi shart,
        // aks holda boshqa do'kon yozuvi ID orqali ochilib qoladi.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetTenantContext::class,
        );

        $middleware->web(append: [
            SetTenantContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Mobil ilova har xatoda o'zgarmas `code` oladi.
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'code' => 'VALIDATION_FAILED',
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors(),
                ], 422);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['code' => 'UNAUTHENTICATED', 'message' => 'Tizimga kiring.'], 401);
            }

            return null;
        });

        // Biznes qoidasi buzilganda: API'ga kod bilan JSON, webda formaga xato sifatida qaytadi.
        $exceptions->render(function (BusinessRuleException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'code' => $exception->errorCode,
                    'message' => $exception->getMessage(),
                    'errors' => [$exception->field => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors([$exception->field => $exception->getMessage()]);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
