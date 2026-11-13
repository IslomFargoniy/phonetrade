<?php

namespace App\Http\Controllers\Legal;

use App\Actions\Accounts\DeleteAccount;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ochiq sahifalar: maxfiylik siyosati va akkauntni o'chirish yo'riqnomasi
 * (ilova do'konlari talab qiladi).
 */
class LegalController extends Controller
{
    public function privacy(): Response
    {
        return Inertia::render('legal/privacy', $this->shared());
    }

    public function accountDeletion(): Response
    {
        return Inertia::render('legal/account-deletion', $this->shared());
    }

    /**
     * @return array<string, mixed>
     */
    private function shared(): array
    {
        return [
            'contactEmail' => config('app.contact_email'),
            'fulfilDays' => DeleteAccount::FULFIL_WITHIN_DAYS,
            'updated' => '14.11.2026',
        ];
    }
}
