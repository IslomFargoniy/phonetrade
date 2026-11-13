<?php

namespace App\Actions\Tenants;

use App\Actions\Branches\CreateBranch;
use App\Enums\CashAccountType;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\CashAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Yangi do'kon ochadi: do'kon, egasining akkaunti, admin kassalar va birinchi filial.
 */
class CreateTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CreateBranch $createBranch,
    ) {}

    public function handle(
        string $name,
        string $branchName,
        string $ownerName,
        string $ownerPhone,
        string $ownerPassword,
    ): Tenant {
        return DB::transaction(function () use ($name, $branchName, $ownerName, $ownerPhone, $ownerPassword): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $name,
                'status' => TenantStatus::Active,
                'settings' => [
                    'round_step_uzs' => Tenant::DEFAULT_ROUND_STEP_UZS,
                    'round_step_usd' => Tenant::DEFAULT_ROUND_STEP_USD,
                    'receipt' => ['name' => $name, 'phone' => null, 'footer' => null],
                ],
            ]);

            $this->context->runFor($tenant, function () use ($branchName, $ownerName, $ownerPhone, $ownerPassword): void {
                User::query()->create([
                    'name' => $ownerName,
                    'phone' => $ownerPhone,
                    'password' => $ownerPassword,
                    'role' => Role::Owner,
                ]);

                // Egasining admin kassalari: har turdan bittadan, filialga bog'lanmagan.
                foreach (CashAccountType::cases() as $type) {
                    CashAccount::query()->create(['branch_id' => null, 'type' => $type]);
                }

                $this->createBranch->handle(['name' => $branchName]);
            });

            return $tenant;
        });
    }
}
