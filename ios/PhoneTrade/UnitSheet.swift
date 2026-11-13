import PhotosUI
import SwiftUI
import UIKit

/// Bitta telefonga yuklash mumkin bo'lgan rasmlar soni (server chegarasi bilan bir xil).
private let maxPhotos = 8

/// Telefon kartasi: katta rasm, shu telefonning rasmlari (qo'shish/o'chirish) va ma'lumotlari.
struct UnitSheet: View {
    let unit: StockUnit
    let onUpdated: (StockUnit) -> Void

    @Environment(Session.self) private var session
    @State private var selected: String?
    @State private var picked: [PhotosPickerItem] = []
    @State private var showCamera = false
    @State private var busy = false
    @State private var error: String?

    private var photos: [UnitPhoto] { unit.photos ?? [] }
    private var canAdd: Bool { !busy && photos.count < maxPhotos }

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 14) {
                    if !unit.details.isEmpty {
                        Text(unit.details).foregroundStyle(Palette.inkMid)
                    }

                    ProductImage(url: selected ?? unit.imageUrl, size: 280, corner: 26)
                        .frame(maxWidth: .infinity)

                    Text("Rasmlar · \(photos.count)/\(maxPhotos)").font(.footnote.weight(.bold)).foregroundStyle(Palette.inkMid)

                    if photos.isEmpty {
                        Text("Bu telefonning o'z rasmi yo'q — model rasmi ko'rsatilmoqda. Telefonni suratga olib qo'shing.")
                            .font(.footnote)
                            .foregroundStyle(Palette.inkSoft)
                    } else {
                        ScrollView(.horizontal, showsIndicators: false) {
                            HStack(spacing: 10) {
                                ForEach(photos) { photo in
                                    ZStack(alignment: .topTrailing) {
                                        Button { selected = photo.url } label: {
                                            ProductImage(url: photo.url, size: 76)
                                        }
                                        .buttonStyle(.plain)
                                        .padding([.top, .trailing], 6)

                                        Button { Task { await delete(photo) } } label: {
                                            Image(systemName: "xmark.circle.fill")
                                                .font(.title3)
                                                .symbolRenderingMode(.palette)
                                                .foregroundStyle(Palette.ink, .white)
                                        }
                                        .disabled(busy)
                                        .accessibilityLabel("Rasmni o'chirish")
                                    }
                                }
                            }
                        }
                    }

                    ErrorBanner(message: error)

                    HStack(spacing: 10) {
                        Button { showCamera = true } label: {
                            Label("Kamera", systemImage: "camera").frame(maxWidth: .infinity)
                        }
                        .buttonStyle(.bordered)
                        .disabled(!canAdd || !UIImagePickerController.isSourceTypeAvailable(.camera))

                        PhotosPicker(selection: $picked, maxSelectionCount: max(1, maxPhotos - photos.count), matching: .images) {
                            Label("Galereya", systemImage: "photo").frame(maxWidth: .infinity)
                        }
                        .buttonStyle(.bordered)
                        .disabled(!canAdd)

                        if busy { ProgressView() }
                    }

                    GlassCard {
                        KeyValue(label: "IMEI 1", value: unit.imei1 ?? "")
                        if let barcode = unit.barcode { KeyValue(label: "Barcode", value: barcode) }
                        KeyValue(label: "Narx", value: unit.salePriceLabel)
                        if let cost = unit.costLabel { KeyValue(label: "Tannarx", value: cost) }
                        if let months = unit.warrantyMonths { KeyValue(label: "Kafolat", value: "\(months) oy") }
                        if let status = unit.statusLabel { KeyValue(label: "Holat", value: status) }
                        if let received = unit.receivedAt { KeyValue(label: "Kirim sanasi", value: received) }
                    }
                }
                .padding(16)
            }
            .background(AppBackground())
            .navigationTitle(unit.product)
            .navigationBarTitleDisplayMode(.inline)
        }
        .onChange(of: picked) { _, items in
            guard !items.isEmpty else { return }
            picked = []
            Task {
                var jpegs: [Data] = []
                for item in items {
                    if let data = try? await item.loadTransferable(type: Data.self), let jpeg = Self.jpeg(data) {
                        jpegs.append(jpeg)
                    }
                }
                await upload(jpegs)
            }
        }
        .fullScreenCover(isPresented: $showCamera) {
            CameraPicker { image in
                if let jpeg = image.flatMap({ Self.jpeg($0) }) {
                    Task { await upload([jpeg]) }
                }
            }
            .ignoresSafeArea()
        }
    }

    private func upload(_ jpegs: [Data]) async {
        guard !jpegs.isEmpty else {
            error = "Rasmni o'qib bo'lmadi."
            return
        }
        busy = true
        error = nil
        do {
            let updated: StockUnit = try await session.upload("stock/units/\(unit.id)/photos", field: "photos", jpegs: jpegs)
            selected = nil
            onUpdated(updated)
        } catch {
            self.error = error.localizedDescription
        }
        busy = false
    }

    private func delete(_ photo: UnitPhoto) async {
        busy = true
        error = nil
        do {
            let updated: StockUnit = try await session.delete("stock/photos/\(photo.id)")
            selected = nil
            onUpdated(updated)
        } catch {
            self.error = error.localizedDescription
        }
        busy = false
    }

    /// Rasmni 1800 px gacha kichraytirib JPEG qiladi (server chegarasi 10 MB).
    private static func jpeg(_ data: Data) -> Data? {
        UIImage(data: data).flatMap { jpeg($0) }
    }

    private static func jpeg(_ image: UIImage) -> Data? {
        let maxSide: CGFloat = 1800
        let scale = min(1, maxSide / max(image.size.width, image.size.height))
        let size = CGSize(width: image.size.width * scale, height: image.size.height * scale)
        let format = UIGraphicsImageRendererFormat()
        format.scale = 1

        return UIGraphicsImageRenderer(size: size, format: format)
            .image { _ in image.draw(in: CGRect(origin: .zero, size: size)) }
            .jpegData(compressionQuality: 0.85)
    }
}

/// Tizim kamerasi orqali bitta surat.
struct CameraPicker: UIViewControllerRepresentable {
    let onFinish: (UIImage?) -> Void
    @Environment(\.dismiss) private var dismiss

    func makeUIViewController(context: Context) -> UIImagePickerController {
        let picker = UIImagePickerController()
        picker.sourceType = .camera
        picker.delegate = context.coordinator
        return picker
    }

    func updateUIViewController(_ controller: UIImagePickerController, context: Context) {}

    func makeCoordinator() -> Coordinator { Coordinator(self) }

    final class Coordinator: NSObject, UIImagePickerControllerDelegate, UINavigationControllerDelegate {
        private let parent: CameraPicker

        init(_ parent: CameraPicker) { self.parent = parent }

        func imagePickerController(_ picker: UIImagePickerController, didFinishPickingMediaWithInfo info: [UIImagePickerController.InfoKey: Any]) {
            parent.onFinish(info[.originalImage] as? UIImage)
            parent.dismiss()
        }

        func imagePickerControllerDidCancel(_ picker: UIImagePickerController) {
            parent.onFinish(nil)
            parent.dismiss()
        }
    }
}
