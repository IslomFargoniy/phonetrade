/**
 * Shisha panellar ortidagi rangli dog'lar. Ular bo'lmasa blur ko'rinmaydi.
 */
export function AppBackground() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none fixed inset-0 overflow-hidden print:hidden"
        >
            <div className="absolute -top-36 -left-28 size-[620px] rounded-full bg-[#7CC4FF] opacity-55 blur-[90px]" />
            <div className="absolute top-20 -right-40 size-[560px] rounded-full bg-[#C9A7FF] opacity-50 blur-[100px]" />
            <div className="absolute -bottom-56 left-1/3 h-[520px] w-[640px] rounded-full bg-[#FFC48A] opacity-50 blur-[110px]" />
            <div className="absolute right-[18%] bottom-28 size-[300px] rounded-full bg-[#8EE6C4] opacity-45 blur-[80px]" />
        </div>
    );
}
