<?php

namespace App\Http\Controllers\Store;

use App\Actions\Branches\CreateBranch;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\Billing\Subscription;
use App\Support\Audit;
use App\Support\Phone;
use App\Tenancy\CurrentBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/branches', [
            'branches' => Branch::query()
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'address' => $branch->address,
                    'phone' => $branch->phone ? Phone::format($branch->phone) : null,
                    'is_active' => $branch->is_active,
                    'users_count' => $branch->users_count,
                ]),
        ]);
    }

    public function store(Request $request, CreateBranch $createBranch, Subscription $subscription): RedirectResponse
    {
        $data = $this->validated($request);
        $subscription->ensureCanAddBranch(Tenant::query()->findOrFail($request->user()->tenant_id));

        $branch = $createBranch->handle($data);

        Audit::log('branch.created', $branch, null, ['name' => $branch->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Filial ochildi.')]);

        return to_route('branches.index');
    }

    public function update(Request $request, Branch $branch, Subscription $subscription): RedirectResponse
    {
        $data = $this->validated($request, $branch) + [
            'is_active' => $request->boolean('is_active', $branch->is_active),
        ];

        // Yopilgan filialni qayta ochish ham tarif limitiga kiradi.
        if ($data['is_active'] && ! $branch->is_active) {
            $subscription->ensureCanAddBranch(Tenant::query()->findOrFail($request->user()->tenant_id));
        }

        if (! $data['is_active'] && $branch->is_active && Branch::query()->where('is_active', true)->count() === 1) {
            return back()->withErrors(['is_active' => __("Oxirgi faol filialni o'chirib bo'lmaydi.")]);
        }

        $old = $branch->only(['name', 'address', 'phone', 'is_active']);
        $branch->update($data);

        Audit::log('branch.updated', $branch, $old, $branch->only(['name', 'address', 'phone', 'is_active']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Filial saqlandi.')]);

        return to_route('branches.index');
    }

    /**
     * Foydalanuvchi ishlayotgan filialni almashtiradi (egasi yoki bir nechta filialga biriktirilgan sotuvchi).
     */
    public function select(Request $request, Branch $branch, CurrentBranch $currentBranch): RedirectResponse
    {
        $currentBranch->switchTo($request->user(), $branch);

        return back();
    }

    /**
     * @return array{name: string, address: string|null, phone: string|null}
     */
    private function validated(Request $request, ?Branch $branch = null): array
    {
        if (is_string($request->input('phone')) && $request->input('phone') !== '') {
            $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);
        }

        /** @var array{name: string, address?: string|null, phone?: string|null} $data */
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(Branch::class)
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->ignore($branch?->id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:'.Phone::PATTERN],
        ], attributes: ['name' => 'Filial nomi', 'address' => 'Manzil', 'phone' => 'Telefon']);

        return [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];
    }
}
