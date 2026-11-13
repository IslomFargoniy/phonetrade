import SwiftUI
import VisionKit

/// Savatdagi qator: telefon (dona) yoki aksesuar (miqdor bilan).
struct CartEntry: Identifiable, Equatable {
    var unit: StockUnit?
    var variant: StockVariant?
    var qty = 1
    var discount = ""

    var id: String { unit.map { "u\($0.id)" } ?? "v\(variant?.id ?? 0)" }
    var name: String { unit?.product ?? variant?.product ?? "" }
    var priceLabel: String { unit?.salePriceLabel ?? variant?.salePriceLabel ?? "" }
    var currency: String { unit?.saleCurrency ?? variant?.saleCurrency ?? "UZS" }
}

/// To'liq to'lovli sotuv. Hisob-kitob serverda: ilova o'zi pul hisoblamaydi.
/// Muddatli to'lov shartnomasi (pasport rasmi bilan) Android ilova va web kassada rasmiylashtiriladi.
struct SaleView: View {
    @Environment(Session.self) private var session
    @State private var query = ""
    @State private var entries: [CartEntry] = []
    @State private var results: LookupResponse?
    @State private var totals: CartTotals?
    @State private var notice: String?
    @State private var error: String?
    @State private var scanning = false
    @State private var method = "cash"
    @State private var currency = "UZS"
    @State private var amount = ""
    @State private var amountEdited = false
    @State private var submitting = false
    @State private var sale: Sale?
    /// Bitta savdo urinishi uchun kalit: qayta yuborilsa savdo ikki marta yozilmaydi.
    @State private var key = UUID().uuidString

    var body: some View {
        ScrollView {
            VStack(spacing: 12) {
                HStack {
                    TextField("IMEI, barcode yoki model", text: $query)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .submitLabel(.search)
                        .onSubmit { search(query) }
                        .padding(14)
                        .background(.white.opacity(0.8), in: RoundedRectangle(cornerRadius: 16))

                    Button { scanning = true } label: {
                        Image(systemName: "barcode.viewfinder")
                            .font(.title2)
                            .foregroundStyle(.white)
                            .frame(width: 52, height: 52)
                            .background(Palette.primary, in: RoundedRectangle(cornerRadius: 16))
                    }
                    .accessibilityLabel("Skaner")
                }

                ErrorBanner(message: notice)

                if let results {
                    SectionTitle("Topildi — qo'shish uchun bosing")
                    ForEach(results.units) { unit in
                        Button { add(CartEntry(unit: unit)) } label: {
                            found(unit.product, detail: [unit.details, "IMEI \(unit.imei1 ?? "")"], price: unit.salePriceLabel)
                        }
                        .buttonStyle(.plain)
                    }
                    ForEach(results.variants) { variant in
                        Button { add(CartEntry(variant: variant)) } label: {
                            found(variant.product, detail: ["Qoldiq: \(variant.qty) ta"], price: variant.salePriceLabel)
                        }
                        .buttonStyle(.plain)
                    }
                }

                if entries.isEmpty, results == nil {
                    Text("Savat bo'sh.\nTelefonni skanerlang yoki nomini yozib qidiring.")
                        .multilineTextAlignment(.center)
                        .foregroundStyle(Palette.inkSoft)
                        .padding(.vertical, 40)
                }

                if !entries.isEmpty {
                    SectionTitle("Savat · \(entries.count) ta")

                    ForEach(Array(entries.enumerated()), id: \.element.id) { index, entry in
                        cartRow(entry, line: totals?.lines?[safe: index])
                    }

                    checkout
                }
            }
            .padding(16)
        }
        .scrollDismissesKeyboard(.interactively)
        .task(id: entries) { await recalculate() }
        .sheet(isPresented: $scanning) {
            ScannerSheet(message: notice) { code in search(code, fromScanner: true) }
        }
        .sheet(item: $sale) { sale in
            ReceiptSheet(sale: sale)
        }
    }

