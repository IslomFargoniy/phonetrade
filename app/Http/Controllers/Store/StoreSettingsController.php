<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\InstallmentRate;
use App\Models\Tenant;
use App\Services\Rates\CurrencyRateService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Do'kon sozlamalari: dollar kursi, nasiya ustamasi, yaxlitlash qadami, chek rekvizitlari.
 */
class StoreSettingsController extends Controller
{
    public function edit(Request $request, CurrencyRateService $rates): Response
    {
        $tenant = $this->tenant($request);

        return Inertia::render('settings/store', [
            'rateHistory' => CurrencyRate::query()
                ->orderByDesc('date')
                ->limit(7)
                ->get()
                ->map(fn (CurrencyRate $rate) => [
                    'date' => $rate->date->format('d.m.Y'),
                    'usd_uzs' => $rate->usd_uzs,
                    'source' => $rate->source,
                ]),
            'currentRate' => $rates->current()?->usd_uzs,
            'branches' => Branch::query()
                ->with('installmentRates')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'rates' => $branch->installmentRates->map(fn (InstallmentRate $rate) => [
                        'months' => $rate->months,
                        'markup_pct' => rtrim(rtrim($rate->markup_pct, '0'), '.'),
                    ]),
                ]),
            'roundStepUzs' => $tenant->setting('round_step_uzs', Tenant::DEFAULT_ROUND_STEP_UZS),
            'roundStepUsd' => $tenant->setting('round_step_usd', Tenant::DEFAULT_ROUND_STEP_USD),
            'receipt' => [
                'name' => $tenant->setting('receipt.name', $tenant->name),
                'phone' => $tenant->setting('receipt.phone'),
                'footer' => $tenant->setting('receipt.footer'),
            ],
        ]);
    }

    public function updateRate(Request $request, CurrencyRateService $rates): RedirectResponse
    {
        $data = $request->validate([
            'usd_uzs' => ['required', 'numeric', 'gt:0', 'max:9999999'],
        ], attributes: ['usd_uzs' => 'Kurs']);

        $rates->setManual((string) $data['usd_uzs'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kurs saqlandi.')]);

        return to_route('store-settings.edit');
    }

    public function updateInstallmentRates(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'rates' => ['required', 'array', 'size:'.count(InstallmentRate::DEFAULTS)],
            'rates.*.months' => ['required', 'integer', 'distinct', Rule::in(array_keys(InstallmentRate::DEFAULTS))],
            'rates.*.markup_pct' => ['required', 'numeric', 'min:0', 'max:500'],
        ], attributes: ['rates.*.markup_pct' => 'Ustama']);

        $old = $branch->installmentRates()->pluck('markup_pct', 'months')->all();

        DB::transaction(function () use ($branch, $data): void {
            foreach ($data['rates'] as $rate) {
                $branch->installmentRates()->updateOrCreate(
                    ['months' => $rate['months']],
                    ['markup_pct' => $rate['markup_pct']],
                );
            }
        });

        Audit::log('installment_rates.updated', $branch, $old, $branch->installmentRates()->pluck('markup_pct', 'months')->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ustama saqlandi.')]);

        return to_route('store-settings.edit');
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'round_step_uzs' => ['required', 'integer', 'min:1', 'max:1000000'],
            'round_step_usd' => ['required', 'integer', 'min:1', 'max:1000'],
            'receipt_name' => ['required', 'string', 'max:255'],
            'receipt_phone' => ['nullable', 'string', 'max:50'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
        ], attributes: [
            'round_step_uzs' => "Yaxlitlash (so'm)",
            'round_step_usd' => 'Yaxlitlash ($)',
            'receipt_name' => "Do'kon nomi",
            'receipt_phone' => 'Telefon',
            'receipt_footer' => 'Chek ostidagi matn',
        ]);

        $tenant = $this->tenant($request);
        $old = $tenant->settings;

        $tenant->update(['settings' => [
            ...($tenant->settings ?? []),
            'round_step_uzs' => (string) $data['round_step_uzs'],
            'round_step_usd' => (string) $data['round_step_usd'],
            'receipt' => [
                'name' => $data['receipt_name'],
                'phone' => $data['receipt_phone'] ?? null,
                'footer' => $data['receipt_footer'] ?? null,
            ],
        ]]);

        Audit::log('store_settings.updated', $tenant, $old, $tenant->settings);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sozlamalar saqlandi.')]);

        return to_route('store-settings.edit');
    }

    private function tenant(Request $request): Tenant
    {
        return Tenant::query()->findOrFail($request->user()->tenant_id);
    }
}
