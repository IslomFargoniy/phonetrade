import SwiftUI

struct InstallmentsView: View {
    @Environment(Session.self) private var session
    @State private var filter = "late"
    @State private var plans: [Plan]?
    @State private var error: String?
    @State private var opened: Plan?

    var body: some View {
        ScrollView {
            VStack(spacing: 12) {
                Picker("Saralash", selection: $filter) {
                    Text("Kechikkan").tag("late")
                    Text("Bugun").tag("today")
                    Text("Hammasi").tag("active")
                }
                .pickerStyle(.segmented)

                ErrorBanner(message: error)

                LoadState(value: plans, error: error, isEmpty: \.isEmpty, emptyText: emptyText) { plans in
                    ForEach(plans) { plan in
                        Button { opened = plan } label: { PlanRow(plan: plan) }.buttonStyle(.plain)
                    }
                }
            }
            .padding(16)
        }
        .refreshable { await load() }
        .task(id: filter) {
            plans = nil
            await load()
        }
        .sheet(item: $opened, onDismiss: { Task { await load() } }) { plan in
            PlanSheet(plan: plan)
        }
    }

    private var emptyText: String {
        switch filter {
        case "late": "Kechikkan to'lov yo'q."
        case "today": "Bugun to'lov kuni kelgan mijoz yo'q."
        default: "Faol nasiya shartnomasi yo'q."
        }
    }

    private func load() async {
        do {
            let response: PlansResponse = try await session.get("installments", query: ["filter": filter])
            plans = response.plans
            error = nil
        } catch {
            self.error = error.localizedDescription
            plans = plans ?? []
        }
    }
}

private func stateColor(_ state: String?) -> Color {
    switch state {
    case "late": Palette.danger
    case "today": Palette.warning
    case "closed": Palette.success
    default: Palette.primary
    }
}

private struct PlanRow: View {
    let plan: Plan

    var body: some View {
        GlassCard {
            HStack {
                Text(plan.customer ?? "").font(.headline)
                Spacer()
                Badge(text: plan.stateLabel ?? "", color: stateColor(plan.state))
            }
            HStack(spacing: 10) {
                ProductImage(url: plan.imageUrl, size: 36)
                Text(plan.product ?? "").font(.footnote).foregroundStyle(Palette.inkMid).lineLimit(1)
            }
            HStack(alignment: .top) {
                VStack(alignment: .leading, spacing: 2) {
                    Text("Navbatdagi to'lov").font(.caption).foregroundStyle(Palette.inkSoft)
                    Text([plan.nextAmountLabel, plan.nextDate].compactMap { $0 }.joined(separator: " · "))
                        .font(.subheadline.weight(.bold))
                        .monospacedDigit()
                }
                Spacer()
                VStack(alignment: .trailing, spacing: 2) {
                    Text("Qolgan qarz").font(.caption).foregroundStyle(Palette.inkSoft)
                    Text(plan.remainingLabel ?? "").font(.subheadline.weight(.bold)).monospacedDigit()
                }
            }
        }
    }
}

private struct PlanSheet: View {
    @Environment(Session.self) private var session
    @State var plan: Plan
    @State private var amount = ""
    @State private var method = "cash"
    @State private var currency = "UZS"
    @State private var loading = false
    @State private var error: String?
    @State private var success: String?
    /// Aloqa uzilib qayta bosilsa to'lov ikki marta yozilmaydi.
    @State private var key = UUID().uuidString