    private func found(_ title: String, detail: [String], price: String) -> some View {
        GlassCard {
            Text(title).font(.headline)
            Text(detail.filter { !$0.isEmpty }.joined(separator: " · ")).font(.footnote).foregroundStyle(Palette.inkMid)
            Text(price).font(.subheadline.weight(.heavy)).foregroundStyle(Palette.primary)
        }
    }

    private func cartRow(_ entry: CartEntry, line: CartLine?) -> some View {
        GlassCard {
            HStack(alignment: .top) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(entry.name).font(.headline)
                    if let unit = entry.unit {
                        Text([unit.details, "IMEI \(unit.imei1 ?? "")"].filter { !$0.isEmpty }.joined(separator: " · "))
                            .font(.footnote)
                            .foregroundStyle(Palette.inkMid)
                    }
                    Text(line?.netLabel ?? entry.priceLabel).font(.body.weight(.heavy)).monospacedDigit()
                }
                Spacer()
                Button { entries.removeAll { $0.id == entry.id } } label: {
                    Image(systemName: "trash").foregroundStyle(Palette.inkSoft)
                }
                .accessibilityLabel("O'chirish")
            }

            HStack {
                TextField("Chegirma, \(entry.currency == "USD" ? "$" : "so'm")", text: binding(entry.id, \.discount))
                    .keyboardType(.decimalPad)
                    .padding(10)
                    .background(.white.opacity(0.85), in: RoundedRectangle(cornerRadius: 12))

                if let variant = entry.variant {
                    Stepper("\(entry.qty) ta", value: binding(entry.id, \.qty), in: 1...max(1, variant.qty))
                        .fixedSize()
                }
            }

            if let limit = line?.maxDiscountLabel {
                Text("Chegirma eng ko'pi: \(limit)").font(.caption).foregroundStyle(Palette.inkSoft)
            }
        }
    }

    private var checkout: some View {
        GlassCard {
            KeyValue(label: "Jami", value: totals?.totalLabel ?? "…", strong: true)
            if totals?.mixed == true {
                Text("Aralash savat: so'mda hisoblandi, kurs \(totals?.rate ?? "")").font(.caption).foregroundStyle(Palette.inkSoft)
            }

            Picker("Usul", selection: $method) {
                ForEach(["cash", "card", "transfer"], id: \.self) { Text(Fmt.method($0)).tag($0) }
            }
            .pickerStyle(.segmented)

            HStack {
                TextField("To'lov summasi", text: $amount)
                    .keyboardType(.decimalPad)
                    .padding(12)
                    .background(.white.opacity(0.85), in: RoundedRectangle(cornerRadius: 14))
                    .onChange(of: amount) { old, value in
                        let clean = Fmt.cleanAmount(value)
                        if clean != value { amount = clean }
                        if value != old, value != Fmt.plain(totals?.total) { amountEdited = true }
                    }

                Picker("Valyuta", selection: $currency) {
                    Text("so'm").tag("UZS")
                    Text("$").tag("USD")
                }
                .pickerStyle(.segmented)
                .frame(width: 120)
            }

            ErrorBanner(message: error)

            Button(action: submit) {
                if submitting { ProgressView().tint(.white) } else { Text("Savdoni yopish") }
            }
            .buttonStyle(PrimaryButtonStyle())
            .disabled(submitting || totals == nil || (Double(amount) ?? 0) <= 0)
        }
    }

    private func binding<Value>(_ id: String, _ keyPath: WritableKeyPath<CartEntry, Value>) -> Binding<Value> {
        Binding(
            get: { entries.first { $0.id == id }![keyPath: keyPath] },
            set: { value in
                if let index = entries.firstIndex(where: { $0.id == id }) {
                    entries[index][keyPath: keyPath] = value
                }
            }
        )
    }

    // MARK: - Amallar

    private func search(_ code: String, fromScanner: Bool = false) {
        let trimmed = code.trimmingCharacters(in: .whitespaces)
        guard !trimmed.isEmpty else { return }

        notice = nil

        Task {
            do {
                let found: LookupResponse = try await session.get("stock/lookup", query: ["code": trimmed])

                if found.exact, found.units.count == 1 {
                    add(CartEntry(unit: found.units[0]))
                } else if found.exact, found.variants.count == 1 {
                    add(CartEntry(variant: found.variants[0]))
                } else if found.units.isEmpty, found.variants.isEmpty {
                    results = nil
                    notice = fromScanner ? "Tovar topilmadi: \(trimmed)" : "Hech narsa topilmadi"
                } else {
                    results = found
                }
            } catch {
                notice = error.localizedDescription
            }
        }
    }

    private func add(_ entry: CartEntry) {
        results = nil
        query = ""

        if let index = entries.firstIndex(where: { $0.id == entry.id }) {
            if let variant = entries[index].variant {
                entries[index].qty = min(entries[index].qty + 1, variant.qty)
                notice = nil
            } else {
                notice = "\(entry.name) savatda bor"
            }
        } else {
            entries.append(entry)
            notice = nil
        }
    }

    private var items: [CartItemBody] {
        entries.map {
            CartItemBody(stockUnitId: $0.unit?.id, variantId: $0.variant?.id, qty: $0.qty, discount: $0.discount.isEmpty ? "0" : Fmt.cleanAmount($0.discount))
        }
    }

    /// Savat o'zgarganda serverdan qayta hisoblatadi.
    private func recalculate() async {
        guard !entries.isEmpty else {
            totals = nil
            error = nil
            amountEdited = false
            return
        }

        try? await Task.sleep(for: .milliseconds(250))
        guard !Task.isCancelled else { return }

        do {
            let result: CartTotals = try await session.post("sales/preview", body: CartBody(items: items))
            totals = result
            error = nil

            if !amountEdited {
                amount = Fmt.plain(result.total)
                currency = result.currency
                amountEdited = false
            }
        } catch {
            if !Task.isCancelled { self.error = error.localizedDescription }
        }
    }

    private func submit() {
        submitting = true
        error = nil

        Task {
            do {
                var body = CartBody(items: items)
                body.payments = [PaymentBody(method: method, currency: currency, amount: amount)]

                let result: Sale = try await session.post("sales", body: body, idempotencyKey: key)
                key = UUID().uuidString
                entries = []
                amount = ""
                amountEdited = false
                sale = result
            } catch let failure as APIError {
                // Rad etilgan so'rov serverda saqlanadi: tuzatilgan savat yangi kalit bilan yuboriladi.
                if !failure.isRetryable { key = UUID().uuidString }
                error = failure.message
            } catch {
                self.error = error.localizedDescription
            }
            submitting = false
        }
    }
}

