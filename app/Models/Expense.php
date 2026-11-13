<?php

namespace App\Models;

use App\Enums\Currency;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Xarajat. Kassadan chiqim sifatida ham yoziladi va sof foydani kamaytiradi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $expense_category_id
 * @property int $cash_account_id
 * @property int|null $user_id
 * @property string $amount
 * @property Currency $currency
 * @property string|null $rate
 * @property Carbon $date
 * @property string|null $note
 */
#[Fillable(['branch_id', 'expense_category_id', 'cash_account_id', 'user_id', 'amount', 'currency', 'rate', 'date', 'note'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'rate' => 'decimal:2',
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }
}
