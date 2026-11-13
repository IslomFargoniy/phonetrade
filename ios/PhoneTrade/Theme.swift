import SwiftUI

/// Liquid Glass dizayni: web ilovadagi ranglar bilan bir xil.
enum Palette {
    static let background = Color(red: 0.914, green: 0.929, blue: 0.961)
    static let primary = Color(red: 0, green: 0.345, blue: 0.761)
    static let ink = Color(red: 0.11, green: 0.11, blue: 0.118)
    static let inkMid = Color(red: 0.357, green: 0.384, blue: 0.459)
    static let inkSoft = Color(red: 0.541, green: 0.565, blue: 0.635)
    static let success = Color(red: 0.07, green: 0.5, blue: 0.36)
    static let danger = Color(red: 0.776, green: 0.157, blue: 0.157)
    static let warning = Color(red: 0.698, green: 0.416, blue: 0)
}

/// Orqa fon: och kulrang ustida rangli dog'lar (shisha panellar ostidan ko'rinadi).
struct AppBackground: View {
    var body: some View {
        ZStack {
            Palette.background
            Circle().fill(Color(red: 0.56, green: 0.72, blue: 1).opacity(0.45)).frame(width: 340).blur(radius: 90).offset(x: -140, y: -300)
            Circle().fill(Color(red: 1, green: 0.76, blue: 0.86).opacity(0.4)).frame(width: 300).blur(radius: 90).offset(x: 150, y: 60)
            Circle().fill(Color(red: 0.66, green: 0.94, blue: 0.85).opacity(0.4)).frame(width: 300).blur(radius: 90).offset(x: -80, y: 380)
        }
        .ignoresSafeArea()
    }
}

struct GlassCard<Content: View>: View {
    @ViewBuilder var content: Content

    var body: some View {
        VStack(alignment: .leading, spacing: 8) { content }
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(.white.opacity(0.72), in: RoundedRectangle(cornerRadius: 22, style: .continuous))
            .overlay(RoundedRectangle(cornerRadius: 22, style: .continuous).stroke(.white.opacity(0.9), lineWidth: 1))
    }
}

struct SectionTitle: View {
    let text: String

    init(_ text: String) { self.text = text }

    var body: some View {
        Text(text.uppercased())
            .font(.caption.weight(.bold))
            .kerning(0.6)
            .foregroundStyle(Palette.inkSoft)
            .frame(maxWidth: .infinity, alignment: .leading)
    }
}

struct KeyValue: View {
    let label: String
    let value: String
    var strong = false

    var body: some View {
        HStack(alignment: .firstTextBaseline) {
            Text(label).font(.subheadline).foregroundStyle(Palette.inkMid)
            Spacer(minLength: 12)
            Text(value)
                .font(strong ? .title3.weight(.heavy) : .subheadline.weight(.semibold))
                .monospacedDigit()
                .foregroundStyle(Palette.ink)
                .multilineTextAlignment(.trailing)
        }
    }
}

struct Badge: View {
    let text: String
    let color: Color

    var body: some View {
        Text(text)
            .font(.caption.weight(.bold))
            .foregroundStyle(color)
            .padding(.horizontal, 10)
            .padding(.vertical, 4)
            .background(color.opacity(0.13), in: Capsule())
    }
}

struct ErrorBanner: View {
    let message: String?

    var body: some View {
        if let message, !message.isEmpty {
            Text(message)
                .font(.subheadline.weight(.semibold))
                .foregroundStyle(Palette.danger)
                .padding(12)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Palette.danger.opacity(0.12), in: RoundedRectangle(cornerRadius: 14, style: .continuous))
        }
    }
}

struct PrimaryButtonStyle: ButtonStyle {
    @Environment(\.isEnabled) private var enabled

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.headline)
            .foregroundStyle(.white)
            .frame(maxWidth: .infinity, minHeight: 54)
            .background(Palette.primary.opacity(enabled ? (configuration.isPressed ? 0.8 : 1) : 0.35), in: RoundedRectangle(cornerRadius: 18, style: .continuous))
    }
}

/// Ro'yxat ekranlarining umumiy holatlari: yuklanmoqda, xato, bo'sh.
struct LoadState<Value, Content: View>: View {
    let value: Value?
    let error: String?
    let isEmpty: (Value) -> Bool
    let emptyText: String
    @ViewBuilder var content: (Value) -> Content

    var body: some View {
        if let value {
            if isEmpty(value), error == nil {
                Text(emptyText)
                    .foregroundStyle(Palette.inkSoft)
                    .multilineTextAlignment(.center)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 40)
            } else {
                content(value)
            }
        } else if error == nil {
            ProgressView().frame(maxWidth: .infinity).padding(40)
        }
    }
}

enum Fmt {
    /// "4700000.00" -> "4 700 000 so'm"; USD: "460.50" -> "460.5 $"
    static func money(_ amount: String?, currency: String = "UZS") -> String {
        var plain = plain(amount)

        // So'mda tiyin ko'rsatilmaydi (web hisobotlaridagidek butun songa yaxlitlanadi).
        if currency != "USD", plain.contains("."), let value = Decimal(string: plain) {
            var rounded = Decimal()
            var source = value
            NSDecimalRound(&rounded, &source, 0, .plain)
            plain = NSDecimalNumber(decimal: rounded).stringValue
        }

        let parts = plain.split(separator: ".", maxSplits: 1).map(String.init)
        let negative = parts.first?.hasPrefix("-") ?? false
        let whole = String((parts.first ?? "0").drop { $0 == "-" })

        var grouped = ""
        for (index, char) in whole.reversed().enumerated() {
            if index > 0, index % 3 == 0 { grouped.append(" ") }
            grouped.append(char)
        }

        let number = (negative ? "−" : "") + String(grouped.reversed()) + (parts.count > 1 ? ".\(parts[1])" : "")

        return currency == "USD" ? "\(number) $" : "\(number) so'm"
    }

    /// "4700000.00" -> "4700000" (kiritish maydonlari uchun)
    static func plain(_ amount: String?) -> String {
        var text = (amount ?? "").trimmingCharacters(in: .whitespaces)
        guard text.contains(".") else { return text.isEmpty ? "0" : text }

        while text.hasSuffix("0") { text.removeLast() }
        if text.hasSuffix(".") { text.removeLast() }

        return text.isEmpty ? "0" : text
    }

    /// Kiritilgan summadan faqat raqam va bitta nuqta qoldiradi.
    static func cleanAmount(_ input: String) -> String {
        var result = ""
        var dot = false

        for char in input.replacingOccurrences(of: ",", with: ".") {
            if char.isNumber {
                result.append(char)
            } else if char == ".", !dot, !result.isEmpty {
                dot = true
                result.append(char)
            }
        }

        return result
    }

    static func phone(_ raw: String?) -> String {
        let digits = (raw ?? "").filter(\.isNumber)
        guard digits.count == 12 else { return raw ?? "" }

        let d = Array(digits)
        return "+\(String(d[0..<3])) \(String(d[3..<5])) \(String(d[5..<8])) \(String(d[8..<10])) \(String(d[10..<12]))"
    }

    static func method(_ code: String) -> String {
        switch code {
        case "card": "Karta"
        case "transfer": "O'tkazma"
        default: "Naqd"
        }
    }
}
