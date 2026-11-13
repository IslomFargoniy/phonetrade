package uz.simulator.phonetrade.ui.sale

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import uz.simulator.phonetrade.data.ApiClient
import uz.simulator.phonetrade.data.ApiException
import uz.simulator.phonetrade.data.CartBody
import uz.simulator.phonetrade.data.CartItemBody
import uz.simulator.phonetrade.data.CartTotalsDto
import uz.simulator.phonetrade.data.InstallmentBody
import uz.simulator.phonetrade.data.LookupResponse
import uz.simulator.phonetrade.data.PaymentBody
import uz.simulator.phonetrade.data.SaleDto
import uz.simulator.phonetrade.data.StockUnitDto
import uz.simulator.phonetrade.data.StockVariantDto
import uz.simulator.phonetrade.ui.plainAmount
import java.io.File
import java.time.LocalDate
import java.util.UUID

data class CartEntry(
    val unit: StockUnitDto? = null,
    val variant: StockVariantDto? = null,
    val qty: Int = 1,
    val discount: String = "",
) {
    val key: String get() = unit?.let { "u${it.id}" } ?: "v${variant?.id}"
    val name: String get() = unit?.product ?: variant?.product.orEmpty()
    val currency: String get() = unit?.saleCurrency ?: variant?.saleCurrency ?: "UZS"
}

data class PaymentLine(val method: String = "cash", val currency: String = "UZS", val amount: String = "")

data class CustomerForm(
    val phone: String = "+998",
    val name: String = "",
    val phone2: String = "",
    val series: String = "",
    val number: String = "",
    val pinfl: String = "",
    val address: String = "",
    /** Serverda bu mijozning pasport rasmi allaqachon bor. */
    val hasPhoto: Boolean = false,
    val found: Boolean = false,
) {
    val hasPhone: Boolean get() = phone.count { it.isDigit() } >= 9
}

enum class SaleStep { Cart, Checkout, Done }

data class SaleState(
    val step: SaleStep = SaleStep.Cart,
    val entries: List<CartEntry> = emptyList(),
    val results: LookupResponse? = null,
    val searching: Boolean = false,
    val notice: String? = null,
    val totals: CartTotalsDto? = null,
    val calculating: Boolean = false,
    val previewError: String? = null,
    val mode: String = "full",
    val payments: List<PaymentLine> = listOf(PaymentLine()),
    val paymentsTouched: Boolean = false,
    val months: Int = 3,
    val payDay: Int = LocalDate.now().dayOfMonth.coerceAtMost(28),
    val downPayment: String = "",
    val customer: CustomerForm = CustomerForm(),
    val photos: List<File> = emptyList(),
    val submitting: Boolean = false,
    val error: String? = null,
    val sale: SaleDto? = null,
) {
    val isInstallment: Boolean get() = mode == "installment"

    /** Bugun to'lanishi kerak bo'lgan summa va valyutasi. */
    val dueAmount: String get() = if (isInstallment) totals?.installment?.dueToday ?: "0" else totals?.total ?: "0"
    val dueCurrency: String get() = (if (isInstallment) totals?.installment?.currency else null) ?: totals?.currency ?: "UZS"
    val dueLabel: String get() = (if (isInstallment) totals?.installment?.dueTodayLabel else totals?.totalLabel).orEmpty()
}

class SaleViewModel(private val client: ApiClient) : ViewModel() {
    var state by mutableStateOf(SaleState())
        private set

    private var previewJob: Job? = null
    private var customerJob: Job? = null

    /** Bitta savdo urinishi uchun kalit: aloqa uzilib qayta yuborilsa savdo ikki marta yozilmaydi. */
    private var idempotencyKey = UUID.randomUUID().toString()

    // --- Qidiruv va savat ---

    fun search(code: String, fromScanner: Boolean = false) {
        val query = code.trim()
        if (query.isEmpty()) {
            state = state.copy(results = null)
            return
        }

        state = state.copy(searching = true, notice = null)

        viewModelScope.launch {
            try {
                val found = client.call { lookup(query) }
                val units = found.units.orEmpty()
                val variants = found.variants.orEmpty()

                when {
                    found.exact && units.size == 1 -> add(CartEntry(unit = units[0]))
                    found.exact && variants.size == 1 -> add(CartEntry(variant = variants[0]))
                    units.isEmpty() && variants.isEmpty() -> state = state.copy(
                        results = null,
                        notice = if (fromScanner) "Tovar topilmadi: $query" else "Hech narsa topilmadi",
                    )
                    else -> state = state.copy(results = found)
                }
            } catch (exception: ApiException) {
                state = state.copy(notice = exception.message)
            } finally {
                state = state.copy(searching = false)
            }
        }
    }

