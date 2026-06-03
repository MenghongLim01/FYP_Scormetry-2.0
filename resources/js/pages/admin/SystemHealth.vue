<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Clock, FileWarning, ShieldAlert, Users } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminClassroomsIndex } from '@/routes/admin/classrooms';
import { index as adminSystemHealthIndex } from '@/routes/admin/system-health';

type HealthItem = {
    title: string;
    meta: string;
    url: string;
};

type HealthSection = {
    key: string;
    title: string;
    description: string;
    severity: 'success' | 'warning' | 'danger';
    count: number;
    action_label: string;
    action_url: string;
    items: HealthItem[];
};

const props = defineProps<{
    summary: Record<string, number>;
    sections: HealthSection[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'System Health', href: adminSystemHealthIndex() },
        ],
    },
});

const cards = [
    { key: 'pending_users', label: 'Pending users', icon: Users },
    { key: 'pending_approvals', label: 'Join requests', icon: Clock },
    { key: 'missing_schedules', label: 'Missing schedules', icon: AlertTriangle },
    { key: 'missing_documents', label: 'Missing documents', icon: FileWarning },
    { key: 'unlocked_reviews', label: 'Open corrections', icon: ShieldAlert },
] as const;

function sectionClasses(section: HealthSection) {
    if (section.severity === 'danger') {
        return 'border-red-200 bg-red-50/60 dark:border-red-900/70 dark:bg-red-950/20';
    }

    if (section.severity === 'warning') {
        return 'border-amber-200 bg-amber-50/60 dark:border-amber-900/70 dark:bg-amber-950/20';
    }

    return 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/70 dark:bg-emerald-950/20';
}

function badgeClasses(section: HealthSection) {
    if (section.severity === 'danger') {
        return 'border-red-200 bg-red-100 text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-200';
    }

    if (section.severity === 'warning') {
        return 'border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200';
    }

    return 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200';
}
</script>

<template>
    <Head title="Admin System Health" />

    <div class="flex flex-col gap-6 p-6">
        <section class="rounded-2xl bg-gradient-to-br from-[#24327a] to-indigo-800 p-6 text-white shadow-md">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/60">Admin control center</p>
                    <h1 class="mt-2 text-2xl font-bold">System Health</h1>
                    <p class="mt-1 max-w-2xl text-sm text-white/75">
                        Find schedule gaps, missing documents, overdue reviews, release-ready results, and correction work in one place.
                    </p>
                </div>
                <Button as-child class="bg-white text-[#24327a] hover:bg-white/90">
                    <Link :href="adminClassroomsIndex()">Open Classrooms</Link>
                </Button>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <Card v-for="card in cards" :key="card.key" class="rounded-2xl shadow-sm">
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-[#24327a] dark:bg-indigo-950 dark:text-indigo-100">
                        <component :is="card.icon" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold">{{ props.summary[card.key] ?? 0 }}</p>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ card.label }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card v-for="section in sections" :key="section.key" :class="['overflow-hidden rounded-2xl shadow-sm', sectionClasses(section)]">
                <CardHeader class="border-b bg-white/70 px-5 py-4 dark:bg-slate-950/40">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <CheckCircle2 v-if="section.severity === 'success'" class="h-4 w-4 text-emerald-600" />
                                <AlertTriangle v-else-if="section.severity === 'danger'" class="h-4 w-4 text-red-600" />
                                <Clock v-else class="h-4 w-4 text-amber-600" />
                                {{ section.title }}
                            </CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">{{ section.description }}</p>
                        </div>
                        <Badge variant="outline" :class="badgeClasses(section)">
                            {{ section.count }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="section.items.length === 0" class="px-5 py-8 text-sm text-muted-foreground">
                        No current issues.
                    </div>
                    <div v-else class="divide-y divide-border/70">
                        <Link v-for="item in section.items" :key="`${section.key}-${item.title}-${item.meta}`" :href="item.url" class="block px-5 py-3 transition hover:bg-white/60 dark:hover:bg-white/5">
                            <p class="font-semibold text-foreground">{{ item.title }}</p>
                            <p class="text-sm text-muted-foreground">{{ item.meta }}</p>
                        </Link>
                    </div>
                    <div class="border-t bg-white/60 px-5 py-3 dark:bg-slate-950/40">
                        <Button as-child variant="outline" size="sm">
                            <Link :href="section.action_url">{{ section.action_label }}</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
