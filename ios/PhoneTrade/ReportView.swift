import SwiftUI

/// Egasi uchun asosiy ko'rsatkichlar va kassalar. Raqamlar web hisobotlari bilan bitta servisdan keladi.
struct ReportView: View {
    @Environment(Session.self) private var session
    @State private var period = "day"
    @State private var summary: Summary?
    @State private var cash: CashOverview?
    @State private var error: String?
    @State private var confirmSweep = false
    @State private var sweeping = false
    @State private var notice: String?
    @State private var sweepKey = UUID().uuidString

    var body: some View {
        ScrollView {
            VStack(spacing: 14) {
                Picker("Davr", selection: $period) {
                    Text("Bugun").tag("day")
                    Text("Hafta").tag("week")
                    Text("Oy").tag("month")
                }
                .pickerStyle(.segmented)

                ErrorBanner(message: error)

                LoadState(value: summary, error: error, isEmpty: { _ in false }, emptyText: "") { summary in
                    GlassCard {
                        Text("Sof foyda").font(.subheadline).foregroundStyle(Palette.inkMid)
                        Text(Fmt.money(summary.netProfit))
                            .font(.system(size: 32, weight: .heavy))
                            .monospacedDigit()
                            .foregroundStyle(summary.netProfit.hasPrefix("-") ? Palette.danger : Palette.ink)
                        Divider()
                        KeyValue(label: "Savdolar", value: "\(summary.salesCount) ta")
                        KeyValue(label: "Tushum", value: Fmt.money(summary.revenue))
                        KeyValue(label: "Yalpi foyda", value: Fmt.money(summary.grossProfit))
                        KeyValue(label: "Xarajatlar", value: Fmt.money(summary.expenses))
                    }

                    SectionTitle("Nasiya")
                    GlassCard {
                        KeyValue(label: "Faol shartnomalar", value: "\(summary.activePlans) ta")
                        KeyValue(label: "Umumiy qarz", value: Fmt.money(summary.activeDebt), strong: true)
                        KeyValue(label: "Davrdagi to'lovlar", value: "\(summary.installmentPayments) ta · \(Fmt.money(summary.installmentIncome))")
                    }

                    if let expected = summary.expectedProfit, !expected.isEmpty {
                        SectionTitle("Kutilayotgan foyda")
                        GlassCard {
                            ForEach(expected) { row in
                                KeyValue(label: row.label, value: Fmt.money(row.profit))
                            }
                        }
                    }
                }

                if let cash {
                    SectionTitle("Kassa — \(cash.branch.name)")
                    GlassCard {
                        accounts(cash.branchAccounts)
                        if let notice { Badge(text: notice, color: Palette.success) }
                        Button {
                            confirmSweep = true
                        } label: {
                            if sweeping { ProgressView().tint(.white) } else { Text("Kassani bo'shatish") }
                        }
                        .buttonStyle(PrimaryButtonStyle())
                        .disabled(sweeping)
                    }

                    SectionTitle("Admin kassa")
                    GlassCard { accounts(cash.adminAccounts) }
                }
            }
            .padding(16)
        }
        .refreshable { await load() }
        .task(id: period) { await load() }
        .confirmationDialog("Filial kassasidagi hamma pul admin kassaga o'tkaziladi.", isPresented: $confirmSweep, titleVisibility: .visible) {
            Button("Bo'shatish") { sweep() }
            Button("Bekor qilish", role: .cancel) {}
        }
    }

    private func accounts(_ rows: [CashAccount]) -> some View {
        ForEach(rows) { account in
            KeyValue(label: account.label, value: "\(Fmt.money(account.uzs)) · \(Fmt.money(account.usd, currency: "USD"))")
        }
    }

    private func load() async {
        do {
            async let summaryRequest: Summary = session.get("reports/summary", query: ["period": period])
            async let cashRequest: CashOverview = session.get("cash")
            (summary, cash) = try await (summaryRequest, cashRequest)
            error = nil
        } catch {
            self.error = error.localizedDescription
        }
    }

    private func sweep() {
        sweeping = true
        notice = nil
        error = nil

        Task {
            do {
                let _: MessageResponse = try await session.post("cash/sweep", idempotencyKey: sweepKey)
                sweepKey = UUID().uuidString
                notice = "Kassa bo'shatildi."
                await load()
            } catch let failure as APIError {
                if !failure.isRetryable { sweepKey = UUID().uuidString }
                error = failure.message
            } catch {
                self.error = error.localizedDescription
            }
            sweeping = false
        }
    }
}
