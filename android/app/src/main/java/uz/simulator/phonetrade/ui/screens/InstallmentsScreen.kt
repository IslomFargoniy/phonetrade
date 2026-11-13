package uz.simulator.phonetrade.ui.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import uz.simulator.phonetrade.PhoneTradeApp
import uz.simulator.phonetrade.data.ApiException
import uz.simulator.phonetrade.data.PaymentBody
import uz.simulator.phonetrade.data.PlanDto
import uz.simulator.phonetrade.ui.AppField
import uz.simulator.phonetrade.ui.Badge
import uz.simulator.phonetrade.ui.EmptyState
import uz.simulator.phonetrade.ui.ErrorBanner
import uz.simulator.phonetrade.ui.GlassCard
import uz.simulator.phonetrade.ui.KeyValue
import uz.simulator.phonetrade.ui.LoadingBox
import uz.simulator.phonetrade.ui.PrimaryButton
import uz.simulator.phonetrade.ui.SectionTitle
import uz.simulator.phonetrade.ui.Segmented
import uz.simulator.phonetrade.ui.cleanAmount
import uz.simulator.phonetrade.ui.methodLabel
import uz.simulator.phonetrade.ui.plainAmount
import uz.simulator.phonetrade.ui.theme.Palette
import java.util.UUID

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun InstallmentsScreen(app: PhoneTradeApp) {
    var filter by rememberSaveable { mutableStateOf("today") }
    var plans by remember { mutableStateOf<List<PlanDto>?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var version by remember { mutableIntStateOf(0) }
    var opened by remember { mutableStateOf<PlanDto?>(null) }

    LaunchedEffect(filter, version) {
        plans = null
        try {
            plans = app.client.call { installments(filter) }.plans.orEmpty()
            error = null
        } catch (exception: ApiException) {
            error = exception.message
            plans = emptyList()
        }
    }

    opened?.let { plan ->
        ModalBottomSheet(
            onDismissRequest = { opened = null },
            sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
            containerColor = Palette.Background,
        ) {
            PlanSheet(app, plan) { updated ->
                opened = updated
                version++
            }
        }
    }

    Column(Modifier.fillMaxSize()) {
        Segmented(
            options = listOf("today" to "Bugun", "late" to "Kechikkan", "active" to "Hammasi"),
            selected = filter,
            onSelect = { filter = it },
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
        )
        ErrorBanner(error, Modifier.padding(horizontal = 16.dp))

        val rows = plans
        when {
            rows == null -> LoadingBox()
            rows.isEmpty() && error == null -> EmptyState(
                when (filter) {
                    "today" -> "Bugun to'lov kuni kelgan mijoz yo'q."
                    "late" -> "Kechikkan to'lov yo'q."
                    else -> "Faol nasiya shartnomasi yo'q."
                },
            )
            else -> LazyColumn(
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                items(rows, key = { it.id }) { plan ->
                    GlassCard(onClick = { opened = plan }) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(plan.customer.orEmpty(), Modifier.weight(1f), fontWeight = FontWeight.Bold, fontSize = 16.sp)
                            StateBadge(plan)
                        }
                        Text(plan.product.orEmpty(), color = Palette.InkMid, fontSize = 13.sp, maxLines = 1)
                        Spacer(Modifier.height(6.dp))
                        Row {
                            Column(Modifier.weight(1f)) {
                                Text("Navbatdagi to'lov", color = Palette.InkSoft, fontSize = 12.sp)
                                Text(
                                    listOfNotNull(plan.nextAmountLabel, plan.nextDate).joinToString(" · ").ifBlank { "—" },
                                    fontWeight = FontWeight.Bold,
                                )
                            }
                            Column(horizontalAlignment = Alignment.End) {
                                Text("Qolgan qarz", color = Palette.InkSoft, fontSize = 12.sp)
                                Text(plan.remainingLabel.orEmpty(), fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun StateBadge(plan: PlanDto) {
    val (color, background) = when (plan.state) {
        "late" -> Palette.Danger to Palette.DangerBg
        "today" -> Palette.Warning to Palette.WarningBg
        "closed" -> Palette.Success to Palette.SuccessBg
        else -> Palette.Primary to Palette.PrimaryBg
    }

    Badge(plan.stateLabel.orEmpty(), color, background)
}

@Composable
private fun PlanSheet(app: PhoneTradeApp, initial: PlanDto, onPaid: (PlanDto) -> Unit) {
    val scope = rememberCoroutineScope()
    var plan by remember { mutableStateOf(initial) }
    var amount by remember { mutableStateOf(plainAmount(initial.nextAmount)) }
    var method by remember { mutableStateOf("cash") }
    var currency by remember { mutableStateOf(initial.currency) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var success by remember { mutableStateOf<String?>(null) }
    // Aloqa uzilib qayta bosilsa to'lov ikki marta yozilmaydi.
    var key by remember { mutableStateOf(UUID.randomUUID().toString()) }
    val active = plan.status == "active"

    fun pay() {
        loading = true
        error = null
        success = null

        scope.launch {
            try {
                val updated = app.client.call { payInstallment(plan.id, key, PaymentBody(method, currency, amount)) }
                key = UUID.randomUUID().toString()
                success = "To'lov qabul qilindi."
                plan = updated
                amount = plainAmount(updated.nextAmount)
                currency = updated.currency
                onPaid(updated)
            } catch (exception: ApiException) {
                if (!exception.isRetryable) key = UUID.randomUUID().toString()
                error = exception.message
            } finally {
                loading = false
            }
        }
    }

    Column(
        Modifier
            .fillMaxWidth()
            .imePadding()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp)
            .padding(bottom = 32.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(plan.customer.orEmpty(), fontSize = 20.sp, fontWeight = FontWeight.ExtraBold)
                Text(plan.customerPhone.orEmpty(), color = Palette.InkMid)
            }
            StateBadge(plan)
        }

        GlassCard {
            KeyValue("Tovar", plan.product.orEmpty())
            KeyValue("Shartnoma", "${plan.months} oy · ${plan.markupPct.orEmpty()}% · ${plan.createdAt.orEmpty()}")
            KeyValue("Jami", plan.totalLabel.orEmpty())
            KeyValue("To'langan", plan.paidLabel.orEmpty())
            KeyValue("Qolgan qarz", plan.remainingLabel.orEmpty(), strong = true)
        }

        if (active) {
            SectionTitle("To'lov qabul qilish")
            GlassCard {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Segmented(
                        options = listOf("cash", "card", "transfer").map { it to methodLabel(it) },
                        selected = method,
                        onSelect = { method = it },
                    )
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        AppField(
                            value = amount,
                            onChange = { amount = cleanAmount(it) },
                            label = "Summa",
                            keyboard = KeyboardType.Decimal,
                            modifier = Modifier.weight(1f),
                        )
                        Segmented(
                            options = listOf("UZS" to "so'm", "USD" to "$"),
                            selected = currency,
                            onSelect = { currency = it },
                            modifier = Modifier.width(120.dp),
                        )
                    }
                    Text(
                        "Istalgan summa: eng eski to'lanmagan oylikdan boshlab yopiladi.",
                        color = Palette.InkMid,
                        fontSize = 13.sp,
                    )
                    ErrorBanner(error)
                    success?.let { Badge(it, Palette.Success, Palette.SuccessBg) }
                    PrimaryButton(
                        text = "Qabul qilish",
                        onClick = ::pay,
                        enabled = (amount.toDoubleOrNull() ?: 0.0) > 0,
                        loading = loading,
                    )
                }
            }
        }

        SectionTitle("Jadval")
        GlassCard {
            plan.schedule.orEmpty().forEach { row ->
                Row(Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
                    Text("${row.no}", Modifier.width(28.dp), color = Palette.InkSoft, fontWeight = FontWeight.Bold)
                    Column(Modifier.weight(1f)) {
                        Text(row.date.orEmpty(), fontWeight = FontWeight.SemiBold)
                        if (row.state == "partial" || row.state == "late") {
                            Text("To'langan: ${row.paidLabel.orEmpty()}", color = Palette.InkSoft, fontSize = 12.sp)
                        }
                    }
                    Text(
                        row.amountLabel.orEmpty(),
                        fontWeight = FontWeight.Bold,
                        color = when (row.state) {
                            "paid" -> Palette.Success
                            "late" -> Palette.Danger
                            else -> Color.Unspecified
                        },
                    )
                    Text(
                        when (row.state) {
                            "paid" -> "  ✓"
                            "late" -> "  !"
                            else -> "   "
                        },
                        fontWeight = FontWeight.ExtraBold,
                        color = if (row.state == "paid") Palette.Success else Palette.Danger,
                    )
                }
            }
        }

        if (plan.payments.orEmpty().isNotEmpty()) {
            SectionTitle("To'lovlar tarixi")
            GlassCard {
                plan.payments.orEmpty().forEach { payment ->
                    KeyValue(
                        payment.date.orEmpty() + if (payment.isInitial) " · savdo paytida" else "",
                        payment.amountLabel.orEmpty(),
                    )
                }
            }
        }
    }
}
