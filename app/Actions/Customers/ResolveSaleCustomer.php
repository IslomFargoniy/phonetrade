<?php

namespace App\Actions\Customers;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Requests\Sales\CartRequest;
use App\Models\Customer;
use App\Support\Phone;

/**
 * Savdo uchun mijozni aniqlaydi.
 *
 * To'liq to'lovda mijoz ixtiyoriy: telefon kiritilsa topiladi yoki yaratiladi.
 * Muddatli savdoda karta to'liq to'ldiriladi va pasport rasmi bo'lishi shart.
 */
class ResolveSaleCustomer
{
    public function handle(CartRequest $request): ?Customer
    {
        $phone = $request->string('customer_phone')->toString();

        if ($phone === '') {
            return null;
        }

        $name = $request->string('customer_name')->trim()->toString();
        $customer = Customer::query()->firstOrCreate(
            ['phone' => $phone],
            ['full_name' => $name !== '' ? $name : Phone::format($phone)],
        );

        if (! $request->isInstallment()) {
            return $customer;
        }

        $customer->update([
            'full_name' => $name,
            'phone2' => $request->string('customer_phone2')->toString() ?: null,
            'passport_series' => $request->string('passport_series')->toString(),
            'passport_number' => $request->string('passport_number')->toString(),
            'pinfl' => $request->string('pinfl')->toString(),
            'address' => $request->string('customer_address')->toString(),
        ]);

        CustomerController::storeFiles($customer, $request->file('passport_files'));

        if ($customer->files()->doesntExist()) {
            throw new BusinessRuleException('PASSPORT_PHOTO_REQUIRED', 'Pasport rasmini yuklang.', 'passport_files');
        }

        return $customer;
    }
}