extension Sale: Identifiable {}

private extension Array {
    subscript(safe index: Int) -> Element? { indices.contains(index) ? self[index] : nil }
}

private struct ReceiptSheet: View {
    let sale: Sale
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            ZStack {
                AppBackground()

                ScrollView {
                    VStack(spacing: 12) {
                        Label("Savdo yopildi", systemImage: "checkmark.circle.fill")
                            .font(.title2.weight(.heavy))
                            .foregroundStyle(Palette.success)
                            .frame(maxWidth: .infinity, alignment: .leading)

                        GlassCard {
                            KeyValue(label: "Chek", value: "#\(sale.number)")
                            KeyValue(label: "Sana", value: sale.date ?? "")
                            Divider()
                            ForEach(sale.items ?? []) { item in
                                HStack(alignment: .top) {
                                    VStack(alignment: .leading) {
                                        Text(item.qty > 1 ? "\(item.name) × \(item.qty)" : item.name).font(.subheadline.weight(.bold))
                                        if let imei = item.imei { Text("IMEI \(imei)").font(.caption).foregroundStyle(Palette.inkSoft) }
                                        if let warranty = item.warrantyUntil { Text("Kafolat: \(warranty) gacha").font(.caption).foregroundStyle(Palette.inkSoft) }
                                    }
                                    Spacer()
                                    Text(item.finalLabel ?? "").font(.subheadline.weight(.bold)).monospacedDigit()
                                }
                            }
                            Divider()
                            KeyValue(label: "Jami", value: sale.totalLabel, strong: true)
                            ForEach(sale.payments ?? []) { payment in
                                KeyValue(label: payment.label ?? "", value: payment.amountLabel ?? "")
                            }
                            if sale.hasChange { KeyValue(label: "Qaytim", value: sale.changeLabel ?? "") }
                            if let profit = sale.profitLabel { KeyValue(label: "Foyda", value: profit) }
                        }

                        Button("Yangi savdo") { dismiss() }.buttonStyle(PrimaryButtonStyle())
                    }
                    .padding(16)
                }
            }
            .navigationTitle("Chek")
            .navigationBarTitleDisplayMode(.inline)
        }
    }
}

