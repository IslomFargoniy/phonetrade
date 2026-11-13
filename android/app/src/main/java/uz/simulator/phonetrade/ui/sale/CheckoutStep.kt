package uz.simulator.phonetrade.ui.sale

import uz.simulator.phonetrade.ui.ProductImage
import android.net.Uri
import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.Print
import androidx.compose.material3.FilterChip
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import uz.simulator.phonetrade.PhoneTradeApp
import uz.simulator.phonetrade.data.SaleDto
import uz.simulator.phonetrade.print.ReceiptPrinter
import uz.simulator.phonetrade.ui.AppField
import uz.simulator.phonetrade.ui.Badge
import uz.simulator.phonetrade.ui.ErrorBanner
import uz.simulator.phonetrade.ui.GlassCard
import uz.simulator.phonetrade.ui.KeyValue
import uz.simulator.phonetrade.ui.PrimaryButton
import uz.simulator.phonetrade.ui.SectionTitle
import uz.simulator.phonetrade.ui.Segmented
import uz.simulator.phonetrade.ui.cleanAmount
import uz.simulator.phonetrade.ui.currencyLabel
import uz.simulator.phonetrade.ui.methodLabel
import uz.simulator.phonetrade.ui.theme.Palette
import java.io.File

@Composable
fun CheckoutStep(app: PhoneTradeApp, model: SaleViewModel) {
    val state = model.state
    val profile by app.session.profile.collectAsStateWithLifecycle()
    val rates = profile?.store?.installmentRates.orEmpty()

    BackHandler(onBack = model::backToCart)

    Column(Modifier.fillMaxSize().imePadding()) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = model::backToCart) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Savatga qaytish")
            }
            Text("To'lov", fontSize = 20.sp, fontWeight = FontWeight.ExtraBold)
        }

        Column(
            Modifier.weight(1f).verticalScroll(rememberScrollState()).padding(horizontal = 16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Segmented(
                options = listOf("full" to "To'liq to'lov", "installment" to "Muddatli"),
                selected = state.mode,
                onSelect = model::setMode,
            )

            GlassCard {
                KeyValue("Savat jami", state.totals?.totalLabel.orEmpty())
                if ((state.totals?.discount?.toDoubleOrNull() ?: 0.0) > 0) {
                    KeyValue("Chegirma", "−${state.totals?.discountLabel.orEmpty()}")
                }
                state.totals?.installment?.takeIf { state.isInstallment }?.let { quote ->
                    KeyValue("Ustama", "${quote.markupPct.orEmpty()}%")
                    KeyValue("Nasiya qarzi", quote.debtLabel.orEmpty())
                    KeyValue("Oylik to'lov", quote.monthlyLabel.orEmpty())
                }
                KeyValue(if (state.isInstallment) "Bugun to'lanadi" else "To'lanadi", state.dueLabel, strong = true)
            }

            if (state.isInstallment) {
                InstallmentTerms(model, rates.map { it.months to "${it.months} oy · ${it.markupPct}%" })
            }

            ErrorBanner(state.previewError)

            SectionTitle("To'lov usuli")
            state.payments.forEachIndexed { index, line ->
                PaymentRow(
                    line = line,
                    removable = state.payments.size > 1,
                    onChange = { model.setPayment(index, it) },
                    onRemove = { model.removePayment(index) },
                )
            }
            TextButton(onClick = model::addPayment) { Text("+ Yana bir to'lov usuli", fontWeight = FontWeight.Bold) }

            if (state.isInstallment) {
                CustomerSection(model, full = true)
                PassportPhotos(model)
                state.totals?.installment?.let { quote ->
                    SectionTitle("To'lov jadvali")
                    GlassCard {
                        quote.rows.orEmpty().forEach { row ->
                            Row(Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
                                Text(row.no.orEmpty(), Modifier.width(28.dp), color = Palette.InkSoft, fontWeight = FontWeight.Bold)
                                Column(Modifier.weight(1f)) {
                                    Text(row.date.orEmpty(), fontWeight = FontWeight.SemiBold)
                                    Text(row.note.orEmpty(), color = Palette.InkSoft, fontSize = 12.sp)
                                }
                                Text(row.amountLabel.orEmpty(), fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            } else {
                CustomerSection(model, full = false)
            }

            Spacer(Modifier.height(4.dp))
        }

        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            ErrorBanner(state.error)
            PrimaryButton(
                text = if (state.isInstallment) "Shartnomani rasmiylashtirish" else "Savdoni yopish",
                onClick = model::submit,
                enabled = state.totals != null && state.previewError == null && !state.calculating,
                loading = state.submitting,
            )
        }
    }
}

@Composable
private fun InstallmentTerms(model: SaleViewModel, months: List<Pair<Int, String>>) {
    val state = model.state

    SectionTitle("Muddat")
    Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        months.forEach { (value, label) ->
            FilterChip(selected = state.months == value, onClick = { model.setMonths(value) }, label = { Text(label) })
        }
    }

    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        AppField(
            value = state.payDay.toString(),
            onChange = { input -> input.filter(Char::isDigit).take(2).toIntOrNull()?.let(model::setPayDay) },
            label = "To'lov kuni",
            keyboard = KeyboardType.Number,
            modifier = Modifier.weight(1f),
        )
        AppField(
            value = state.downPayment,
            onChange = { model.setDownPayment(cleanAmount(it)) },
            label = "Boshlang'ich to'lov",
            keyboard = KeyboardType.Decimal,
            suffix = currencyLabel(state.dueCurrency),
            modifier = Modifier.weight(1.4f),
        )
    }
    Text(
        if (state.totals?.installment?.mode == "B") {
            "Boshlang'ich to'lov ustamasiz; ustama qolgan summaga qo'shiladi."
        } else {
            "Boshlang'ich to'lovsiz: birinchi oylik savdo paytida olinadi. To'lov kuni: oyning 1–28-kuni."
        },
        color = Palette.InkMid,
        fontSize = 13.sp,
    )
}

@Composable
private fun PaymentRow(line: PaymentLine, removable: Boolean, onChange: (PaymentLine) -> Unit, onRemove: () -> Unit) {
    GlassCard {
        Segmented(
            options = listOf("cash", "card", "transfer").map { it to methodLabel(it) },
            selected = line.method,
            onSelect = { onChange(line.copy(method = it)) },
        )
        Spacer(Modifier.height(8.dp))
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            AppField(
                value = line.amount,
                onChange = { onChange(line.copy(amount = cleanAmount(it))) },
                label = "Summa",
                keyboard = KeyboardType.Decimal,
                modifier = Modifier.weight(1f),
            )
            Segmented(
                options = listOf("UZS" to "so'm", "USD" to "$"),
                selected = line.currency,
                onSelect = { onChange(line.copy(currency = it)) },
                modifier = Modifier.width(120.dp),
            )
            if (removable) {
                IconButton(onClick = onRemove) { Icon(Icons.Default.Close, contentDescription = "Olib tashlash") }
            }
        }
    }
}

