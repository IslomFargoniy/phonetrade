<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('superadmin:create')]
#[Description('Platforma superadminini yaratadi (ism, telefon va parol so\'raladi)')]
class CreateSuperadmin extends Command
{
    public function handle(): int
    {
        $name = text('Ism', required: true);

        $phone = Phone::normalize(text('Telefon raqami', placeholder: '+998 90 123 45 67', required: true));

        if ($phone === null) {
            $this->error("Telefon raqami noto'g'ri.");

            return self::FAILURE;
        }

        if (User::query()->where('phone', $phone)->exists()) {
            $this->error('Bu telefon raqami band.');

            return self::FAILURE;
        }

        $password = password('Parol (kamida 8 belgi)', required: true);

        if (mb_strlen($password) < 8) {
            $this->error('Parol kamida 8 belgidan iborat bo\'lishi kerak.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'phone' => $phone,
            'password' => $password,
            'role' => Role::Superadmin,
        ]);

        $this->info('Superadmin yaratildi: '.Phone::format($phone));

        return self::SUCCESS;
    }
}
