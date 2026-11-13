package uz.simulator.phonetrade.data

/*
 * API javoblari. Gson maydonlarni snake_case -> camelCase qilib o'qiydi (ApiClient'dagi siyosat).
 * Gson konstruktorni chetlab o'tadi, shuning uchun javobda bo'lmasligi mumkin bo'lgan
 * hamma maydon nullable.
 */

data class ApiError(
    val code: String?,
    val message: String?,
    val errors: Map<String, List<String>>?,
)

data class LoginRequest(val phone: String, val password: String, val deviceName: String)

data class Profile(
    val token: String?,
    val user: UserDto,
    val store: StoreDto,
)

data class UserDto(
    val id: Int,
    val name: String,
    val phone: String,
    val role: String,
    val canRefund: Boolean,
) {
    val isOwner: Boolean get() = role == "owner"
}

data class BranchDto(val id: Int, val name: String)

data class StoreDto(
    val name: String?,
    val branch: BranchDto?,
    val branches: List<BranchDto>?,
    val rate: String?,
    val installmentRates: List<InstallmentRateDto>?,
)

data class InstallmentRateDto(val months: Int, val markupPct: String)

data class StockUnitDto(
    val id: Int,
    val product: String,
    val imei1: String?,
    val imei2: String?,
    val barcode: String?,
    val attributes: Map<String, String>?,
    val salePrice: String,
    val saleCurrency: String,
    val salePriceLabel: String,
    val maxDiscountPct: String?,
    val warrantyMonths: Int?,
    val statusLabel: String?,
    val receivedAt: String?,
    /** Faqat egasiga keladi. */
    val costLabel: String?,
)

data class StockVariantDto(
    val id: Int,
    val product: String,
    val barcode: String?,
    val attributes: Map<String, String>?,
    val salePrice: String,
    val saleCurrency: String,
    val salePriceLabel: String,
    val maxDiscountPct: String?,
    val qty: Int,
)

data class LookupResponse(
    val exact: Boolean,
    val units: List<StockUnitDto>?,
    val variants: List<StockVariantDto>?,
)

data class StockResponse(val units: List<StockUnitDto>?)

data class CustomerRow(
    val id: Int,
    val fullName: String?,
    val phone: String?,
    val activePlans: Int,
)

data class CustomersResponse(val customers: List<CustomerRow>?)

data class CustomerCard(
    val id: Int,
    val fullName: String?,
    val phone: String?,
    val phone2: String?,
    val passportSeries: String?,
    val passportNumber: String?,
    val pinfl: String?,
    val address: String?,
    val hasPassportPhoto: Boolean,
)

data class CustomerLookupResponse(val customer: CustomerCard?)

// --- Savat ---

data class CartItemBody(
    val stockUnitId: Int?,
    val variantId: Int?,
    val qty: Int,
    val discount: String,
)

data class PaymentBody(val method: String, val currency: String, val amount: String)

data class InstallmentBody(
    val currency: String,
    val months: Int,
    val payDay: Int,
    val downPayment: String,
)

data class CartBody(
    val items: List<CartItemBody>,
    val payments: List<PaymentBody>? = null,
    val mode: String = "full",
    val installment: InstallmentBody? = null,
    val customerPhone: String? = null,
    val customerName: String? = null,
    val customerPhone2: String? = null,
    val passportSeries: String? = null,
    val passportNumber: String? = null,
    val pinfl: String? = null,
    val customerAddress: String? = null,
)

data class CartLineDto(
    val grossLabel: String?,
    val netLabel: String?,
    val finalLabel: String?,
    val maxDiscount: String?,
    val maxDiscountLabel: String?,
)

data class QuoteRowDto(val no: String?, val date: String?, val note: String?, val amountLabel: String?)

data class QuoteDto(
    val mode: String?,
    val currency: String,
    val markupPct: String?,
    val priceLabel: String?,
    val debtLabel: String?,
    val monthlyLabel: String?,
    val dueToday: String,
    val dueTodayLabel: String?,
    val rows: List<QuoteRowDto>?,
)

data class CartTotalsDto(
    val currency: String,
    val rate: String?,
    val mixed: Boolean,
    val subtotalLabel: String?,
    val discount: String?,
    val discountLabel: String?,
    val total: String,
    val totalLabel: String,
    val lines: List<CartLineDto>?,
    val installment: QuoteDto?,
)

// --- Savdo (chek) ---

data class SaleItemDto(
    val id: Int,
    val name: String,
    val qty: Int,
    val imei: String?,
    val barcode: String?,
    val priceLabel: String?,
    val discountLabel: String?,
    val hasDiscount: Boolean,
    val finalLabel: String?,
    val warrantyUntil: String?,
)

data class SalePaymentDto(val id: Int, val label: String?, val amountLabel: String?)

data class SaleDto(
    val id: Int,
    val number: Int,
    val date: String?,
    val type: String,
    val typeLabel: String?,
    val statusLabel: String?,
    val totalLabel: String,
    val seller: String?,
    val customer: String?,
    val customerPhone: String?,
    val branch: String?,
    val subtotalLabel: String?,
    val discountLabel: String?,
    val hasDiscount: Boolean,
    val changeLabel: String?,
    val hasChange: Boolean,
    val items: List<SaleItemDto>?,
    val payments: List<SalePaymentDto>?,
)

// --- Nasiya ---

data class ScheduleRowDto(
    val no: Int,
    val date: String?,
    val amountLabel: String?,
    val paidLabel: String?,
    val state: String?,
)

data class PlanPaymentDto(val id: Int, val date: String?, val amountLabel: String?, val isInitial: Boolean)

data class PlanDto(
    val id: Int,
    val saleId: Int?,
    val saleNumber: Int?,
    val customer: String?,
    val customerPhone: String?,
    val customerPhoneRaw: String?,
    val product: String?,
    val months: Int,
    val currency: String,
    val status: String?,
    val state: String?,
    val stateLabel: String?,
    val nextDate: String?,
    val nextAmount: String?,
    val nextAmountLabel: String?,
    val paidLabel: String?,
    val remaining: String?,
    val remainingLabel: String?,
    val totalLabel: String?,
    val downPaymentLabel: String?,
    val markupPct: String?,
    val createdAt: String?,
    val schedule: List<ScheduleRowDto>?,
    val payments: List<PlanPaymentDto>?,
)

data class PlansResponse(val plans: List<PlanDto>?)

data class MessageResponse(val message: String?)
