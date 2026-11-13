import { router } from '@inertiajs/react';
import { Camera, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { destroy } from '@/routes/stock/photos';
import { store } from '@/routes/stock/units/photos';

export type UnitPhoto = { id: number; url: string };

/** Bitta telefonga ko'pi bilan shuncha rasm (serverdagi chegara bilan bir xil). */
const MAX_PHOTOS = 8;

/**
 * Telefonning o'z rasmlari: ko'rish, bir nechtasini birdan qo'shish (telefonda kamera ochiladi) va o'chirish.
 */
export function UnitPhotos({
    unitId,
    photos,
    size = 'md',
}: {
    unitId: number;
    photos: UnitPhoto[];
    size?: 'sm' | 'md';
}) {
    const input = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const tile =
        size === 'sm' ? 'size-12 rounded-[12px]' : 'size-20 rounded-[16px]';
    const canAdd = photos.length < MAX_PHOTOS;

    const upload = (files: FileList | null) => {
        if (!files || files.length === 0) {
            return;
        }

        setError(null);
        router.post(
            store(unitId).url,
            {
                photos: Array.from(files).slice(0, MAX_PHOTOS - photos.length),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);

                    if (input.current) {
                        input.current.value = '';
                    }
                },
                onError: (errors) => setError(Object.values(errors)[0] ?? null),
            },
        );
    };

    const remove = (photo: UnitPhoto) =>
        router.delete(destroy(photo.id).url, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });

    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex flex-wrap gap-2">
                {photos.map((photo, index) => (
                    <div key={photo.id} className={cn('relative', tile)}>
                        <a
                            href={photo.url}
                            target="_blank"
                            rel="noreferrer"
                            className={cn(
                                'block size-full overflow-hidden border border-white/90 bg-white',
                                tile,
                            )}
                        >
                            <img
                                src={photo.url}
                                alt={t('photos.alt', { n: index + 1 })}
                                loading="lazy"
                                className="size-full object-cover"
                            />
                        </a>
                        <button
                            type="button"
                            onClick={() => remove(photo)}
                            disabled={busy}
                            aria-label={t('photos.delete', { n: index + 1 })}
                            className="absolute -top-1.5 -right-1.5 flex size-6 cursor-pointer items-center justify-center rounded-full bg-ink/80 text-white shadow"
                        >
                            <X className="size-3.5" />
                        </button>
                    </div>
                ))}

                {canAdd && (
                    <button
                        type="button"
                        onClick={() => input.current?.click()}
                        disabled={busy}
                        aria-label={t('photos.add')}
                        className={cn(
                            'flex cursor-pointer flex-col items-center justify-center gap-0.5 border border-dashed border-primary/40 bg-white/60 text-primary',
                            tile,
                        )}
                    >
                        {busy ? (
                            <Spinner />
                        ) : (
                            <Camera className="size-5" aria-hidden="true" />
                        )}
                        {size === 'md' && (
                            <span className="text-[11px] font-semibold">
                                {t('photos.add')}
                            </span>
                        )}
                    </button>
                )}
            </div>

            <input
                ref={input}
                type="file"
                accept="image/*"
                multiple
                hidden
                onChange={(event) => upload(event.target.files)}
            />

            {error && <p className="text-xs text-danger">{error}</p>}
        </div>
    );
}
