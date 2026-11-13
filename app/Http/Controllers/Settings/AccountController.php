<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Accounts\DeleteAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Akkauntni o'chirish: web sozlamalari va mobil ilova bitta amalni ishlatadi.
 * Har ikkalasida joriy parol so'raladi.
 */
class AccountController extends Controller
{
    public function destroy(Request $request, DeleteAccount $deleteAccount): RedirectResponse
    {
        $user = $this->confirmed($request);
        $result = $deleteAccount->handle($user);

        if ($result === 'requested') {
            Inertia::flash('toast', ['type' => 'success', 'message' => __("Do'konni o'chirish so'rovi yuborildi.")]);

            return to_route('profile.edit');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', "Akkauntingiz o'chirildi.");
    }

    public function cancel(Request $request, DeleteAccount $deleteAccount): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $deleteAccount->cancelRequest($request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __("O'chirish so'rovi bekor qilindi.")]);

        return to_route('profile.edit');
    }

    public function destroyViaApi(Request $request, DeleteAccount $deleteAccount): JsonResponse
    {
        $result = $deleteAccount->handle($this->confirmed($request));

        return response()->json([
            'status' => $result,
            'message' => $result === 'deleted'
                ? "Akkauntingiz o'chirildi."
                : "Do'konni o'chirish so'rovi yuborildi. ".DeleteAccount::FULFIL_WITHIN_DAYS.' kun ichida bajariladi.',
        ]);
    }

    private function confirmed(Request $request): User
    {
        $request->validate(['password' => ['required', 'string']], attributes: ['password' => 'Parol']);
        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => "Parol noto'g'ri."]);
        }

        return $user;
    }
}