@Composable
private fun CustomerSection(model: SaleViewModel, full: Boolean) {
    val customer = model.state.customer

    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        SectionTitle(if (full) "Mijoz" else "Mijoz (ixtiyoriy)")
        if (customer.found) Badge("Bazada bor", Palette.Success, Palette.SuccessBg)
    }

    GlassCard {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            AppField(
                value = customer.phone,
                onChange = { model.setCustomer(customer.copy(phone = it)) },
                label = "Telefon",
                keyboard = KeyboardType.Phone,
            )
            AppField(
                value = customer.name,
                onChange = { model.setCustomer(customer.copy(name = it)) },
                label = "F.I.Sh",
            )

            if (full) {
                AppField(
                    value = customer.phone2,
                    onChange = { model.setCustomer(customer.copy(phone2 = it)) },
                    label = "Qo'shimcha telefon (ixtiyoriy)",
                    keyboard = KeyboardType.Phone,
                )
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    AppField(
                        value = customer.series,
                        onChange = { model.setCustomer(customer.copy(series = it.filter(Char::isLetter).take(2).uppercase())) },
                        label = "Seriya",
                        modifier = Modifier.weight(1f),
                    )
                    AppField(
                        value = customer.number,
                        onChange = { model.setCustomer(customer.copy(number = it.filter(Char::isDigit).take(7))) },
                        label = "Pasport raqami",
                        keyboard = KeyboardType.Number,
                        modifier = Modifier.weight(2f),
                    )
                }
                AppField(
                    value = customer.pinfl,
                    onChange = { model.setCustomer(customer.copy(pinfl = it.filter(Char::isDigit).take(14))) },
                    label = "JSHSHIR (14 raqam)",
                    keyboard = KeyboardType.Number,
                )
                AppField(
                    value = customer.address,
                    onChange = { model.setCustomer(customer.copy(address = it)) },
                    label = "Manzil",
                )
            }
        }
    }
}

