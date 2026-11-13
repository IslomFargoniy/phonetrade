package uz.simulator.phonetrade.ui.sale

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilledIconButton
import androidx.compose.material3.FilledTonalIconButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import uz.simulator.phonetrade.PhoneTradeApp
import uz.simulator.phonetrade.data.CartLineDto
import uz.simulator.phonetrade.ui.AppField
import uz.simulator.phonetrade.ui.EmptyState
import uz.simulator.phonetrade.ui.ErrorBanner
import uz.simulator.phonetrade.ui.GlassCard
import uz.simulator.phonetrade.ui.PrimaryButton
import uz.simulator.phonetrade.ui.SectionTitle
import uz.simulator.phonetrade.ui.attributesLine
import uz.simulator.phonetrade.ui.cleanAmount
import uz.simulator.phonetrade.ui.currencyLabel
import uz.simulator.phonetrade.ui.theme.Palette

@Composable
fun SaleScreen(app: PhoneTradeApp, branchId: Int) {
    // Savat filialga tegishli: filial almashsa yangi, bo'sh savat ochiladi.
    val model: SaleViewModel = viewModel(key = "sale-$branchId") { SaleViewModel(app.client) }
    val state = model.state

    when (state.step) {
        SaleStep.Cart -> CartStep(model)
        SaleStep.Checkout -> CheckoutStep(app, model)
        SaleStep.Done -> state.sale?.let { DoneStep(app, it, model::startNew) }
    }
}

@Composable
private fun CartStep(model: SaleViewModel) {
    val state = model.state
    var query by rememberSaveable { mutableStateOf("") }
    var scanning by remember { mutableStateOf(false) }
    var discountFor by remember { mutableStateOf<String?>(null) }

    if (scanning) {
        ScannerDialog(
            message = state.notice ?: state.entries.lastOrNull()?.let { "Savatda: ${state.entries.size} ta · oxirgisi ${it.name}" },
            onCode = { model.search(it, fromScanner = true) },
            onClose = { scanning = false },
        )
    }

    discountFor?.let { key ->
        val index = state.entries.indexOfFirst { it.key == key }
        val entry = state.entries.getOrNull(index)

        if (entry == null) {
            discountFor = null
        } else {
            DiscountDialog(
                entry = entry,
                line = state.totals?.lines?.getOrNull(index),
                onSave = {
                    model.setDiscount(key, it)
                    discountFor = null
                },
                onClose = { discountFor = null },
            )
        }
    }

    Column(Modifier.fillMaxSize()) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            OutlinedTextField(
                value = query,
                onValueChange = {
                    query = it
                    if (it.isBlank()) model.clearNotice()
                },
                placeholder = { Text("IMEI, barcode yoki model") },
                leadingIcon = {
                    if (state.searching) {
                        CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp)
                    } else {
                        Icon(Icons.Default.Search, contentDescription = null)
                    }
                },
                singleLine = true,
                modifier = Modifier.weight(1f),
                shape = RoundedCornerShape(18.dp),
                keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
                keyboardActions = KeyboardActions(onSearch = { model.search(query) }),
                colors = OutlinedTextFieldDefaults.colors(
                    unfocusedContainerColor = Palette.Glass,
                    focusedContainerColor = Color.White,
                    unfocusedBorderColor = Palette.Stroke,
                ),
            )
            FilledIconButton(onClick = { scanning = true }, modifier = Modifier.size(56.dp), shape = RoundedCornerShape(18.dp)) {
                Icon(Icons.Default.QrCodeScanner, contentDescription = "Skaner")
            }
        }

        LazyColumn(
            modifier = Modifier.weight(1f),
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            if (!scanning && state.notice != null) {
                item { ErrorBanner(state.notice) }
            }

            state.results?.let { results ->
                item { SectionTitle("Topildi — savatga qo'shish uchun bosing") }
                items(results.units.orEmpty(), key = { "ru${it.id}" }) { unit ->
                    GlassCard(onClick = {
                        model.add(CartEntry(unit = unit))
                        query = ""
                    }) {
                        Text(unit.product, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                        Text(
                            listOf(attributesLine(unit.attributes), "IMEI ${unit.imei1.orEmpty()}").filter { it.isNotBlank() }.joinToString(" · "),
                            color = Palette.InkMid,
                            fontSize = 13.sp,
                        )
                        Text(unit.salePriceLabel, fontWeight = FontWeight.ExtraBold, color = Palette.Primary)
                    }
                }
                items(results.variants.orEmpty(), key = { "rv${it.id}" }) { variant ->
                    GlassCard(onClick = {
                        model.add(CartEntry(variant = variant))
                        query = ""
                    }) {
                        Text(variant.product, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                        Text(
                            listOf(attributesLine(variant.attributes), "Qoldiq: ${variant.qty} ta").filter { it.isNotBlank() }.joinToString(" · "),
                            color = Palette.InkMid,
                            fontSize = 13.sp,
                        )
                        Text(variant.salePriceLabel, fontWeight = FontWeight.ExtraBold, color = Palette.Primary)
                    }
                }
                item { Spacer(Modifier.height(8.dp)) }
            }

            if (state.entries.isEmpty() && state.results == null) {
                item { EmptyState("Savat bo'sh.\nTelefonni skanerlang yoki nomini yozib qidiring.") }
            }

            if (state.entries.isNotEmpty()) {
                item { SectionTitle("Savat · ${state.entries.size} ta") }
            }

            items(state.entries.size, key = { state.entries[it].key }) { index ->
                val entry = state.entries[index]
                val line = state.totals?.lines?.getOrNull(index)

                CartRow(
                    entry = entry,
                    line = line,
                    onRemove = { model.remove(entry.key) },
                    onQty = { model.setQty(entry.key, it) },
                    onDiscount = { discountFor = entry.key },
                )
            }
        }

        if (state.entries.isNotEmpty()) {
            GlassCard(Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) {
                ErrorBanner(state.previewError, Modifier.padding(bottom = 8.dp))

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text("Jami", color = Palette.InkMid, fontSize = 13.sp)
                        Text(
                            state.totals?.totalLabel ?: "…",
                            fontSize = 24.sp,
                            fontWeight = FontWeight.ExtraBold,
                            color = Palette.Ink,
                        )
                        if (state.totals?.mixed == true) {
                            Text("Aralash savat: so'mda, kurs ${state.totals.rate.orEmpty()}", color = Palette.InkSoft, fontSize = 12.sp)
                        }
                    }
                    PrimaryButton(
                        text = "To'lov",
                        onClick = model::openCheckout,
                        modifier = Modifier.width(140.dp),
                        enabled = state.totals != null && state.previewError == null,
                        loading = state.calculating,
                    )
                }
            }
        }
    }
}

