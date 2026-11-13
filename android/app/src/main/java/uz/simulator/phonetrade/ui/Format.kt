package uz.simulator.phonetrade.ui

/** "4700000.00" -> "4700000"; "12.50" -> "12.5". Kiritish maydonlari uchun. */
fun plainAmount(value: String?): String {
    val text = value?.trim().orEmpty()
    if (!text.contains('.')) return text

    return text.trimEnd('0').trimEnd('.')
}

/** Kiritilgan summadan faqat raqam va bitta nuqta qoldiradi (vergul nuqtaga aylanadi). */
fun cleanAmount(input: String): String {
    val builder = StringBuilder()
    var dot = false

    for (char in input.replace(',', '.')) {
        when {
            char.isDigit() -> builder.append(char)
            char == '.' && !dot && builder.isNotEmpty() -> {
                dot = true
                builder.append(char)
            }
        }
    }

    return builder.toString()
}

/** Raqamlarni mingliklarga ajratib ko'rsatadi: "4700000" -> "4 700 000". */
fun grouped(amount: String): String {
    val whole = amount.substringBefore('.')
    val fraction = amount.substringAfter('.', "")
    val spaced = whole.reversed().chunked(3).joinToString(" ").reversed()

    return if (amount.contains('.')) "$spaced.$fraction" else spaced
}

fun currencyLabel(code: String): String = if (code == "USD") "$" else "so'm"

fun methodLabel(code: String): String = when (code) {
    "card" -> "Karta"
    "transfer" -> "O'tkazma"
    else -> "Naqd"
}

fun attributesLine(attributes: Map<String, String>?): String =
    attributes.orEmpty().values.filter { it.isNotBlank() }.joinToString(" · ")

/** "998901234567" -> "+998 90 123 45 67". */
fun formatPhone(raw: String?): String {
    val digits = raw.orEmpty().filter { it.isDigit() }
    if (digits.length != 12) return raw.orEmpty()

    return "+${digits.substring(0, 3)} ${digits.substring(3, 5)} ${digits.substring(5, 8)} " +
        "${digits.substring(8, 10)} ${digits.substring(10, 12)}"
}