    fun add(entry: CartEntry) {
        val existing = state.entries.firstOrNull { it.key == entry.key }

        state = when {
            existing == null -> state.copy(entries = state.entries + entry, results = null, notice = null)
            existing.unit != null -> state.copy(notice = "${existing.name} savatda bor", results = null)
            else -> state.copy(
                entries = state.entries.map {
                    if (it.key == entry.key) it.copy(qty = (it.qty + 1).coerceAtMost(it.variant?.qty ?: 1)) else it
                },
                results = null,
                notice = null,
            )
        }

        recalculate()
    }

    fun remove(key: String) {
        state = state.copy(entries = state.entries.filterNot { it.key == key })
        recalculate()
    }

    fun setQty(key: String, qty: Int) {
        state = state.copy(
            entries = state.entries.map {
                if (it.key == key) it.copy(qty = qty.coerceIn(1, it.variant?.qty ?: 1)) else it
            },
        )
        recalculate()
    }

    fun setDiscount(key: String, discount: String) {
        state = state.copy(entries = state.entries.map { if (it.key == key) it.copy(discount = discount) else it })
        recalculate()
    }

    fun clearNotice() {
        state = state.copy(notice = null, results = null)
    }

    // --- To'lov ---

    fun openCheckout() {
        if (state.entries.isEmpty() || state.totals == null) return
        state = state.copy(step = SaleStep.Checkout, error = null)
    }

    fun backToCart() {
        state = state.copy(step = SaleStep.Cart, error = null)
    }

    fun setMode(mode: String) {
        state = state.copy(mode = mode, paymentsTouched = false, error = null)
        recalculate()
    }

    fun setMonths(months: Int) {
        state = state.copy(months = months)
        recalculate()
    }

    fun setPayDay(day: Int) {
        state = state.copy(payDay = day.coerceIn(1, 28))
        recalculate()
    }

    fun setDownPayment(amount: String) {
        state = state.copy(downPayment = amount)
        recalculate()
    }

    fun setPayment(index: Int, line: PaymentLine) {
        state = state.copy(
            payments = state.payments.mapIndexed { i, old -> if (i == index) line else old },
            paymentsTouched = true,
        )
    }

    fun addPayment() {
        if (state.payments.size >= 6) return
        state = state.copy(payments = state.payments + PaymentLine(currency = state.dueCurrency), paymentsTouched = true)
    }

    fun removePayment(index: Int) {
        state = state.copy(payments = state.payments.filterIndexed { i, _ -> i != index }, paymentsTouched = true)
    }

    fun setCustomer(form: CustomerForm) {
        val phoneChanged = form.phone != state.customer.phone
        state = state.copy(customer = if (phoneChanged) form.copy(found = false, hasPhoto = false) else form)

        if (phoneChanged && form.phone.count { it.isDigit() } == 12) {
            findCustomer(form.phone)
        }
    }

    /** Telefon to'liq yozilganda mijoz kartasi topilsa, maydonlar to'ldiriladi. */
    private fun findCustomer(phone: String) {
        customerJob?.cancel()
        customerJob = viewModelScope.launch {
            val card = runCatching { client.call { customerByPhone(phone) } }.getOrNull()?.customer ?: return@launch

            if (state.customer.phone != phone) return@launch

            state = state.copy(
                customer = state.customer.copy(
                    name = card.fullName.orEmpty(),
                    phone2 = card.phone2.orEmpty(),
                    series = card.passportSeries.orEmpty(),
                    number = card.passportNumber.orEmpty(),
                    pinfl = card.pinfl.orEmpty(),
                    address = card.address.orEmpty(),
                    hasPhoto = card.hasPassportPhoto,
                    found = true,
                ),
            )
        }
    }

    fun addPhoto(file: File) {
        if (state.photos.size >= 5) return
        state = state.copy(photos = state.photos + file)
    }

    fun removePhoto(file: File) {
        file.delete()
        state = state.copy(photos = state.photos - file)
    }

    // --- Hisob ---

