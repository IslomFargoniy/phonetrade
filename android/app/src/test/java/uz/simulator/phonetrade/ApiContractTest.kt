package uz.simulator.phonetrade

import com.google.gson.FieldNamingPolicy
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import uz.simulator.phonetrade.data.ApiError
import uz.simulator.phonetrade.data.CartBody
import uz.simulator.phonetrade.data.CartItemBody
import uz.simulator.phonetrade.data.CartTotalsDto
import uz.simulator.phonetrade.data.CustomerLookupResponse
import uz.simulator.phonetrade.data.CustomersResponse
import uz.simulator.phonetrade.data.InstallmentBody
import uz.simulator.phonetrade.data.LookupResponse
import uz.simulator.phonetrade.data.PlansResponse
import uz.simulator.phonetrade.data.Profile
import uz.simulator.phonetrade.data.SaleDto
import uz.simulator.phonetrade.data.StockResponse
import uz.simulator.phonetrade.print.ReceiptPrinter
import uz.simulator.phonetrade.ui.cleanAmount
import uz.simulator.phonetrade.ui.formatPhone
import uz.simulator.phonetrade.ui.grouped
import uz.simulator.phonetrade.ui.plainAmount

/**
 * `src/test/resources/api` dagi fayllar haqiqiy serverdan olingan javoblar.
 * Test ilova modellari shu javoblarni to'g'ri o'qishini tekshiradi.
 */
class ApiContractTest {
    // ApiClient'dagi sozlama bilan bir xil bo'lishi shart.
    private val gson: Gson = GsonBuilder().setFieldNamingPolicy(FieldNamingPolicy.LOWER_CASE_WITH_UNDERSCORES).create()

    private inline fun <reified T> read(name: String): T {
        val json = checkNotNull(javaClass.getResourceAsStream("/api/$name.json")).reader().readText()
        return gson.fromJson(json, T::class.java)
    }

    @Test
    fun profile() {
        val profile = read<Profile>("login")

        assertNotNull(profile.token)
        assertTrue(profile.user.isOwner)
        assertEquals("998901111111", profile.user.phone)
        assertEquals("12750.00", profile.store.rate)
        assertEquals(listOf(3, 6, 9, 12), profile.store.installmentRates?.map { it.months })
        assertEquals("20", profile.store.installmentRates?.first()?.markupPct)
        assertEquals(profile.store.branch?.id, profile.store.branches?.first()?.id)
    }

    @Test
    fun stockLookupAndList() {
        val lookup = read<LookupResponse>("lookup")
        val unit = lookup.units!!.first()

        assertFalse(lookup.exact)
        assertEquals("Samsung Galaxy A55", unit.product)
        assertEquals("UZS", unit.saleCurrency)
        assertEquals("8/256 GB", unit.attributes?.get("Xotira"))
        // Kassa qidiruvida tannarx hech kimga kelmaydi.
        assertNull(unit.costLabel)
        assertTrue(lookup.variants!!.all { it.qty > 0 })

        // Ombor ro'yxatida egasiga tannarx keladi.
        assertNotNull(read<StockResponse>("stock").units!!.first().costLabel)
    }

    @Test
    fun previewWithInstallmentQuote() {
        val totals = read<CartTotalsDto>("preview")
        val quote = totals.installment!!

        assertEquals("4800000.00", totals.total)
        assertEquals("727500.00", totals.lines!!.single().maxDiscount)
        assertEquals("A", quote.mode)
        assertEquals("1920000.00", quote.dueToday)
        assertEquals(3, quote.rows!!.size)
        assertEquals("Savdo paytida", quote.rows!!.first().note)
    }

    @Test
    fun saleAndReceipt() {
        val sale = read<SaleDto>("sale")

        assertEquals("installment", sale.type)
        assertEquals("Test Mijoz", sale.customer)
        assertEquals("352914110000037", sale.items!!.single().imei)
        assertTrue(sale.items!!.single().hasDiscount)
        assertEquals(1, sale.payments!!.size)

        val receipt = String(ReceiptPrinter.saleReceipt(sale, "Namuna do'kon", 32), Charsets.US_ASCII)

        assertTrue(receipt.contains("Namuna do'kon"))
        assertTrue(receipt.contains("IMEI: 352914110000037"))
        assertTrue(receipt.contains("4 800 000 so'm"))
        // Har bir qator qog'oz kengligiga sig'adi.
        assertTrue(receipt.lines().filter { line -> line.all { it.code in 32..126 } }.all { it.length <= 32 })
    }

    @Test
    fun installmentsAndCustomers() {
        val plan = read<PlansResponse>("installments").plans!!.single()

        assertEquals("Test Mijoz", plan.customer)
        assertEquals("upcoming", plan.state)
        assertEquals("3840000.00", plan.remaining)
        assertEquals("1920000.00", plan.nextAmount)
        assertEquals(listOf("paid", "pending", "pending"), plan.schedule!!.map { it.state })
        assertTrue(plan.payments!!.single().isInitial)

        assertEquals(1, read<CustomersResponse>("customers").customers!!.single().activePlans)

        val card = read<CustomerLookupResponse>("customer").customer!!
        assertEquals("AB", card.passportSeries)
        assertTrue(card.hasPassportPhoto)
    }

    @Test
    fun errorBody() {
        val error = read<ApiError>("error")

        assertEquals("VALIDATION_FAILED", error.code)
        assertEquals("Savat kiritilishi shart.", error.errors!!["items"]!!.first())
    }

    @Test
    fun requestUsesSnakeCaseAndOmitsEmptyFields() {
        val json = gson.toJson(
            CartBody(
                items = listOf(CartItemBody(stockUnitId = 2, variantId = null, qty = 1, discount = "0")),
                mode = "installment",
                installment = InstallmentBody("UZS", 3, 13, "0"),
                customerPhone2 = "998901112233",
            ),
        )

        assertTrue(json.contains("\"stock_unit_id\":2"))
        assertTrue(json.contains("\"pay_day\":13"))
        assertTrue(json.contains("\"down_payment\":\"0\""))
        assertTrue(json.contains("\"customer_phone2\":\"998901112233\""))
        assertFalse(json.contains("variant_id"))
        assertFalse(json.contains("customer_name"))
    }

    @Test
    fun amountHelpers() {
        assertEquals("4700000", plainAmount("4700000.00"))
        assertEquals("12.5", plainAmount("12.50"))
        assertEquals("", plainAmount(null))
        assertEquals("1250.5", cleanAmount("1 250,5 so'm"))
        assertEquals("12.50", cleanAmount("12.5.0"))
        assertEquals("4 700 000", grouped("4700000"))
        assertEquals("12 750.5", grouped("12750.5"))
        assertEquals("+998 90 123 45 67", formatPhone("998901234567"))
    }
}
