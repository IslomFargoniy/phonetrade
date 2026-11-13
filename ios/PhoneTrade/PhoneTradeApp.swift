import SwiftUI
import UIKit

@main
struct PhoneTradeApp: App {
    @State private var session = Session()

    var body: some Scene {
        WindowGroup {
            ZStack {
                AppBackground()

                if session.token == nil {
                    LoginView()
                } else {
                    MainView()
                }
            }
            .environment(session)
            .tint(Palette.primary)
            .preferredColorScheme(.light)
        }
    }
}

struct LoginView: View {
    @Environment(Session.self) private var session
    @State private var phone = "+998"
    @State private var password = ""
    @State private var loading = false
    @State private var error: String?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                Image("Logo")
                    .resizable()
                    .frame(width: 72, height: 72)
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 2) {
                    Text("PhoneTrade").font(.system(size: 34, weight: .heavy))
                    Text("Do'kon egasi uchun").foregroundStyle(Palette.inkMid)
                }

                GlassCard {
                    TextField("Telefon raqam", text: $phone)
                        .keyboardType(.phonePad)
                        .textContentType(.username)
                        .padding(14)
                        .background(.white.opacity(0.8), in: RoundedRectangle(cornerRadius: 14))

                    SecureField("Parol", text: $password)
                        .textContentType(.password)
                        .padding(14)
                        .background(.white.opacity(0.8), in: RoundedRectangle(cornerRadius: 14))

                    ErrorBanner(message: error)

                    Button(action: submit) {
                        if loading { ProgressView().tint(.white) } else { Text("Kirish") }
                    }
                    .buttonStyle(PrimaryButtonStyle())
                    .disabled(loading || phone.filter(\.isNumber).count < 9 || password.isEmpty)
                }

                Text("Akkauntni tizim administratori ochadi.")
                    .font(.footnote)
                    .foregroundStyle(Palette.inkSoft)
            }
            .padding(20)
            .padding(.top, 60)
        }
        .scrollDismissesKeyboard(.interactively)
        #if DEBUG
        .task {
            // Faqat debug yig'ilish: simulyatorda lokal serverning test akkaunti bilan avtomatik kirish
            // (SIMCTL_CHILD_PHONETRADE_TEST_LOGIN="telefon:parol"). Release'ga kirmaydi.
            let parts = (ProcessInfo.processInfo.environment["PHONETRADE_TEST_LOGIN"] ?? "").split(separator: ":", maxSplits: 1)
            if parts.count == 2 {
                phone = String(parts[0])
                password = String(parts[1])
                submit()
            }
        }
        #endif
    }

    private func submit() {
        loading = true
        error = nil

        Task {
            do {
                try await session.signIn(phone: phone, password: password, device: UIDevice.current.name)
            } catch {
                self.error = error.localizedDescription
            }
            loading = false
        }
    }
}

struct MainView: View {
    @Environment(Session.self) private var session
    @State private var error: String?
    @State private var showProfile = false

    var body: some View {
        Group {
            if let profile = session.profile {
                if profile.store.branch == nil {
                    ErrorBanner(message: "Sizga filial biriktirilmagan.").padding()
                } else {
                    tabs(profile)
                }
            } else {
                VStack(spacing: 12) {
                    if let error {
                        ErrorBanner(message: error)
                        Button("Qayta urinish") { load() }.buttonStyle(PrimaryButtonStyle())
                        Button("Chiqish") { Task { await session.signOut() } }
                    } else {
                        ProgressView()
                    }
                }
                .padding(24)
            }
        }
        .task { load() }
        .sheet(isPresented: $showProfile) { ProfileSheet().presentationDetents([.medium, .large]) }
    }

    private func load() {
        error = nil

        Task {
            do {
                try await session.refreshProfile()
            } catch {
                self.error = error.localizedDescription
            }
        }
    }

    private func tabs(_ profile: Profile) -> some View {
        TabView {
            // Tartib va ikonkalar dizayndagidek: Hisobot, Sotuv, Nasiya, Ombor, Mijozlar.
            if profile.user.isOwner {
                screen("Hisobot", icon: "chart.bar", profile: profile) { ReportView() }
            }
            screen("Sotuv", icon: "barcode.viewfinder", profile: profile) { SaleView() }
            screen("Nasiya", icon: "calendar", profile: profile) { InstallmentsView() }
            screen("Ombor", icon: "shippingbox", profile: profile) { StockView() }
            screen("Mijozlar", icon: "person", profile: profile) { CustomersView() }
        }
        // Chiziqli (to'ldirilmagan) ikonkalar.
        .environment(\.symbolVariants, .none)
        // Filial almashganda ekranlar noldan ochiladi: ro'yxatlar va savat filialga tegishli.
        .id(profile.store.branch?.id)
    }

