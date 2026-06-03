<script setup lang="ts">
import { Lightbulb, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = withDefaults(defineProps<{
    /** Stable key. Once dismissed, this banner won't appear again on this browser. */
    storageKey: string;
    title: string;
    text: string;
    /** Optional accent — defaults to brand indigo. */
    accent?: 'indigo' | 'emerald' | 'amber';
    /** Set true to keep showing even after dismissal (e.g. for previewing). */
    forceShow?: boolean;
}>(), {
    accent: 'indigo',
    forceShow: false,
});

const dismissed = ref(false);
// Solid (opaque) backgrounds so the tip always reads as a proper card — even when
// it overlaps a colored hero banner in a page's floating content zone.
const accentClasses: Record<string, string> = {
    indigo:  'border-indigo-200 bg-indigo-50 text-[#24327a] dark:border-indigo-800/50 dark:bg-indigo-950/60 dark:text-indigo-200',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/60 dark:text-emerald-200',
    amber:   'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/60 dark:text-amber-200',
};

const iconBgClasses: Record<string, string> = {
    indigo:  'bg-[#24327a]/10 text-[#24327a] dark:bg-indigo-900/40 dark:text-indigo-300',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300',
    amber:   'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300',
};

onMounted(() => {
    if (props.forceShow) return;
    if (typeof window === 'undefined') return;
    if (window.localStorage.getItem(`tip-dismissed:${props.storageKey}`) === '1') {
        dismissed.value = true;
    }
});

function dismiss() {
    dismissed.value = true;
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(`tip-dismissed:${props.storageKey}`, '1');
    }
}
</script>

<template>
    <div
        v-if="!dismissed"
        class="relative flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-sm"
        :class="accentClasses[accent]"
    >
        <div
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
            :class="iconBgClasses[accent]"
        >
            <Lightbulb class="h-4 w-4" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold">{{ title }}</p>
            <p class="mt-0.5 text-xs leading-relaxed opacity-80">{{ text }}</p>
        </div>
        <button
            type="button"
            class="rounded-md p-1 opacity-60 transition-opacity hover:bg-current/10 hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current/30"
            @click="dismiss"
            aria-label="Dismiss tip"
        >
            <X class="h-3.5 w-3.5" />
        </button>
    </div>
</template>
