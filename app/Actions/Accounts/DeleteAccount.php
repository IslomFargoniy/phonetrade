<?php

namespace App\Actions\Accounts;

use App\Exceptions\BusinessRuleException;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Akkauntni o'chirish.
 *
 * - Sotuvchi: akkaunti darhol o'chiriladi. U qilgan savdolar do'kon hisobida qoladi,
 *   shuning uchun yozuv saqlanib, shaxsiy ma'lumoti (ism, telefon, parol) tozalanadi.
 * - Do'kon egasi: butun do'konni o'chirish so'rovi yoziladi; superadmin bajaradi
 *   (savdo va nasiya hisobi bexosdan yo'qolmasligi uchun).
 */
class DeleteAccount
{
    /** So'rov shu muddat ichida bajariladi (maxfiylik siyosatida ham shunday yozilgan). */
    public const FULFIL_WITHIN_DAYS = 30;

    /**
     * @return 'deleted'|'requested'
     */
    public function handle(User $user): string
    {
        if ($user->isSuperadmin()) {
            throw new BusinessRuleException('ACCOUNT_NOT_DELETABLE', "Superadmin akkaunti bu yerdan o'chirilmaydi.", 'password');
        }

        if ($user->isOwner()) {
            Tenant::query()->whereKey($user->tenant_id)->whereNull('deletion_requested_at')->update(['deletion_requested_at' => now()]);
            Audit::log('tenant.deletion_requested', $user);

            return 'requested';
        }

        DB::transaction(function () use ($user): void {
            Audit::log('user.deleted', $user, ['name' => $user->name]);

            $user->tokens()->delete();
            $user->branches()->detach();
            $user->forceFill([
                'name' => "O'chirilgan xodim",
                // Telefon ustuni noyob: bo'shatilgan raqam bilan yangi xodim ochish mumkin bo'ladi.
                'phone' => 'deleted-'.$user->id,
                'password' => Str::random(40),
                'is_active' => false,
                'can_refund' => false,
                'telegram_chat_id' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
            ])->save();
        });

        return 'deleted';
    }

    public function cancelRequest(User $owner): void
    {
        Tenant::query()->whereKey($owner->tenant_id)->update(['deletion_requested_at' => null]);
        Audit::log('tenant.deletion_cancelled', $owner);
    }

    /**
     * Do'konni butunlay o'chiradi: foydalanuvchilar, savdolar, mijozlar va pasport rasmlari bilan.
     * Qaytarib bo'lmaydi.
     */
    public function purgeTenant(Tenant $tenant): void
    {
        $id = $tenant->id;

        DB::transaction(function () use ($tenant, $id): void {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', User::query()->where('tenant_id', $id)->select('id'))
                ->delete();

            /*
             * Do'kon jadvallari bir-biriga bog'langan (masalan, kirim qatori mahsulotga), shuning
             * uchun bitta kaskad bilan o'chmaydi. Har o'tishda bog'lanishi bo'shagan jadvallar
             * tozalanadi; tartib sxemadan o'zi kelib chiqadi va yangi jadval qo'shilsa ham ishlaydi.
             */
            $pending = array_values(array_filter(
                array_column(Schema::getTables(), 'name'),
                fn (string $table) => $table !== 'tenants' && Schema::hasColumn($table, 'tenant_id'),
            ));

            for ($pass = 0; $pending !== [] && $pass < 12; $pass++) {
                foreach ($pending as $index => $table) {
                    try {
                        DB::transaction(fn () => DB::table($table)->where('tenant_id', $id)->delete());
                        unset($pending[$index]);
                    } catch (QueryException) {
                        // Hali boshqa jadval shu yozuvlarga tayanadi — keyingi o'tishda.
                    }
                }
            }

            if ($pending !== []) {
                throw new RuntimeException("Do'konni o'chirib bo'lmadi: ".implode(', ', $pending));
            }

            $tenant->delete();
        });

        Storage::disk('local')->deleteDirectory("customers/{$id}");
    }
}
