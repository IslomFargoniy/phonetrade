package uz.simulator.phonetrade.ui.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.delay
import uz.simulator.phonetrade.PhoneTradeApp
import uz.simulator.phonetrade.data.ApiException
import uz.simulator.phonetrade.data.CustomerRow
import uz.simulator.phonetrade.data.StockUnitDto
import uz.simulator.phonetrade.ui.Badge
import uz.simulator.phonetrade.ui.EmptyState
import uz.simulator.phonetrade.ui.ErrorBanner
import uz.simulator.phonetrade.ui.GlassCard
import uz.simulator.phonetrade.ui.LoadingBox
import uz.simulator.phonetrade.ui.attributesLine
import uz.simulator.phonetrade.ui.formatPhone
import uz.simulator.phonetrade.ui.theme.Palette

@Composable
private fun SearchField(value: String, onChange: (String) -> Unit, placeholder: String) {
    OutlinedTextField(
        value = value,
        onValueChange = onChange,
        placeholder = { Text(placeholder) },
        leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
        singleLine = true,
        modifier = Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
        shape = RoundedCornerShape(18.dp),
        colors = OutlinedTextFieldDefaults.colors(
            unfocusedContainerColor = Palette.Glass,
            focusedContainerColor = Color.White,
            unfocusedBorderColor = Palette.Stroke,
        ),
    )
}

/** Filial omboridagi telefonlar. Tannarx faqat egasiga keladi (server shunday beradi). */
@Composable
fun StockScreen(app: PhoneTradeApp) {
    var query by rememberSaveable { mutableStateOf("") }
    var units by remember { mutableStateOf<List<StockUnitDto>?>(null) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(query) {
        if (query.isNotEmpty()) delay(350)
        try {
            units = app.client.call { stock(query.trim().ifEmpty { null }) }.units.orEmpty()
            error = null
        } catch (exception: ApiException) {
            error = exception.message
            units = units ?: emptyList()
        }
    }

    Column(Modifier.fillMaxSize()) {
        SearchField(query, { query = it }, "Model, IMEI yoki barcode")
        ErrorBanner(error, Modifier.padding(horizontal = 16.dp))

        val rows = units
        when {
            rows == null -> LoadingBox()
            rows.isEmpty() && error == null -> EmptyState(if (query.isBlank()) "Omborda telefon yo'q." else "Hech narsa topilmadi.")
            else -> LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                item { Text("${rows.size} ta telefon", color = Palette.InkSoft, fontSize = 13.sp) }
                items(rows, key = { it.id }) { unit ->
                    GlassCard {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(unit.product, Modifier.weight(1f), fontWeight = FontWeight.Bold, fontSize = 16.sp)
                            Text(unit.salePriceLabel, fontWeight = FontWeight.ExtraBold, color = Palette.Primary)
                        }
                        attributesLine(unit.attributes).takeIf { it.isNotBlank() }?.let {
                            Text(it, color = Palette.InkMid, fontSize = 13.sp)
                        }
                        Text(
                            "IMEI ${unit.imei1.orEmpty()} · ${unit.barcode.orEmpty()}",
                            color = Palette.InkSoft,
                            fontSize = 12.sp,
                        )
                        unit.costLabel?.let {
                            Text("Tannarx: $it", color = Palette.InkMid, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun CustomersScreen(app: PhoneTradeApp) {
    var query by rememberSaveable { mutableStateOf("") }
    var customers by remember { mutableStateOf<List<CustomerRow>?>(null) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(query) {
        if (query.isNotEmpty()) delay(350)
        try {
            customers = app.client.call { customers(query.trim().ifEmpty { null }) }.customers.orEmpty()
            error = null
        } catch (exception: ApiException) {
            error = exception.message
            customers = customers ?: emptyList()
        }
    }

    Column(Modifier.fillMaxSize()) {
        SearchField(query, { query = it }, "Ism yoki telefon")
        ErrorBanner(error, Modifier.padding(horizontal = 16.dp))

        val rows = customers
        when {
            rows == null -> LoadingBox()
            rows.isEmpty() && error == null -> EmptyState(if (query.isBlank()) "Mijozlar hali yo'q." else "Hech kim topilmadi.")
            else -> LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                items(rows, key = { it.id }) { customer ->
                    GlassCard {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Column(Modifier.weight(1f)) {
                                Text(customer.fullName.orEmpty().ifBlank { "Ismsiz mijoz" }, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                Text(formatPhone(customer.phone), color = Palette.InkMid, fontSize = 14.sp)
                            }
                            if (customer.activePlans > 0) {
                                Badge("${customer.activePlans} ta nasiya", Palette.Warning, Palette.WarningBg)
                            }
                        }
                    }
                }
            }
        }
    }
}
