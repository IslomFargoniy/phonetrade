<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Tenancy\CurrentBranch;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * Foydalanuvchi hozir ishlayotgan filial. Filial biriktirilmagan bo'lsa ish davom etmaydi.
     */
    protected function branch(Request $request): Branch
    {
        $branch = app(CurrentBranch::class)->resolve($this->user($request));

        abort_if($branch === null, 403, 'Sizga filial biriktirilmagan.');

        return $branch;
    }
}