// MARK: - Skaner

private struct ScannerSheet: View {
    let message: String?
    let onCode: (String) -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            ZStack(alignment: .bottom) {
                if DataScannerViewController.isSupported, DataScannerViewController.isAvailable {
                    BarcodeScanner(onCode: onCode).ignoresSafeArea()
                } else {
                    VStack(spacing: 8) {
                        Image(systemName: "camera.fill").font(.largeTitle).foregroundStyle(Palette.inkSoft)
                        Text("Skaner bu qurilmada ishlamaydi yoki kameraga ruxsat berilmagan.\nSozlamalar → PhoneTrade → Kamera.")
                            .multilineTextAlignment(.center)
                            .foregroundStyle(Palette.inkMid)
                    }
                    .padding(32)
                    .frame(maxHeight: .infinity)
                }

                Text(message ?? "IMEI yoki barcode'ni kameraga to'g'rilang")
                    .font(.subheadline.weight(.semibold))
                    .foregroundStyle(.white)
                    .padding(14)
                    .frame(maxWidth: .infinity)
                    .background(.black.opacity(0.6), in: RoundedRectangle(cornerRadius: 16))
                    .padding(16)
            }
            .navigationTitle("Skaner")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) { Button("Tayyor") { dismiss() } }
            }
        }
    }
}

private struct BarcodeScanner: UIViewControllerRepresentable {
    let onCode: (String) -> Void

    func makeCoordinator() -> Coordinator { Coordinator(onCode: onCode) }

    func makeUIViewController(context: Context) -> DataScannerViewController {
        let scanner = DataScannerViewController(
            recognizedDataTypes: [.barcode()],
            qualityLevel: .balanced,
            recognizesMultipleItems: false,
            isHighlightingEnabled: true
        )
        scanner.delegate = context.coordinator
        try? scanner.startScanning()

        return scanner
    }

    func updateUIViewController(_ controller: DataScannerViewController, context: Context) {}

    static func dismantleUIViewController(_ controller: DataScannerViewController, coordinator: Coordinator) {
        controller.stopScanning()
    }

    final class Coordinator: NSObject, DataScannerViewControllerDelegate {
        private let onCode: (String) -> Void
        private var lastCode = ""
        private var lastTime = Date.distantPast

        init(onCode: @escaping (String) -> Void) { self.onCode = onCode }

        func dataScanner(_ dataScanner: DataScannerViewController, didAdd addedItems: [RecognizedItem], allItems: [RecognizedItem]) {
            for case let .barcode(barcode) in addedItems {
                guard let value = barcode.payloadStringValue?.trimmingCharacters(in: .whitespaces), !value.isEmpty else { continue }

                // Bir xil kod 2 soniya ichida qayta yuborilmaydi: bitta telefon savatga ikki marta tushmasin.
                if value == lastCode, Date().timeIntervalSince(lastTime) < 2 { continue }

                lastCode = value
                lastTime = Date()
                onCode(value)
            }
        }
    }
}
