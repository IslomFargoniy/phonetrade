<?php

namespace Database\Seeders;

use App\Actions\Tenants\CreateTenant;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Faqat lokal ishlab chiqish uchun namuna ma'lumot. Productionda ishlatilmaydi:
 * u yerda superadmin `superadmin:create` buyrug'i bilan yaratiladi.
 *
 * Model hodisalari o'chirilmaydi: tenant_id aynan `creating` hodisasida to'ldiriladi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->create([
            'name' => 'Superadmin',
            'phone' => '998900000000',
            'password' => 'password',
            'role' => Role::Superadmin,
        ]);

        app(CreateTenant::class)->handle(
            name: 'Namuna do\'kon',
            branchName: 'Chilonzor filiali',
            ownerName: 'Namuna Egasi',
            ownerPhone: '998901111111',
            ownerPassword: 'password',
        );
    }
}
