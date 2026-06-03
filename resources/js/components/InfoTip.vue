<script setup lang="ts">
import { HelpCircle } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

withDefaults(defineProps<{
    text: string;
    size?: 'sm' | 'md';
    side?: 'top' | 'right' | 'bottom' | 'left';
}>(), {
    size: 'sm',
    side: 'top',
});
</script>

<template>
    <TooltipProvider :delay-duration="200">
        <Tooltip>
            <TooltipTrigger as-child>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center rounded-full text-muted-foreground/70 transition-colors hover:text-[#24327a] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#24327a]/30 dark:hover:text-indigo-300"
                    :class="size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'"
                    aria-label="More info"
                >
                    <HelpCircle :class="size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'" />
                </button>
            </TooltipTrigger>
            <TooltipContent
                :side="side"
                class="max-w-xs whitespace-pre-line rounded-lg border bg-slate-900 px-3 py-2 text-xs font-normal leading-relaxed text-slate-50 shadow-lg dark:bg-slate-800"
            >
                {{ text }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
