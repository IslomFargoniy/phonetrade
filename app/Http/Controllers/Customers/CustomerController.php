<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Presenters\InstallmentPresenter;
use App\Http\Presenters\SalePresenter;
use App\Models\Customer;
use App\Models\CustomerFile;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    /** Pasport rasmlari saqlanadigan yopiq disk */
    public const DISK = 'local';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $digits = preg_replace('/\D+/', '', $search) ?? '';

        $customers = Customer::query()
            ->withCount(['plans as active_plans_count' => fn ($query) => $query->where('status', 'active')])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('full_name', 'like', "%{$search}%")
                ->when($digits !== '', fn ($byDigits) => $byDigits
                    ->orWhere('phone', 'like', "%{$digits}%")
                    ->orWhere('pinfl', 'like', "%{$digits}%")
                    ->orWhere('passport_number', 'like', "%{$digits}%"))))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $selected = $request->filled('customer')
            ? Customer::query()->with('files')->find((int) $request->query('customer'))
            : null;

        return Inertia::render('customers/index', [
            'q' => $search,
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'phone' => Phone::format($customer->phone),
                'active_plans' => (int) $customer->getAttribute('active_plans_count'),
            ]),
            'selected' => $selected ? [
                ...$this->card($selected),
                'sales' => Sale::query()->with(['items.unit.product:id,image_path', 'items.unit.photos', 'items.variant.product:id,image_path', 'user:id,name', 'customer:id,full_name,phone'])
                    ->where('customer_id', $selected->id)->orderByDesc('id')->limit(30)->get()
                    ->map(fn (Sale $sale) => SalePresenter::summary($sale)),
                'plans' => InstallmentPlan::query()
                    ->with(['customer', 'sale.items.unit.product:id,image_path', 'sale.items.unit.photos', 'sale.items.variant.product:id,image_path', 'schedule' => fn ($rows) => $rows->orderBy('no'), 'payments'])
                    ->where('customer_id', $selected->id)->orderByDesc('id')->get()
                    ->map(fn (InstallmentPlan $plan) => InstallmentPresenter::plan($plan)),
            ] : null,
        ]);
    }

    /**
     * Kassa oynasi uchun: telefon bo'yicha mijoz kartasini topadi.
     */
    public function lookup(Request $request): JsonResponse
    {
        $phone = Phone::normalize((string) $request->query('phone', ''));
        $customer = $phone ? Customer::query()->with('files')->where('phone', $phone)->first() : null;

        return response()->json(['customer' => $customer ? $this->card($customer) : null]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        foreach (['phone', 'phone2'] as $field) {
            if (is_string($request->input($field)) && $request->input($field) !== '') {
                $request->merge([$field => Phone::normalize($request->input($field)) ?? $request->input($field)]);
            }
        }

        if (is_string($request->input('passport_series'))) {
            $request->merge(['passport_series' => strtoupper(trim($request->input('passport_series')))]);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.Phone::PATTERN, Rule::unique(Customer::class)->where('tenant_id', $customer->tenant_id)->ignore($customer->id)],
            'phone2' => ['nullable', 'string', 'regex:'.Phone::PATTERN],
            'passport_series' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'passport_number' => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'pinfl' => ['nullable', 'string', 'regex:/^\d{14}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'passport_files' => ['nullable', 'array', 'max:5'],
            'passport_files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ], attributes: [
            'full_name' => 'F.I.Sh', 'phone' => 'Telefon', 'phone2' => "Qo'shimcha telefon",
            'passport_series' => 'Pasport seriyasi', 'passport_number' => 'Pasport raqami',
            'pinfl' => 'JSHSHIR', 'address' => 'Manzil', 'passport_files' => 'Pasport rasmi',
        ]);

        $fields = ['full_name', 'phone', 'phone2', 'passport_series', 'passport_number', 'pinfl', 'address'];
        $old = $customer->only($fields);
        $customer->update(Arr::only($data, $fields));

        self::storeFiles($customer, $request->file('passport_files'));

        Audit::log('customer.updated', $customer, $old, $customer->only($fields));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mijoz kartasi saqlandi.']);

        return back();
    }

    /**
     * Pasport rasmi faqat shu do'kon xodimiga, tizim orqali beriladi (ochiq havola yo'q).
     */
    public function file(CustomerFile $file): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($file->path), 404);

        Audit::log('customer.file_viewed', $file);

        return Storage::disk(self::DISK)->response($file->path, $file->original_name);
    }

    /**
     * @param  UploadedFile|array<int, UploadedFile>|null  $files
     */
    public static function storeFiles(Customer $customer, UploadedFile|array|null $files): void
    {
        foreach (is_array($files) ? $files : array_filter([$files]) as $file) {
            $customer->files()->create([
                'path' => $file->store("customers/{$customer->tenant_id}/{$customer->id}", self::DISK),
                'original_name' => $file->getClientOriginalName(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'full_name' => $customer->full_name,
            'phone' => Phone::format($customer->phone),
            'phone2' => $customer->phone2 ? Phone::format($customer->phone2) : null,
            'passport_series' => $customer->passport_series,
            'passport_number' => $customer->passport_number,
            'pinfl' => $customer->pinfl,
            'address' => $customer->address,
            'files' => $customer->files->map(fn (CustomerFile $file) => [
                'id' => $file->id,
                'name' => $file->original_name ?? 'Pasport',
                'url' => route('customers.files.show', $file),
            ]),
        ];
    }
}
