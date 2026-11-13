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
            if profile.user.isOwner {
                screen("Hisobot", icon: "chart.bar.fill") { ReportView() }
            }
            screen("Nasiya", icon: "calendar") { InstallmentsView() }
            screen("Ombor", icon: "shippingbox.fill") { StockView() }
            screen("Mijozlar", icon: "person.2.fill") { CustomersView() }
            screen("Sotuv", icon: "cart.fill") { SaleView() }
        }
        // Filial almashganda ekranlar noldan ochiladi: ro'yxatlar va savat filialga tegishli.
        .id(profile.store.branch?.id)
    }

    private func screen<Content: View>(_ title: String, icon: String, @ViewBuilder content: () -> Content) -> some View {
        NavigationStack {
            ZStack {
                AppBackground()
                content()
            }
            .navigationTitle(title)
            .toolbar {
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
        }
    }
}
