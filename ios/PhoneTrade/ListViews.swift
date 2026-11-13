import SwiftUI

/// Filial omboridagi telefonlar. Tannarx faqat egasiga keladi (server shunday beradi).
struct StockView: View {
    @Environment(Session.self) private var session
    @State private var query = ""
    @State private var units: [StockUnit]?
    @State private var error: String?

    var body: some View {
        ScrollView {
            VStack(spacing: 10) {
                ErrorBanner(message: error)

                LoadState(value: units, error: error, isEmpty: \.isEmpty, emptyText: query.isEmpty ? "Omborda telefon yo'q." : "Hech narsa topilmadi.") { units in
                    Text("\(units.count) ta telefon").font(.footnote).foregroundStyle(Palette.inkSoft).frame(maxWidth: .infinity, alignment: .leading)

                    ForEach(units) { unit in
                        GlassCard {
                            HStack(alignment: .firstTextBaseline) {
                                Text(unit.product).font(.headline)
                                Spacer()
                                Text(unit.salePriceLabel).font(.subheadline.weight(.heavy)).monospacedDigit().foregroundStyle(Palette.primary)
                            }
                            if !unit.details.isEmpty {
                                Text(unit.details).font(.footnote).foregroundStyle(Palette.inkMid)
                            }
                            Text("IMEI \(unit.imei1 ?? "") · \(unit.barcode ?? "")").font(.caption).monospacedDigit().foregroundStyle(Palette.inkSoft)
                            if let cost = unit.costLabel {
                                Text("Tannarx: \(cost)").font(.footnote.weight(.semibold)).foregroundStyle(Palette.inkMid)
                            }
                        }
                    }
                }
            }
            .padding(16)
        }
        .searchable(text: $query, prompt: "Model, IMEI yoki barcode")
        .refreshable { await load() }
        .task(id: query) {
            if !query.isEmpty { try? await Task.sleep(for: .milliseconds(350)) }
            guard !Task.isCancelled else { return }
            await load()
        }
    }

    private func load() async {
        do {
            let trimmed = query.trimmingCharacters(in: .whitespaces)
            let response: StockResponse = try await session.get("stock", query: ["q": trimmed.isEmpty ? nil : trimmed])
            units = response.units
            error = nil
        } catch {
            self.error = error.localizedDescription
            units = units ?? []
        }
    }
}

struct CustomersView: View {
    @Environment(Session.self) private var session
    @State private var query = ""
    @State private var customers: [CustomerRow]?
    @State private var error: String?

    var body: some View {
        ScrollView {
            VStack(spacing: 10) {
                ErrorBanner(message: error)

                LoadState(value: customers, error: error, isEmpty: \.isEmpty, emptyText: query.isEmpty ? "Mijozlar hali yo'q." : "Hech kim topilmadi.") { customers in
                    ForEach(customers) { customer in
                        GlassCard {
                            HStack {
                                VStack(alignment: .leading, spacing: 2) {
                                    Text((customer.fullName ?? "").isEmpty ? "Ismsiz mijoz" : customer.fullName ?? "").font(.headline)
                                    if let phone = customer.phone, let url = URL(string: "tel:+\(phone.filter(\.isNumber))") {
                                        Link(Fmt.phone(phone), destination: url).font(.subheadline).monospacedDigit()
                                    }
                                }
                                Spacer()
                                if customer.activePlans > 0 {
                                    Badge(text: "\(customer.activePlans) ta nasiya", color: Palette.warning)
                                }
                            }
                        }
                    }
                }
            }
            .padding(16)
        }
        .searchable(text: $query, prompt: "Ism yoki telefon")
        .refreshable { await load() }
        .task(id: query) {
            if !query.isEmpty { try? await Task.sleep(for: .milliseconds(350)) }
            guard !Task.isCancelled else { return }
            await load()
        }
    }

    private func load() async {
        do {
            let trimmed = query.trimmingCharacters(in: .whitespaces)
            let response: CustomersResponse = try await session.get("customers", query: ["q": trimmed.isEmpty ? nil : trimmed])
            customers = response.customers
            error = nil
        } catch {
            self.error = error.localizedDescription
            customers = customers ?? []
        }
    }
}
