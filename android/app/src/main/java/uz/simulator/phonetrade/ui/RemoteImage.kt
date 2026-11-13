package uz.simulator.phonetrade.ui

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.util.LruCache
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.Inventory2
import androidx.compose.material.icons.outlined.Smartphone
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import uz.simulator.phonetrade.ui.theme.Palette
import java.util.concurrent.TimeUnit

/**
 * Tovar rasmlarini yuklash: ochiq `storage` havolalari, token kerak emas.
 * Xotirada LRU kesh (taxminan 24 MB), rasmlar ko'rinish o'lchamiga qadar kichraytiriladi.
 */
object ImageLoader {
    private val http = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .build()

    private val cache = object : LruCache<String, Bitmap>(24 * 1024 * 1024) {
        override fun sizeOf(key: String, value: Bitmap): Int = value.byteCount
    }

    fun cached(url: String, maxSide: Int): Bitmap? = cache.get("$maxSide|$url")

    suspend fun load(url: String, maxSide: Int): Bitmap? {
        cached(url, maxSide)?.let { return it }

        return withContext(Dispatchers.IO) {
            runCatching {
                val bytes = http.newCall(Request.Builder().url(url).build()).execute().use { response ->
                    if (!response.isSuccessful) return@runCatching null
                    response.body?.bytes()
                } ?: return@runCatching null

                val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
                BitmapFactory.decodeByteArray(bytes, 0, bytes.size, bounds)

                var sample = 1
                while (maxOf(bounds.outWidth, bounds.outHeight) / (sample * 2) >= maxSide) sample *= 2

                BitmapFactory.decodeByteArray(bytes, 0, bytes.size, BitmapFactory.Options().apply { inSampleSize = sample })
                    ?.also { cache.put("$maxSide|$url", it) }
            }.getOrNull()
        }
    }
}

/**
 * Tovar rasmi. Rasm bo'lmasa yoki yuklanmasa — telefon/aksessuar ikonkasi.
 */
@Composable
fun ProductImage(
    url: String?,
    modifier: Modifier = Modifier,
    size: Dp = 52.dp,
    isPhone: Boolean = true,
    corner: Dp = 14.dp,
) {
    val maxSide = (size.value * 3).toInt().coerceIn(96, 1080)
    var bitmap by remember(url) { mutableStateOf(url?.let { ImageLoader.cached(it, maxSide) }) }

    LaunchedEffect(url) {
        if (url != null && bitmap == null) bitmap = ImageLoader.load(url, maxSide)
    }

    Box(
        modifier
            .size(size)
            .clip(RoundedCornerShape(corner))
            .background(Palette.Glass),
        contentAlignment = Alignment.Center,
    ) {
        val image = bitmap
        if (image != null) {
            Image(image.asImageBitmap(), contentDescription = null, Modifier.fillMaxSize(), contentScale = ContentScale.Crop)
        } else {
            Icon(
                if (isPhone) Icons.Outlined.Smartphone else Icons.Outlined.Inventory2,
                contentDescription = null,
                tint = Palette.InkSoft,
                modifier = Modifier.size(size * 0.45f),
            )
        }
    }
}
