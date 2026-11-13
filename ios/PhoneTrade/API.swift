import Foundation
import Observation
import Security

/// Server qaytargan xato. `code` o'zgarmas (docs/openapi.yaml), `message` foydalanuvchiga ko'rsatiladi.
struct APIError: LocalizedError {
    let code: String
    let message: String
    let status: Int

    var errorDescription: String? { message }

    /// Aloqa uzilgan yoki server xatosi: natija noma'lum, o'sha Idempotency-Key bilan qayta urinish kerak.
    var isRetryable: Bool { status == 0 || status >= 500 || status == 409 }
}

private struct ErrorBody: Decodable {
    let code: String?
    let message: String?
    let errors: [String: [String]]?
}

private struct NoBody: Encodable {}

/// Token (Keychain'da), profil va tarmoq so'rovlari.
@MainActor
@Observable
final class Session {
    private(set) var token: String? = Keychain.read()
    private(set) var profile: Profile?

    var branchId: Int? {
        get { UserDefaults.standard.object(forKey: "branch_id") as? Int }
        set { UserDefaults.standard.set(newValue, forKey: "branch_id") }
    }

    private let baseURL: URL = {
        // Simulyatorda lokal serverni sinash: SIMCTL_CHILD_PHONETRADE_API_URL=http://127.0.0.1:8000/api/v1/
        let override = ProcessInfo.processInfo.environment["PHONETRADE_API_URL"]
        return URL(string: override ?? "https://phonetrade.simulator.uz/api/v1/")!
    }()

    private let decoder: JSONDecoder = {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        return decoder
    }()

    private let encoder: JSONEncoder = {
        let encoder = JSONEncoder()
        encoder.keyEncodingStrategy = .convertToSnakeCase
        return encoder
    }()

    // MARK: - Sessiya

    func signIn(phone: String, password: String, device: String) async throws {
        let result: Profile = try await post("login", body: LoginBody(phone: phone, password: password, deviceName: device))
        token = result.token
        Keychain.save(result.token)
        apply(result)
    }

    /// Parolsiz demo rejimi: namuna do'konning demo xodimi nomidan kiradi.
    func demoSignIn() async throws {
        let result: Profile = try await post("demo-login")
        token = result.token
        Keychain.save(result.token)
        apply(result)
    }

    func refreshProfile() async throws {
        apply(try await get("me"))
    }

    func switchBranch(_ id: Int) async {
        branchId = id
        try? await refreshProfile()
    }

    func signOut() async {
        // Server tokenni bekor qiladi; aloqa bo'lmasa ham qurilmadan o'chiriladi.
        let _: MessageResponse? = try? await post("logout")
        clear()
    }

    /// Akkauntni o'chirish. Sotuvchi darhol o'chiriladi va chiqariladi; egasida so'rov yuboriladi.
    func deleteAccount(password: String) async throws -> String {
        let result: DeleteAccountResponse = try await send(method: "DELETE", "account", body: PasswordBody(password: password))

        if result.status == "deleted" { clear() }

        return result.message ?? ""
    }

    private func apply(_ profile: Profile) {
        self.profile = profile
        branchId = profile.store.branch?.id
    }

    private func clear() {
        token = nil
        profile = nil
        Keychain.save(nil)
        UserDefaults.standard.removeObject(forKey: "branch_id")
    }

    // MARK: - So'rovlar

    func get<T: Decodable>(_ path: String, query: [String: String?] = [:]) async throws -> T {
        var components = URLComponents(url: baseURL.appendingPathComponent(path), resolvingAgainstBaseURL: false)!
        let items = query.compactMap { key, value in value.map { URLQueryItem(name: key, value: $0) } }
        if !items.isEmpty { components.queryItems = items }

        var request = URLRequest(url: components.url!)
        request.httpMethod = "GET"

        return try await send(request)
    }

    func post<T: Decodable>(_ path: String, idempotencyKey: String? = nil) async throws -> T {
        try await post(path, body: NoBody(), idempotencyKey: idempotencyKey)
    }

    func post<T: Decodable, B: Encodable>(_ path: String, body: B, idempotencyKey: String? = nil) async throws -> T {
        try await send(method: "POST", path, body: body, idempotencyKey: idempotencyKey)
    }

