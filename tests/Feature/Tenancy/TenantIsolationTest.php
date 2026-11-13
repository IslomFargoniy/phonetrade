<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * app/Models ichidagi BelongsToTenant ishlatadigan barcha modellar. Yangi model
     * qo'shilganda u shu testga avtomatik tushadi.
     *
     * @return array<string, array{class-string<Model>}>
     */
    public static function tenantModels(): array
    {
        $models = [];

        // Data provider ilova yuklanmasdan oldin ishlaydi, shuning uchun fasadlarsiz yoziladi.
        foreach (glob(dirname(__DIR__, 3).'/app/Models/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $class = 'App\\Models\\'.$name;

            if (in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                $models[$name] = [$class];
            }
        }

        return $models;
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('tenantModels')]
    public function test_model_only_sees_rows_of_the_current_tenant(string $model): void
    {
        $ours = Tenant::factory()->create();
        $theirs = Tenant::factory()->create();

        $own = $model::factory()->create(['tenant_id' => $ours->id]);
        $foreign = $model::factory()->create(['tenant_id' => $theirs->id]);

        $context = app(TenantContext::class);
        $context->set($ours);

        $visible = $model::query()->pluck('tenant_id')->unique()->values()->all();

        $this->assertSame([$ours->id], $visible);
        $this->assertNotNull($model::query()->find($own->getKey()));
        $this->assertNull($model::query()->find($foreign->getKey()));

        $context->forget();
    }

    public function test_tenant_id_is_filled_from_the_context(): void
    {
        $tenant = Tenant::factory()->create();

        $branch = app(TenantContext::class)->runFor($tenant, fn () => Branch::query()->create(['name' => 'Chilonzor']));

        $this->assertSame($tenant->id, $branch->tenant_id);
    }

    public function test_tenant_row_cannot_be_created_without_a_tenant(): void
    {
        $this->expectException(LogicException::class);

        Branch::query()->create(['name' => 'Egasiz filial']);
    }

    public function test_another_tenants_record_cannot_be_reached_through_a_route(): void
    {
        $owner = User::factory()->create();
        $foreignBranch = Branch::factory()->create();

        $this->actingAs($owner)
            ->patch(route('branches.update', $foreignBranch), ['name' => 'Egallab olindi'])
            ->assertNotFound();

        $this->assertNotSame('Egallab olindi', $foreignBranch->fresh()->name);
    }

    public function test_context_does_not_leak_into_the_next_request(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get(route('profile.edit'))->assertOk();

        $this->assertFalse(app(TenantContext::class)->has());
    }
}
