package uz.simulator.phonetrade.print

import android.Manifest
import android.annotation.SuppressLint
import android.bluetooth.BluetoothManager
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.content.ContextCompat
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.withContext
import uz.simulator.phonetrade.data.SaleDto
import java.io.ByteArrayOutputStream
import java.util.UUID

data class PrinterDevice(val name: String, val address: String)

/** ESC/POS buyruqlari: Bluetooth chek printerlarining deyarli hammasi tushunadi. */
class EscPos(private val columns: Int) {
    private val out = ByteArrayOutputStream()

    init {
        out.write(byteArrayOf(0x1B, 0x40)) // qayta sozlash
    }

    fun align(center: Boolean) = apply { out.write(byteArrayOf(0x1B, 0x61, if (center) 1 else 0)) }

    fun bold(on: Boolean) = apply { out.write(byteArrayOf(0x1B, 0x45, if (on) 1 else 0)) }

    fun big(on: Boolean) = apply { out.write(byteArrayOf(0x1D, 0x21, if (on) 0x11 else 0x00)) }

    /** Qog'ozga sig'maydigan matn so'z chegarasidan keyingi qatorga o'tkaziladi. */
    fun line(text: String = "") = apply {
        wrap(ascii(text)).forEach {
            out.write(it.toByteArray(Charsets.US_ASCII))
            out.write(0x0A)
        }
    }

    private fun wrap(text: String): List<String> {
        if (text.length <= columns) return listOf(text)

        val lines = mutableListOf<String>()
        var current = ""

        for (word in text.split(' ')) {
            val candidate = if (current.isEmpty()) word else "$current $word"

            if (candidate.length <= columns) {
                current = candidate
            } else {
                if (current.isNotEmpty()) lines += current
                // Bitta so'zning o'zi sig'masa (uzun IMEI yoki nom) bo'laklab yoziladi.
                val pieces = word.chunked(columns)
                lines += pieces.dropLast(1)
                current = pieces.last()
            }
        }

        if (current.isNotEmpty()) lines += current

        return lines
    }

    fun divider() = line("-".repeat(columns))

    /** Chapda nom, o'ngda qiymat; sig'masa nom ikki qatorga bo'linadi. */
    fun pair(left: String, right: String) = apply {
        val a = ascii(left)
        val b = ascii(right)

        if (a.length + b.length + 1 <= columns) {
            line(a + " ".repeat(columns - a.length - b.length) + b)
        } else {
            line(a)
            line(" ".repeat((columns - b.length).coerceAtLeast(0)) + b)
        }
    }

    fun feed(lines: Int) = apply { repeat(lines) { out.write(0x0A) } }

    fun cut() = apply { out.write(byteArrayOf(0x1D, 0x56, 0x42, 0x00)) }

    fun bytes(): ByteArray = out.toByteArray()

    /** Printer kod sahifasiga bog'lanmaslik uchun matn ASCII'ga keltiriladi. */
    private fun ascii(text: String): String = buildString {
        for (char in text) {
            append(
                when (char) {
                    'ʻ', 'ʼ', '‘', '’', '`' -> '\''
                    '—', '–' -> '-'
                    '·', '•' -> '-'
                    '×' -> 'x'
                    ' ', ' ' -> ' '
                    else -> if (char.code in 32..126) char else '?'
                },
            )
        }
    }
}

object ReceiptPrinter {
    /** Standart Serial Port Profile. */
    private val SPP: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")

    fun hasPermission(context: Context): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.S ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.BLUETOOTH_CONNECT) == PackageManager.PERMISSION_GRANTED

    /** Telefon sozlamalarida juftlangan qurilmalar. */
    @SuppressLint("MissingPermission")
    fun paired(context: Context): List<PrinterDevice> {
        if (!hasPermission(context)) return emptyList()

        val adapter = context.getSystemService(BluetoothManager::class.java)?.adapter ?: return emptyList()

        return runCatching {
            adapter.bondedDevices.orEmpty().map { PrinterDevice(it.name ?: it.address, it.address) }
        }.getOrDefault(emptyList())
    }

    /** @return xato matni yoki muvaffaqiyatda null */
    @SuppressLint("MissingPermission")
    suspend fun print(context: Context, address: String?, data: ByteArray): String? = withContext(Dispatchers.IO) {
        if (address == null) return@withContext "Printer tanlanmagan. Profil oynasidan printerni tanlang."
        if (!hasPermission(context)) return@withContext "Bluetooth'ga ruxsat berilmagan."

        val adapter = context.getSystemService(BluetoothManager::class.java)?.adapter
        if (adapter == null || !adapter.isEnabled) return@withContext "Bluetooth o'chiq."

        try {
            adapter.getRemoteDevice(address).createRfcommSocketToServiceRecord(SPP).use { socket ->
                adapter.cancelDiscovery()
                socket.connect()
                socket.outputStream.write(data)
                socket.outputStream.flush()
                // Ba'zi printerlar ulanish darhol yopilsa oxirgi qatorlarni tashlab yuboradi.
                delay(600)
            }
            null
        } catch (_: Exception) {
            "Printerga ulanib bo'lmadi. Printer yoqilganini tekshiring."
        }
    }

    fun saleReceipt(sale: SaleDto, storeName: String, columns: Int): ByteArray {
        val receipt = EscPos(columns)
            .align(center = true).bold(true).big(true).line(storeName).big(false).bold(false)
            .line(sale.branch.orEmpty())
            .align(center = false).divider()
            .pair("Chek", "#${sale.number}")
            .pair("Sana", sale.date.orEmpty())
            .pair("Sotuvchi", sale.seller.orEmpty())

        sale.customer?.let { receipt.pair("Mijoz", it) }
        receipt.divider()

        sale.items.orEmpty().forEach { item ->
            receipt.bold(true).line(if (item.qty > 1) "${item.name} x ${item.qty}" else item.name).bold(false)
            item.imei?.let { receipt.line("IMEI: $it") }
            if (item.hasDiscount) receipt.pair("  Chegirma", "-${item.discountLabel.orEmpty()}")
            receipt.pair("  Narxi", item.finalLabel ?: item.priceLabel.orEmpty())
            item.warrantyUntil?.let { receipt.pair("  Kafolat", "$it gacha") }
        }

        receipt.divider()
        if (sale.hasDiscount) receipt.pair("Chegirma", "-${sale.discountLabel.orEmpty()}")
        receipt.bold(true).pair("JAMI", sale.totalLabel).bold(false)
        sale.payments.orEmpty().forEach { receipt.pair(it.label.orEmpty(), it.amountLabel.orEmpty()) }
        if (sale.hasChange) receipt.pair("Qaytim", sale.changeLabel.orEmpty())
        if (sale.type == "installment") receipt.line("Muddatli to'lov shartnomasi asosida")

        return receipt.divider().align(center = true).line("Xaridingiz uchun rahmat!").feed(3).cut().bytes()
    }
}
