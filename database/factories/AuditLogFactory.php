<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'action' => 'test.action',
            'old' => null,
            'new' => null,
        ];
    }
}
