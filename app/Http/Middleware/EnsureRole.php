<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marshrutni faqat ko'rsatilgan rollarga ochadi: `role:owner` yoki `role:owner,seller`.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;

        abort_unless(
            $role instanceof Role && in_array($role->value, $roles, true),
            403,
        );

        return $next($request);
    }
}
