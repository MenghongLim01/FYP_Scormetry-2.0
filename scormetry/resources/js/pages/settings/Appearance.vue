<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Palette } from 'lucide-vue-next';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="space-y-8">
        <!-- Page header -->
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Palette class="h-6 w-6" />
            </div>
            <div>
                <h2 class="text-xl font-semibold text-foreground">Appearance</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Choose how Scormetry looks to you. Select a theme or let it follow your system settings.
                </p>
            </div>
        </div>

        <!-- Divider -->
        <div class="border-t border-border" />

        <!-- Theme picker section -->
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-medium text-foreground">Interface theme</h3>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    This setting affects all pages and panels across the application.
                </p>
            </div>
            <AppearanceTabs />
        </div>

        <!-- Info note -->
        <p class="rounded-lg border border-border bg-muted/40 px-4 py-3 text-xs text-muted-foreground">
            <span class="font-medium text-foreground">Tip:</span>
            Your choice is remembered on this device. Sign in on another device to set it there.
        </p>
    </div>
</template>
