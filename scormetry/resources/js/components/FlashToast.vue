<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Toaster, toast } from 'vue-sonner';
import { computed, watch } from 'vue';

const flash = computed(() => (usePage().props as Record<string, unknown>).flash as { success?: string; error?: string } | undefined);

watch(
    () => flash.value,
    (val) => {
        if (val?.success) {
            toast.success(val.success, {
                duration: 5000,
                closeButton: true,
            });
        }
        if (val?.error) {
            toast.error(val.error, {
                duration: 6000,
                closeButton: true,
            });
        }
    },
    { immediate: true },
);
</script>

<template>
    <Toaster
        position="top-right"
        :duration="5000"
        rich-colors
        close-button
        :expand="true"
        :toast-options="{
            style: { fontSize: '0.875rem' },
        }"
    />
</template>
