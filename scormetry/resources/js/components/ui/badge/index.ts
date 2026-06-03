import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

export const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        // Primary Blue — active, scheduled, submitted, auto-join
        default:
          "border-transparent bg-primary text-white [a&]:hover:bg-primary/90",
        // Soft blue secondary — neutral information
        secondary:
          "border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90",
        // Red — overdue, error, rejected, danger
        destructive:
          "border-transparent bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300 [a&]:hover:bg-red-200 dark:[a&]:hover:bg-red-900",
        // Gray outline — draft, not started, neutral
        outline:
          "border-border text-muted-foreground bg-muted/50 [a&]:hover:bg-muted",
        // Green — completed, released, passed, locked
        success:
          "border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300",
        // Orange — pending, waiting, approval required, attention needed
        warning:
          "border-transparent bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

export type BadgeVariants = VariantProps<typeof badgeVariants>