    private func screen<Content: View>(_ title: String, icon: String, profile: Profile, @ViewBuilder content: () -> Content) -> some View {
        NavigationStack {
            ZStack {
                AppBackground()
                content()
            }
            .navigationTitle(title)
            .toolbar {
                // Bugungi kurs kapsulasi (dizayndagidek) va profil.
                ToolbarItem(placement: .topBarTrailing) {
                    Text(profile.store.rate.map { "1 $ = \(Fmt.money($0).replacingOccurrences(of: " so'm", with: ""))" } ?? "Kurs yo'q")
                        .font(.footnote.weight(.bold))
                        .monospacedDigit()
                        .foregroundStyle(profile.store.rate == nil ? Palette.danger : Palette.ink)
                        .padding(.horizontal, 6)
                }
                ToolbarItem(placement: .topBarTrailing) {
                    Button { showProfile = true } label: { Image(systemName: "person.crop.circle") }
                        .accessibilityLabel("Profil")
                }
            }
        }
        .tabItem { Label(title, systemImage: icon) }
    }
}

struct ProfileSheet: View {
    @Environment(Session.self) private var session
    @Environment(\.dismiss) private var dismiss
    @State private var deletePassword = ""
    @State private var confirmDelete = false
    @State private var deleting = false
    @State private var deleteMessage: String?

    var body: some View {
        NavigationStack {
            List {
                if let profile = session.profile {
                    Section {
                        VStack(alignment: .leading, spacing: 4) {
                            Text(profile.user.name).font(.title3.weight(.bold))
                            Text("\(Fmt.phone(profile.user.phone)) · \(profile.user.isOwner ? "Do'kon egasi" : "Sotuvchi")")
                                .font(.subheadline)
                                .foregroundStyle(.secondary)
                            Text(profile.store.name ?? "").font(.subheadline).foregroundStyle(.secondary)
                        }
                    }

                    Section("Dollar kursi") {
                        Text(profile.store.rate.map { "1 $ = \(Fmt.money($0))" } ?? "Bugungi kurs kiritilmagan")
                            .foregroundStyle(profile.store.rate == nil ? Palette.danger : Palette.ink)
                    }

                    let branches = profile.store.branches ?? []
                    if branches.count > 1 {
                        Section("Filial") {
                            ForEach(branches) { branch in
                                Button {
                                    Task {
                                        await session.switchBranch(branch.id)
                                        dismiss()
                                    }
                                } label: {
                                    HStack {
                                        Text(branch.name).foregroundStyle(Palette.ink)
                                        Spacer()
                                        if branch.id == profile.store.branch?.id {
                                            Image(systemName: "checkmark").foregroundStyle(Palette.primary)
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if let profile = session.profile {
                    Section {
                        SecureField("Parolingiz", text: $deletePassword)
                        Button(profile.user.isOwner ? "Do'konni o'chirish so'rovini yuborish" : "Akkauntni o'chirish", role: .destructive) {
                            confirmDelete = true
                        }
                        .disabled(deletePassword.isEmpty || deleting)
                        if let deleteMessage { Text(deleteMessage).font(.footnote).foregroundStyle(.secondary) }
                    } header: {
                        Text(profile.user.isOwner ? "Do'konni o'chirish" : "Akkauntni o'chirish")
                    } footer: {
                        Text(profile.user.isOwner
                            ? "Butun do'kon — xodimlar, mijozlar, pasport rasmlari, ombor va savdo yozuvlari bilan — 30 kun ichida o'chiriladi. Web paneldagi Sozlamalar → Profil bo'limida bekor qilish mumkin."
                            : "Akkauntingiz darhol o'chiriladi: ism, telefon va parol tozalanadi. Siz qilgan savdolar do'kon hisobida shaxsiy ma'lumotsiz qoladi.")
                    }
                }

                Section {
                    Button("Chiqish", role: .destructive) {
                        Task {
                            await session.signOut()
                            dismiss()
                        }
                    }
                }
            }
            .navigationTitle("Profil")
            .navigationBarTitleDisplayMode(.inline)
            .confirmationDialog("Bu amalni qaytarib bo'lmaydi.", isPresented: $confirmDelete, titleVisibility: .visible) {
                Button("Ha, davom etish", role: .destructive) { deleteAccount() }
                Button("Bekor qilish", role: .cancel) {}
            }
        }
    }

    private func deleteAccount() {
        deleting = true
        deleteMessage = nil

        Task {
            do {
                deleteMessage = try await session.deleteAccount(password: deletePassword)
                deletePassword = ""
                if session.token == nil { dismiss() }
            } catch {
                deleteMessage = error.localizedDescription
            }
            deleting = false
        }
    }
}
