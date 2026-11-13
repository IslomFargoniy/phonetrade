import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold transition-[color,box-shadow,background-color] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:ring-ring focus-visible:ring-[3px] aria-invalid:ring-destructive/20 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground font-bold shadow-[inset_0_1px_0_rgb(255_255_255/0.4),0_8px_20px_rgb(0_88_194/0.32)] hover:bg-primary-strong",
        destructive:
          "bg-destructive text-white font-bold shadow-[inset_0_1px_0_rgb(255_255_255/0.3),0_8px_20px_rgb(179_38_30/0.28)] hover:bg-destructive/90",
        outline:
          "border border-white/95 bg-white/62 text-ink shadow-[inset_0_1px_0_#fff,0_2px_8px_rgb(28_35_64/0.08)] hover:bg-white/85",
        secondary:
          "border border-white/95 bg-white/62 text-ink shadow-[inset_0_1px_0_#fff,0_2px_8px_rgb(28_35_64/0.08)] hover:bg-white/85",
        ghost: "text-ink-soft hover:bg-white/70",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "min-h-11 px-5 py-2 has-[>svg]:px-4",
        sm: "min-h-9 px-4 text-[13px] has-[>svg]:px-3",
        lg: "min-h-[50px] px-6 text-base has-[>svg]:px-5",
        icon: "size-11",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