    private fun body(withPayments: Boolean): CartBody {
        val current = state
        val customer = current.customer
        val sendCustomer = withPayments && (current.isInstallment || customer.hasPhone)

        return CartBody(
            items = current.entries.map {
                CartItemBody(
                    stockUnitId = it.unit?.id,
                    variantId = it.variant?.id,
                    qty = it.qty,
                    discount = it.discount.ifBlank { "0" },
                )
            },
            payments = if (withPayments) {
                current.payments
                    .filter { (it.amount.toDoubleOrNull() ?: 0.0) > 0 }
                    .map { PaymentBody(it.method, it.currency, it.amount) }
            } else {
                null
            },
            mode = current.mode,
            installment = if (current.isInstallment) {
                InstallmentBody(
                    currency = current.totals?.currency ?: if (current.entries.all { it.currency == "USD" }) "USD" else "UZS",
                    months = current.months,
                    payDay = current.payDay,
                    downPayment = current.downPayment.ifBlank { "0" },
                )
            } else {
                null
            },
            customerPhone = customer.phone.takeIf { sendCustomer },
            customerName = customer.name.trim().takeIf { sendCustomer && it.isNotEmpty() },
            customerPhone2 = customer.phone2.takeIf { sendCustomer && it.count(Char::isDigit) >= 9 },
            passportSeries = customer.series.trim().takeIf { sendCustomer && it.isNotEmpty() },
            passportNumber = customer.number.trim().takeIf { sendCustomer && it.isNotEmpty() },
            pinfl = customer.pinfl.trim().takeIf { sendCustomer && it.isNotEmpty() },
            customerAddress = customer.address.trim().takeIf { sendCustomer && it.isNotEmpty() },
        )
    }

    /** Savat o'zgarganda serverdan qayta hisoblatadi: ilova o'zi pul hisoblamaydi. */
    private fun recalculate() {
        previewJob?.cancel()

        if (state.entries.isEmpty()) {
            state = state.copy(totals = null, previewError = null, calculating = false, step = SaleStep.Cart)
            return
        }

        state = state.copy(calculating = true)

        previewJob = viewModelScope.launch {
            delay(250)

            try {
                val totals = client.call { preview(body(withPayments = false)) }
                state = state.copy(totals = totals, previewError = null, calculating = false)

                if (!state.paymentsTouched) {
                    state = state.copy(
                        payments = listOf(PaymentLine("cash", state.dueCurrency, plainAmount(state.dueAmount))),
                    )
                }
            } catch (exception: ApiException) {
                state = state.copy(previewError = exception.message, calculating = false)
            }
        }
    }

    fun submit() {
        if (state.submitting) return

        val current = state

        if (current.isInstallment && current.photos.isEmpty() && !current.customer.hasPhoto) {
            state = state.copy(error = "Pasport rasmini qo'shing.")
            return
        }

        state = state.copy(submitting = true, error = null)

        viewModelScope.launch {
            try {
                val request = body(withPayments = true)
                val sale = if (current.photos.isEmpty()) {
                    client.call { closeSale(idempotencyKey, request) }
                } else {
                    client.call { closeSaleMultipart(idempotencyKey, multipart(request, current.photos)) }
                }

                current.photos.forEach { it.delete() }
                idempotencyKey = UUID.randomUUID().toString()
                state = SaleState(step = SaleStep.Done, sale = sale)
            } catch (exception: ApiException) {
                // Rad etilgan so'rov serverda saqlanadi: tuzatilgan savat yangi kalit bilan yuboriladi.
                if (!exception.isRetryable) {
                    idempotencyKey = UUID.randomUUID().toString()
                }

                state = state.copy(submitting = false, error = exception.message)
            }
        }
    }

    fun startNew() {
        state = SaleState()
    }

    /** Laravel ichma-ich maydonlarni `items[0][qty]` ko'rinishida o'qiydi. */
    private fun multipart(body: CartBody, photos: List<File>): MultipartBody {
        val form = MultipartBody.Builder().setType(MultipartBody.FORM)

        fun put(name: String, value: Any?) {
            if (value != null) form.addFormDataPart(name, value.toString())
        }

        body.items.forEachIndexed { index, item ->
            put("items[$index][stock_unit_id]", item.stockUnitId)
            put("items[$index][variant_id]", item.variantId)
            put("items[$index][qty]", item.qty)
            put("items[$index][discount]", item.discount)
        }
        body.payments.orEmpty().forEachIndexed { index, payment ->
            put("payments[$index][method]", payment.method)
            put("payments[$index][currency]", payment.currency)
            put("payments[$index][amount]", payment.amount)
        }
        put("mode", body.mode)
        body.installment?.let {
            put("installment[currency]", it.currency)
            put("installment[months]", it.months)
            put("installment[pay_day]", it.payDay)
            put("installment[down_payment]", it.downPayment)
        }
        put("customer_phone", body.customerPhone)
        put("customer_name", body.customerName)
        put("customer_phone2", body.customerPhone2)
        put("passport_series", body.passportSeries)
        put("passport_number", body.passportNumber)
        put("pinfl", body.pinfl)
        put("customer_address", body.customerAddress)

        photos.forEach { file ->
            form.addFormDataPart("passport_files[]", file.name, file.asRequestBody("image/jpeg".toMediaType()))
        }

        return form.build()
    }
}