@Composable
private fun PassportPhotos(model: SaleViewModel) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val state = model.state
    var pending by remember { mutableStateOf<File?>(null) }

    fun import(uri: Uri, temporary: File? = null) {
        scope.launch {
            val file = withContext(Dispatchers.IO) { Photos.import(context, uri) }
            temporary?.delete()
            file?.let(model::addPhoto)
        }
    }

    val camera = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { saved ->
        val file = pending
        pending = null

        if (saved && file != null) import(Photos.uriFor(context, file), file) else file?.delete()
    }
    val gallery = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let { import(it) }
    }

    SectionTitle("Pasport rasmi")
    GlassCard {
        if (state.customer.hasPhoto) {
            Text("Bu mijozning pasport rasmi bazada bor — qayta olish shart emas.", color = Palette.Success, fontSize = 13.sp)
            Spacer(Modifier.height(8.dp))
        }

        state.photos.forEachIndexed { index, file ->
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.Image, contentDescription = null, tint = Palette.Primary)
                Text("  Rasm ${index + 1} · ${file.length() / 1024} KB", Modifier.weight(1f), fontWeight = FontWeight.SemiBold)
                IconButton(onClick = { model.removePhoto(file) }) {
                    Icon(Icons.Default.Close, contentDescription = "O'chirish")
                }
            }
        }

        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedButton(
                onClick = {
                    val file = Photos.newFile(context)
                    pending = file
                    camera.launch(Photos.uriFor(context, file))
                },
                enabled = state.photos.size < 5,
                modifier = Modifier.weight(1f),
            ) {
                Icon(Icons.Default.PhotoCamera, contentDescription = null)
                Text("  Suratga olish")
            }
            OutlinedButton(onClick = { gallery.launch("image/*") }, enabled = state.photos.size < 5, modifier = Modifier.weight(1f)) {
                Icon(Icons.Default.Image, contentDescription = null)
                Text("  Galereya")
            }
        }
    }
}

@Composable
fun DoneStep(app: PhoneTradeApp, sale: SaleDto, onNew: () -> Unit) {
    Column(Modifier.fillMaxSize()) {
        Column(
            Modifier.weight(1f).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Savdo yopildi", fontSize = 26.sp, fontWeight = FontWeight.ExtraBold, color = Palette.Success)
            SaleReceiptCard(app, sale)
        }
        PrimaryButton("Yangi savdo", onNew, Modifier.padding(16.dp))
    }
}

/** Chek ko'rinishi va chop etish tugmasi. */
@Composable
fun SaleReceiptCard(app: PhoneTradeApp, sale: SaleDto) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val profile by app.session.profile.collectAsStateWithLifecycle()
    var printing by remember { mutableStateOf(false) }
    var printError by remember { mutableStateOf<String?>(null) }

    GlassCard {
        KeyValue("Chek", "#${sale.number}")
        KeyValue("Sana", sale.date.orEmpty())
        sale.customer?.let { KeyValue("Mijoz", it) }
        KeyValue("Turi", sale.typeLabel.orEmpty())
        Spacer(Modifier.height(8.dp))

        sale.items.orEmpty().forEach { item ->
            Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                ProductImage(item.imageUrl, Modifier.padding(end = 10.dp), size = 40.dp, isPhone = item.imei != null, corner = 10.dp)
                Column(Modifier.weight(1f)) {
                    Text(if (item.qty > 1) "${item.name} × ${item.qty}" else item.name, fontWeight = FontWeight.Bold)
                    item.imei?.let { Text("IMEI $it", color = Palette.InkSoft, fontSize = 12.sp) }
                    item.warrantyUntil?.let { Text("Kafolat: $it gacha", color = Palette.InkSoft, fontSize = 12.sp) }
                }
                Text(item.finalLabel.orEmpty(), fontWeight = FontWeight.Bold)
            }
        }

        Spacer(Modifier.height(8.dp))
        if (sale.hasDiscount) KeyValue("Chegirma", "−${sale.discountLabel.orEmpty()}")
        KeyValue("Jami", sale.totalLabel, strong = true)
        sale.payments.orEmpty().forEach { KeyValue(it.label.orEmpty(), it.amountLabel.orEmpty()) }
        if (sale.hasChange) KeyValue("Qaytim", sale.changeLabel.orEmpty())
    }

    ErrorBanner(printError)
    OutlinedButton(
        onClick = {
            printing = true
            printError = null
            scope.launch {
                printError = ReceiptPrinter.print(
                    context,
                    app.session.printerAddress,
                    ReceiptPrinter.saleReceipt(sale, profile?.store?.name ?: "PhoneTrade", app.session.printerColumns),
                )
                printing = false
            }
        },
        enabled = !printing,
        modifier = Modifier.fillMaxWidth().height(50.dp),
    ) {
        Icon(Icons.Default.Print, contentDescription = null)
        Text(if (printing) "  Chop etilmoqda…" else "  Chekni chop etish", fontWeight = FontWeight.Bold)
    }
}
