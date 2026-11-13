import Foundation

// API javoblari (docs/openapi.yaml). Kalitlar snake_case -> camelCase avtomatik o'giriladi.
// Javobda bo'lmasligi mumkin bo'lgan maydonlar optional.

struct LoginBody: Encodable {
    let phone: String
    let password: String
    let deviceName: String
}

struct MessageResponse: Decodable {
    let message: String?
}

struct Profile: Decodable {
    let token: String?
    let user: UserInfo
    let store: StoreInfo
}

struct UserInfo: Decodable {
    let id: Int
    let name: String
    let phone: String
    let role: String

    var isOwner: Bool { role == "owner" }
}

struct BranchInfo: Decodable, Identifiable, Hashable {
    let id: Int
    let name: String
}

struct StoreInfo: Decodable {
    let name: String?
    let branch: BranchInfo?
    let branches: [BranchInfo]?
    let rate: String?
}

// MARK: - Hisobot va kassa

struct Summary: Decodable {
    let from: String
    let to: String
    let salesCount: Int
    let revenue: String
    let grossProfit: String
    let expenses: String
    let netProfit: String
    let installmentIncome: String
    let installmentPayments: Int
    let activeDebt: String
    let activePlans: Int
    let expectedProfit: [ExpectedProfit]?
}

struct ExpectedProfit: Decodable, Identifiable {
    let month: String
    let label: String
    let profit: String

    var id: String { month }
}

struct CashAccount: Decodable, Identifiable {
    let id: Int
    let type: String
    let label: String
    let uzs: String
    let usd: String
}

struct CashOverview: Decodable {
    let branch: BranchInfo
    let branchAccounts: [CashAccount]
    let adminAccounts: [CashAccount]
}

// MARK: - Ombor va mijozlar

struct StockUnit: Decodable, Identifiable, Hashable {
    let id: Int
    let product: String
    let imei1: String?
    let barcode: String?
    let attributes: [String: String]?
    let saleCurrency: String
    let salePriceLabel: String
    let costLabel: String?

    var details: String { (attributes ?? [:]).values.filter { !$0.isEmpty }.sorted().joined(separator: " · ") }
}

struct StockVariant: Decodable, Identifiable, Hashable {
    let id: Int
    let product: String
    let attributes: [String: String]?
    let saleCurrency: String
    let salePriceLabel: String
    let qty: Int
}

struct StockResponse: Decodable {
    let units: [StockUnit]
}

struct LookupResponse: Decodable {
    let exact: Bool
    let units: [StockUnit]
    let variants: [StockVariant]
}

struct CustomerRow: Decodable, Identifiable {
    let id: Int
    let fullName: String?
    let phone: String?
    let activePlans: Int
}

struct CustomersResponse: Decodable {
    let customers: [CustomerRow]
}

// MARK: - Nasiya

struct ScheduleRow: Decodable, Identifiable {
    let no: Int
    let date: String?
    let amountLabel: String?
    let paidLabel: String?
    let state: String?

    var id: Int { no }
}

struct PlanPayment: Decodable, Identifiable {
    let id: Int
    let date: String?
    let amountLabel: String?
    let isInitial: Bool
}

struct Plan: Decodable, Identifiable {
    let id: Int
    let customer: String?
    let customerPhone: String?
    let product: String?
    let months: Int
    let currency: String
    let status: String?
    let state: String?
    let stateLabel: String?
    let nextDate: String?
    let nextAmount: String?
    let nextAmountLabel: String?
    let paidLabel: String?
    let remainingLabel: String?
    let totalLabel: String?
    let markupPct: String?
    let createdAt: String?
    let schedule: [ScheduleRow]?
    let payments: [PlanPayment]?
}

struct PlansResponse: Decodable {
    let plans: [Plan]
}

struct PaymentBody: Encodable {
    let method: String
    let currency: String
    let amount: String
}

// MARK: - Sotuv

struct CartItemBody: Encodable {
    let stockUnitId: Int?
    let variantId: Int?
    let qty: Int
    let discount: String
}

struct CartBody: Encodable {
    let items: [CartItemBody]
    var payments: [PaymentBody]?
    var mode = "full"
    var customerPhone: String?
    var customerName: String?
}

struct CartLine: Decodable {
    let netLabel: String?
    let maxDiscountLabel: String?
}

struct CartTotals: Decodable {
    let currency: String
    let rate: String?
    let mixed: Bool
    let total: String
    let totalLabel: String
    let lines: [CartLine]?
}

struct SaleItem: Decodable, Identifiable {
    let id: Int
    let name: String
    let qty: Int
    let imei: String?
    let finalLabel: String?
    let warrantyUntil: String?
}

struct SalePayment: Decodable, Identifiable {
    let id: Int
    let label: String?
    let amountLabel: String?
}

struct Sale: Decodable {
    let id: Int
    let number: Int
    let date: String?
    let totalLabel: String
    let changeLabel: String?
    let hasChange: Bool
    let profitLabel: String?
    let items: [SaleItem]?
    let payments: [SalePayment]?
}
