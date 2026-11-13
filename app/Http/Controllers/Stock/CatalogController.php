<?php

namespace App\Http\Controllers\Stock;

use App\Enums\AttributeType;
use App\Enums\Tracking;
use App\Http\Controllers\Controller;
use App\Http\Presenters\StockPresenter;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Katalog sozlamalari: kategoriyalar, kriteriyalar, mahsulotlar va yetkazib beruvchilar.
 */
class CatalogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/catalog', [
            'categories' => Category::query()->withCount('products')->orderBy('name')->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'tracking' => $category->tracking->value,
                    'tracking_label' => $category->tracking->label(),
                    'products_count' => $category->products_count,
                ]),
            'attributes' => Attribute::query()->with(['options' => fn ($query) => $query->orderBy('sort')->orderBy('id'), 'categories:id'])
                ->orderBy('sort')->orderBy('id')->get()
                ->map(fn (Attribute $attribute) => [
                    'id' => $attribute->id,
                    'name' => $attribute->name,
                    'type' => $attribute->type->value,
                    'type_label' => $attribute->type->label(),
                    'options' => $attribute->options->pluck('value')->values(),
                    'category_ids' => $attribute->categories->modelKeys(),
                ]),
            'products' => Product::query()->with('category:id,name')->orderBy('name')->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category_id' => $product->category_id,
                    'category' => $product->category->name,
                    'default_warranty_months' => $product->default_warranty_months,
                    'default_max_discount_pct' => StockPresenter::trimDecimal($product->default_max_discount_pct),
                    'is_active' => $product->is_active,
                ]),
            'suppliers' => Supplier::query()->orderBy('name')->get()
                ->map(fn (Supplier $supplier) => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'phone' => $supplier->phone ? Phone::format($supplier->phone) : null,
                    'note' => $supplier->note,
                ]),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', $this->uniqueInTenant($request, Category::class)],
            'tracking' => ['required', Rule::enum(Tracking::class)],
        ], attributes: ['name' => 'Kategoriya nomi', 'tracking' => 'Hisob turi']);

        Category::query()->create($data);

        return $this->done('Kategoriya qo\'shildi.');
    }

    public function storeAttribute(Request $request): RedirectResponse
    {
        $data = $this->validateAttribute($request);

        DB::transaction(function () use ($data): void {
            $attribute = Attribute::query()->create([
                'name' => $data['name'],
                'type' => $data['type'],
                'sort' => (int) Attribute::query()->max('sort') + 1,
            ]);

            $this->syncAttribute($attribute, $data);
        });

        return $this->done('Kriteriya qo\'shildi.');
    }

    public function updateAttribute(Request $request, Attribute $attribute): RedirectResponse
    {
        $data = $this->validateAttribute($request, $attribute);

        DB::transaction(function () use ($attribute, $data): void {
            $attribute->update(['name' => $data['name'], 'type' => $data['type']]);
            $this->syncAttribute($attribute, $data);
        });

        return $this->done('Kriteriya saqlandi.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        Product::query()->create($this->validateProduct($request));

        return $this->done('Mahsulot qo\'shildi.');
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $old = $product->only(['name', 'default_warranty_months', 'default_max_discount_pct', 'is_active']);
        $product->update($this->validateProduct($request, $product) + ['is_active' => $request->boolean('is_active', true)]);

        Audit::log('product.updated', $product, $old, $product->only(array_keys($old)));

        return $this->done('Mahsulot saqlandi.');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        if (is_string($request->input('phone')) && $request->input('phone') !== '') {
            $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:'.Phone::PATTERN],
            'note' => ['nullable', 'string', 'max:255'],
        ], attributes: ['name' => 'Nomi', 'phone' => 'Telefon', 'note' => 'Izoh']);

        Supplier::query()->create($data);

        return $this->done('Yetkazib beruvchi qo\'shildi.');
    }

    /**
     * @return array{name: string, type: string, options: list<string>, category_ids: list<int>}
     */
    private function validateAttribute(Request $request, ?Attribute $attribute = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', $this->uniqueInTenant($request, Attribute::class, $attribute?->id)],
            'type' => ['required', Rule::enum(AttributeType::class)],
            // Qiymatlar vergul yoki yangi qator bilan ajratib yoziladi
            'options' => ['nullable', 'string', 'max:2000'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', Rule::exists(Category::class, 'id')->where('tenant_id', $request->user()->tenant_id)],
        ], attributes: ['name' => 'Kriteriya nomi', 'type' => 'Turi', 'options' => 'Qiymatlar', 'category_ids' => 'Kategoriyalar']);

        $type = $request->enum('type', AttributeType::class) ?? AttributeType::Text;

        $options = collect(preg_split('/[,\n]+/', $request->string('options')->toString()) ?: [])
            ->map(fn (string $option) => trim($option))
            ->filter(fn (string $option) => $option !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'name' => $request->string('name')->toString(),
            'type' => $type->value,
            'options' => $type === AttributeType::Select ? array_values($options) : [],
            'category_ids' => array_values(array_map('intval', (array) $data['category_ids'])),
        ];
    }

    /**
     * @param  array{options: list<string>, category_ids: list<int>}  $data
     */
    private function syncAttribute(Attribute $attribute, array $data): void
    {
        $attribute->categories()->sync($data['category_ids']);

        $attribute->options()->whereNotIn('value', $data['options'])->delete();
        $existing = $attribute->options()->pluck('value')->all();

        foreach ($data['options'] as $sort => $value) {
            if (in_array($value, $existing, true)) {
                AttributeOption::query()->where('attribute_id', $attribute->id)->where('value', $value)->update(['sort' => $sort]);
            } else {
                $attribute->options()->create(['value' => $value, 'sort' => $sort]);
            }
        }
    }

    /**
     * @return array{category_id: int, name: string, default_warranty_months: int, default_max_discount_pct: string}
     */
    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')->where('tenant_id', $request->user()->tenant_id)],
            'name' => ['required', 'string', 'max:255', $this->uniqueInTenant($request, Product::class, $product?->id)],
            'default_warranty_months' => ['required', 'integer', 'min:0', 'max:120'],
            'default_max_discount_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ], attributes: [
            'category_id' => 'Kategoriya',
            'name' => 'Mahsulot nomi',
            'default_warranty_months' => 'Kafolat',
            'default_max_discount_pct' => 'Max chegirma',
        ]);

        return [
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'default_warranty_months' => (int) $data['default_warranty_months'],
            'default_max_discount_pct' => (string) $data['default_max_discount_pct'],
        ];
    }

    /**
     * @param  class-string  $model
     */
    private function uniqueInTenant(Request $request, string $model, ?int $ignoreId = null): Unique
    {
        return Rule::unique($model, 'name')->where('tenant_id', $request->user()->tenant_id)->ignore($ignoreId);
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('catalog.index');
    }
}
