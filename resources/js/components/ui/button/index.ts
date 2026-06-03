import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        // Deep Navy with a subtle top-down gradient sheen → lighter on hover
        default:
          "bg-primary bg-gradient-to-b from-[hsl(228,56%,33%)] to-primary text-primary-foreground shadow-sm hover:from-[hsl(228,60%,38%)] hover:to-[hsl(228,60%,32%)] active:from-primary active:to-[hsl(228,60%,22%)]",
        // Red for delete / danger
        destructive:
          "bg-destructive text-white shadow-sm hover:bg-red-700 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/70",
        // Neutral outlined
        outline:
          "border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50",
        // Soft navy secondary
        secondary:
          "bg-secondary text-secondary-foreground shadow-xs hover:bg-[hsl(228,50%,88%)] dark:hover:bg-secondary/70",
        // Ghost — for icon buttons / toolbar actions
        ghost:
          "hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50",
        // Text link
        link: "text-primary underline-offset-4 hover:underline",
        // Orange — warning actions (use sparingly)
        warning:
          "bg-orange-500 text-white shadow-sm hover:bg-orange-600 focus-visible:ring-orange-500/30",
      },
      size: {
        "default":  "h-9 px-4 py-2 has-[>svg]:px-3",
        "sm":       "h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5",
        "lg":       "h-10 rounded-md px-6 has-[>svg]:px-4",
        "icon":     "size-9",
        "icon-sm":  "size-8",
        "icon-lg":  "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size:    "default",
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
