<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
    >
        <!-- Clipboard/rubric body -->
        <rect x="8" y="4" width="24" height="32" rx="3" fill="currentColor" opacity="0.15" />
        <rect x="8" y="4" width="24" height="32" rx="3" stroke="currentColor" stroke-width="2" fill="none" />
        <!-- Clipboard clip -->
        <rect x="14" y="1" width="12" height="6" rx="2" fill="currentColor" />
        <!-- Score lines -->
        <line x1="13" y1="15" x2="22" y2="15" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        <line x1="13" y1="21" x2="20" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        <line x1="13" y1="27" x2="18" y2="27" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        <!-- Checkmark circle -->
        <circle cx="28" cy="28" r="9" fill="currentColor" />
        <polyline points="23,28 26.5,31.5 33,25" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</template>
