<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Catalog\PublicCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mijozlar uchun ochiq katalog (kirishsiz). Telegram mini-ilova sifatida ochiladi.
 */
class StorefrontController extends Controller
{
    public function show(string $code, PublicCatalog $catalog): Response
    {
        $tenant = $catalog->findByCode($code);

        abort_if($tenant === null, 404);

        return Inertia::render('storefront/show', $catalog->for($tenant));
    }
}
