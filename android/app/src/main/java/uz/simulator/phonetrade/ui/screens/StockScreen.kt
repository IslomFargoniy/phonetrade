package uz.simulator.phonetrade.ui.screens

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.FilledTonalIconButton
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButtonDefaults
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import uz.simulator.phonetrade.PhoneTradeApp
import uz.simulator.phonetrade.data.ApiException
import uz.simulator.phonetrade.data.StockUnitDto
import uz.simulator.phonetrade.ui.EmptyState
import uz.simulator.phonetrade.ui.ErrorBanner
import uz.simulator.phonetrade.ui.GlassCard
import uz.simulator.phonetrade.ui.KeyValue
import uz.simulator.phonetrade.ui.LoadingBox
import uz.simulator.phonetrade.ui.ProductImage
import uz.simulator.phonetrade.ui.attributesLine
import uz.simulator.phonetrade.ui.sale.Photos
import uz.simulator.phonetrade.ui.theme.Palette
import java.io.File

/** Bitta telefonga yuklash mumkin bo'lgan rasmlar soni (server chegarasi bilan bir xil). */
private const val MAX_PHOTOS = 8

/** Filial omboridagi telefonlar. Tannarx faqat egasiga keladi (server shunday beradi). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun StockScreen(app: PhoneTradeApp) {
    var query by rememberSaveable { mutableStateOf("") }
    var units by remember { mutableStateOf<List<StockUnitDto>?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var opened by remember { mutableStateOf<StockUnitDto?>(null) }

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
                    GlassCard(onClick = { opened = unit }) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            ProductImage(unit.imageUrl, size = 60.dp)
                            Column(Modifier.weight(1f).padding(start = 12.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Text(unit.product, Modifier.weight(1f), fontWeight = FontWeight.Bold, fontSize = 16.sp)
                                    Text(unit.salePriceLabel, fontWeight = FontWeight.ExtraBold, color = Palette.Primary)
                                }
                                attributesLine(unit.attributes).takeIf { it.isNotBlank() }?.let {
                                    Text(it, color = Palette.InkMid, fontSize = 13.sp)
                                }
                                Text(
                                    listOfNotNull(
                                        "IMEI ${unit.imei1.orEmpty()}",
                                        unit.photos?.size?.takeIf { it > 0 }?.let { "$it ta rasm" },
                                    ).joinToString(" · "),
                                    color = Palette.InkSoft,
                                    fontSize = 12.sp,
                                )
                            }
                        }
                    }
                }
            }
        }
    }

    opened?.let { unit ->
        ModalBottomSheet(
            onDismissRequest = { opened = null },
            sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true),
            containerColor = Palette.Background,
        ) {
            UnitSheet(app, unit) { updated ->
                opened = updated
                units = units?.map { if (it.id == updated.id) updated else it }
            }
        }
    }
}

/** Telefon kartasi: katta rasm, shu telefonning rasmlari va ma'lumotlari. */
@Composable
private fun UnitSheet(app: PhoneTradeApp, unit: StockUnitDto, onUpdated: (StockUnitDto) -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var busy by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var pending by remember { mutableStateOf<File?>(null) }
    var selected by remember(unit.id) { mutableStateOf<String?>(null) }
    val photos = unit.photos.orEmpty()

    fun upload(sources: List<Uri>, temporary: File? = null) {
        if (sources.isEmpty()) return
        busy = true
        error = null

        scope.launch {
            val files = withContext(Dispatchers.IO) { sources.take(MAX_PHOTOS - photos.size).mapNotNull { Photos.import(context, it) } }
            temporary?.delete()

            try {
                if (files.isEmpty()) throw ApiException("IMAGE_INVALID", "Rasmni o'qib bo'lmadi.")

                val body = MultipartBody.Builder().setType(MultipartBody.FORM).apply {
                    files.forEach { addFormDataPart("photos[]", it.name, it.asRequestBody("image/jpeg".toMediaType())) }
                }.build()

                onUpdated(app.client.call { addUnitPhotos(unit.id, body) })
                selected = null
            } catch (exception: ApiException) {
                error = exception.message
            } finally {
                files.forEach { it.delete() }
                busy = false
            }
        }
    }

    fun delete(photoId: Int) {
        busy = true
        error = null

        scope.launch {
            try {
                onUpdated(app.client.call { deleteUnitPhoto(photoId) })
                selected = null
            } catch (exception: ApiException) {
                error = exception.message
            } finally {
                busy = false
            }
        }
    }

    val camera = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { saved ->
        val file = pending
        pending = null

        if (saved && file != null) upload(listOf(Photos.uriFor(context, file)), file) else file?.delete()
    }
    val gallery = rememberLauncherForActivityResult(ActivityResultContracts.GetMultipleContents()) { upload(it) }

    Column(
        Modifier
            .fillMaxWidth()
            .verticalScroll(rememberScrollState())
            .padding(horizontal = 16.dp)
            .navigationBarsPadding(),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(unit.product, fontWeight = FontWeight.ExtraBold, fontSize = 22.sp)
        attributesLine(unit.attributes).takeIf { it.isNotBlank() }?.let { Text(it, color = Palette.InkMid) }

        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            ProductImage(selected ?: unit.imageUrl, size = 260.dp, corner = 26.dp)
        }

        Text("Rasmlar · ${photos.size}/$MAX_PHOTOS", fontWeight = FontWeight.Bold, color = Palette.InkMid, fontSize = 13.sp)

        if (photos.isEmpty()) {
            Text(
                "Bu telefonning o'z rasmi yo'q — model rasmi ko'rsatilmoqda. Telefonni suratga olib qo'shing.",
                color = Palette.InkSoft,
                fontSize = 13.sp,
            )
        } else {
            LazyRow(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                items(photos, key = { it.id }) { photo ->
                    Box {
                        Box(Modifier.padding(top = 6.dp, end = 6.dp)) {
                            GlassCard(
                                modifier = Modifier.size(76.dp),
                                onClick = { selected = photo.url },
                                padding = PaddingValues(0.dp),
                            ) {
                                ProductImage(photo.url, size = 76.dp, corner = 0.dp)
                            }
                        }
                        FilledTonalIconButton(
                            onClick = { delete(photo.id) },
                            enabled = !busy,
                            modifier = Modifier.size(26.dp).align(Alignment.TopEnd),
                            shape = CircleShape,
                            colors = IconButtonDefaults.filledTonalIconButtonColors(containerColor = Color.White),
                        ) {
                            Icon(Icons.Default.Close, contentDescription = "Rasmni o'chirish", Modifier.size(16.dp))
                        }
                    }
                }
            }
        }

        ErrorBanner(error)

        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            OutlinedButton(
                onClick = {
                    val file = Photos.newFile(context)
                    pending = file
                    camera.launch(Photos.uriFor(context, file))
                },
                enabled = !busy && photos.size < MAX_PHOTOS,
                modifier = Modifier.weight(1f),
            ) {
                Icon(Icons.Default.PhotoCamera, contentDescription = null)
                Text("  Kamera")
            }
            OutlinedButton(
                onClick = { gallery.launch("image/*") },
                enabled = !busy && photos.size < MAX_PHOTOS,
                modifier = Modifier.weight(1f),
            ) {
                Icon(Icons.Default.Image, contentDescription = null)
                Text("  Galereya")
            }
            if (busy) CircularProgressIndicator(Modifier.size(24.dp), strokeWidth = 2.dp)
        }

        GlassCard {
            KeyValue("IMEI 1", unit.imei1.orEmpty())
            unit.imei2?.let { KeyValue("IMEI 2", it) }
            unit.barcode?.let { KeyValue("Barcode", it) }
            KeyValue("Narx", unit.salePriceLabel, strong = true)
            unit.costLabel?.let { KeyValue("Tannarx", it) }
            unit.warrantyMonths?.let { KeyValue("Kafolat", "$it oy") }
            unit.statusLabel?.let { KeyValue("Holat", it) }
            unit.receivedAt?.let { KeyValue("Kirim sanasi", it) }
        }

        Spacer(Modifier.height(16.dp))
    }
}