    func delete<T: Decodable>(_ path: String) async throws -> T {
        var request = URLRequest(url: baseURL.appendingPathComponent(path))
        request.httpMethod = "DELETE"

        return try await send(request)
    }

    /// JPEG fayllarni multipart/form-data bilan yuboradi (`field[]`).
    func upload<T: Decodable>(_ path: String, field: String, jpegs: [Data]) async throws -> T {
        let boundary = "PhoneTrade-\(UUID().uuidString)"
        var body = Data()

        for (index, jpeg) in jpegs.enumerated() {
            body.append(Data("--\(boundary)\r\n".utf8))
            body.append(Data("Content-Disposition: form-data; name=\"\(field)[]\"; filename=\"photo\(index).jpg\"\r\n".utf8))
            body.append(Data("Content-Type: image/jpeg\r\n\r\n".utf8))
            body.append(jpeg)
            body.append(Data("\r\n".utf8))
        }
        body.append(Data("--\(boundary)--\r\n".utf8))

        var request = URLRequest(url: baseURL.appendingPathComponent(path))
        request.httpMethod = "POST"
        request.setValue("multipart/form-data; boundary=\(boundary)", forHTTPHeaderField: "Content-Type")
        request.httpBody = body

        return try await send(request)
    }

    private func send<T: Decodable, B: Encodable>(method: String, _ path: String, body: B, idempotencyKey: String? = nil) async throws -> T {
        var request = URLRequest(url: baseURL.appendingPathComponent(path))
        request.httpMethod = method
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try encoder.encode(body)

        if let idempotencyKey {
            request.setValue(idempotencyKey, forHTTPHeaderField: "Idempotency-Key")
        }

        return try await send(request)
    }

    private func send<T: Decodable>(_ original: URLRequest) async throws -> T {
        var request = original
        request.timeoutInterval = 30
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        if let token { request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization") }
        if let branchId { request.setValue(String(branchId), forHTTPHeaderField: "X-Branch-Id") }

        let data: Data
        let response: URLResponse

        do {
            (data, response) = try await URLSession.shared.data(for: request)
        } catch {
            throw APIError(code: "NETWORK", message: "Internet aloqasi yo'q. Qayta urinib ko'ring.", status: 0)
        }

        let status = (response as? HTTPURLResponse)?.statusCode ?? 0

        guard (200..<300).contains(status) else {
            let body = try? decoder.decode(ErrorBody.self, from: data)

            if status == 401, token != nil { clear() }

            let fallback: String = switch status {
            case 403: "Bu amalga ruxsat yo'q."
            case 404: "Topilmadi."
            case 429: "Juda ko'p urinish. Birozdan keyin qayta urinib ko'ring."
            default: "Server xatosi (\(status)). Qayta urinib ko'ring."
            }

            throw APIError(
                code: body?.code ?? "HTTP_\(status)",
                message: body?.errors?.values.first?.first ?? body?.message ?? fallback,
                status: status
            )
        }

        do {
            return try decoder.decode(T.self, from: data)
        } catch {
            throw APIError(code: "DECODE", message: "Server javobini o'qib bo'lmadi. Ilovani yangilang.", status: status)
        }
    }
}

/// Token faqat shu qurilmada, qulflangan holatda o'qib bo'lmaydigan qilib saqlanadi.
enum Keychain {
    private static let query: [String: Any] = [
        kSecClass as String: kSecClassGenericPassword,
        kSecAttrService as String: "uz.simulator.phonetrade",
        kSecAttrAccount as String: "api-token",
    ]

    static func read() -> String? {
        var item: CFTypeRef?
        let status = SecItemCopyMatching(query.merging([kSecReturnData as String: true]) { $1 } as CFDictionary, &item)

        guard status == errSecSuccess, let data = item as? Data else { return nil }

        return String(data: data, encoding: .utf8)
    }

    static func save(_ token: String?) {
        SecItemDelete(query as CFDictionary)

        guard let token else { return }

        SecItemAdd(query.merging([
            kSecValueData as String: Data(token.utf8),
            kSecAttrAccessible as String: kSecAttrAccessibleWhenUnlockedThisDeviceOnly,
        ]) { $1 } as CFDictionary, nil)
    }
}