    var body: some View {
        NavigationStack {
            ZStack {
                AppBackground()

                ScrollView {
                    VStack(spacing: 12) {
                        GlassCard {
                            HStack {
                                VStack(alignment: .leading) {
                                    Text(plan.customer ?? "").font(.title3.weight(.heavy))
                                    Text(plan.customerPhone ?? "").foregroundStyle(Palette.inkMid)
                                }
                                Spacer()
                                Badge(text: plan.stateLabel ?? "", color: stateColor(plan.state))
                            }
                            Divider()
                            KeyValue(label: "Tovar", value: plan.product ?? "")
                            KeyValue(label: "Shartnoma", value: "\(plan.months) oy · \(plan.markupPct ?? "")% · \(plan.createdAt ?? "")")
                            KeyValue(label: "Jami", value: plan.totalLabel ?? "")
                            KeyValue(label: "To'langan", value: plan.paidLabel ?? "")
                            KeyValue(label: "Qolgan qarz", value: plan.remainingLabel ?? "", strong: true)
                        }

                        if plan.status == "active" {
                            SectionTitle("To'lov qabul qilish")
                            GlassCard {
                                Picker("Usul", selection: $method) {
                                    ForEach(["cash", "card", "transfer"], id: \.self) { Text(Fmt.method($0)).tag($0) }
                                }
                                .pickerStyle(.segmented)

                                HStack {
                                    TextField("Summa", text: $amount)
                                        .keyboardType(.decimalPad)
                                        .padding(12)
                                        .background(.white.opacity(0.85), in: RoundedRectangle(cornerRadius: 14))
                                        .onChange(of: amount) { _, value in amount = Fmt.cleanAmount(value) }

                                    Picker("Valyuta", selection: $currency) {
                                        Text("so'm").tag("UZS")
                                        Text("$").tag("USD")
                                    }
                                    .pickerStyle(.segmented)
                                    .frame(width: 120)
                                }

                                Text("Istalgan summa: eng eski to'lanmagan oylikdan boshlab yopiladi.")
                                    .font(.footnote)
                                    .foregroundStyle(Palette.inkMid)

                                ErrorBanner(message: error)
                                if let success { Badge(text: success, color: Palette.success) }

                                Button(action: pay) {
                                    if loading { ProgressView().tint(.white) } else { Text("Qabul qilish") }
                                }
                                .buttonStyle(PrimaryButtonStyle())
                                .disabled(loading || (Double(amount) ?? 0) <= 0)
                            }
                        }

                        SectionTitle("Jadval")
                        GlassCard {
                            ForEach(plan.schedule ?? []) { row in
                                HStack {
                                    Text("\(row.no)").font(.subheadline.weight(.bold)).foregroundStyle(Palette.inkSoft).frame(width: 24, alignment: .leading)
                                    VStack(alignment: .leading) {
                                        Text(row.date ?? "").font(.subheadline.weight(.semibold))
                                        if row.state == "partial" || row.state == "late" {
                                            Text("To'langan: \(row.paidLabel ?? "")").font(.caption).foregroundStyle(Palette.inkSoft)
                                        }
                                    }
                                    Spacer()
                                    Text(row.amountLabel ?? "")
                                        .font(.subheadline.weight(.bold))
                                        .monospacedDigit()
                                        .foregroundStyle(row.state == "paid" ? Palette.success : row.state == "late" ? Palette.danger : Palette.ink)
                                    if row.state == "paid" {
                                        Image(systemName: "checkmark.circle.fill").foregroundStyle(Palette.success)
                                    }
                                }
                            }
                        }
                    }
                    .padding(16)
                }
                .scrollDismissesKeyboard(.interactively)
            }
            .navigationTitle("Shartnoma")
            .navigationBarTitleDisplayMode(.inline)
        }
        .onAppear {
            amount = Fmt.plain(plan.nextAmount)
            currency = plan.currency
        }
    }

    private func pay() {
        loading = true
        error = nil
        success = nil

        Task {
            do {
                let updated: Plan = try await session.post(
                    "installments/\(plan.id)/payments",
                    body: PaymentBody(method: method, currency: currency, amount: amount),
                    idempotencyKey: key
                )
                key = UUID().uuidString
                plan = updated
                amount = Fmt.plain(updated.nextAmount)
                currency = updated.currency
                success = "To'lov qabul qilindi."
            } catch let failure as APIError {
                if !failure.isRetryable { key = UUID().uuidString }
                error = failure.message
            } catch {
                self.error = error.localizedDescription
            }
            loading = false
        }
    }
}