@Composable
private fun CartRow(
    entry: CartEntry,
    line: CartLineDto?,
    onRemove: () -> Unit,
    onQty: (Int) -> Unit,
    onDiscount: () -> Unit,
) {
    GlassCard(padding = PaddingValues(start = 16.dp, top = 12.dp, end = 6.dp, bottom = 12.dp)) {
        Row(verticalAlignment = Alignment.Top) {
            Column(Modifier.weight(1f)) {
                Text(entry.name, fontWeight = FontWeight.Bold, fontSize = 16.sp, color = Palette.Ink)

                val details = listOfNotNull(
                    attributesLine(entry.unit?.attributes ?: entry.variant?.attributes).takeIf { it.isNotBlank() },
                    entry.unit?.imei1?.let { "IMEI $it" },
                ).joinToString(" · ")

                if (details.isNotBlank()) Text(details, color = Palette.InkMid, fontSize = 13.sp)

                Text(
                    line?.netLabel ?: entry.unit?.salePriceLabel ?: entry.variant?.salePriceLabel.orEmpty(),
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = 17.sp,
                    color = Palette.Ink,
                    modifier = Modifier.padding(top = 4.dp),
                )
            }
            IconButton(onClick = onRemove) {
                Icon(Icons.Default.Delete, contentDescription = "O'chirish", tint = Palette.InkSoft)
            }
        }

        Row(verticalAlignment = Alignment.CenterVertically) {
            TextButton(onClick = onDiscount, contentPadding = PaddingValues(horizontal = 4.dp)) {
                Text(
                    if (entry.discount.isBlank() || entry.discount == "0") "Chegirma" else "Chegirma: ${entry.discount} ${currencyLabel(entry.currency)}",
                    fontWeight = FontWeight.Bold,
                )
            }
            Spacer(Modifier.weight(1f))

            entry.variant?.let { variant ->
                FilledTonalIconButton(onClick = { onQty(entry.qty - 1) }, enabled = entry.qty > 1) {
                    Icon(Icons.Default.Remove, contentDescription = "Kamaytirish")
                }
                Text(
                    "${entry.qty}",
                    Modifier.padding(horizontal = 14.dp),
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = 18.sp,
                )
                FilledTonalIconButton(onClick = { onQty(entry.qty + 1) }, enabled = entry.qty < variant.qty) {
                    Icon(Icons.Default.Add, contentDescription = "Ko'paytirish")
                }
                Spacer(Modifier.width(6.dp))
            }
        }
    }
}

@Composable
private fun DiscountDialog(entry: CartEntry, line: CartLineDto?, onSave: (String) -> Unit, onClose: () -> Unit) {
    var value by remember { mutableStateOf(entry.discount) }

    AlertDialog(
        onDismissRequest = onClose,
        title = { Text("Chegirma") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(entry.name, fontWeight = FontWeight.SemiBold)
                AppField(
                    value = value,
                    onChange = { value = cleanAmount(it) },
                    label = "Summa",
                    keyboard = KeyboardType.Decimal,
                    suffix = currencyLabel(entry.currency),
                )
                line?.maxDiscountLabel?.let {
                    Text("Eng ko'pi: $it", color = Palette.InkMid, fontSize = 13.sp)
                }
            }
        },
        confirmButton = { TextButton(onClick = { onSave(value) }) { Text("Saqlash") } },
        dismissButton = { TextButton(onClick = { onSave("") }) { Text("Chegirmasiz") } },
    )
}
