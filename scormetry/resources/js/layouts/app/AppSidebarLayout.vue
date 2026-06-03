<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import FlashToast from '@/components/FlashToast.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="flex min-h-0 flex-1 flex-col">
                <slot />
                <footer
                    class="mt-auto border-t border-sidebar-border/60 bg-background px-6 py-4 text-center text-xs text-muted-foreground"
                >
                    All rights reserved. Copyright © 2010 - 2026 | Paragon
                    International University | Phnom Penh - Cambodia | Privacy
                    Policy
                </footer>
            </div>
        </AppContent>
    </AppShell>
    <FlashToast />
</template>
