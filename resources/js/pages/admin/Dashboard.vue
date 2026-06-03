<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, BookOpen, CheckCircle2, Clock, FileText, LockOpen, ShieldAlert, Users } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';
import { reviews as adminAuditReviews } from '@/routes/admin/audit';
import { index as adminClassroomsIndex } from '@/routes/admin/classrooms';
import { index as adminReportsIndex } from '@/routes/admin/reports';
import { edit as adminSettingsEdit } from '@/routes/admin/settings';
import { index as adminSystemHealthIndex } from '@/routes/admin/system-health';
import { index as adminUsersIndex } from '@/routes/admin/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Dashboard', href: adminDashboard() },
        ],
    },
});

const props = defineProps<{
    stats: {
        total_classrooms: number;
        total_users: number;
        total_submissions: number;
        pending_approvals: number;
        missing_schedules: number;
        missing_documents: number;
        overdue_reviews: number;
        ready_to_release: number;
        unlocked_reviews: number;
        pending_users: number;
    };
}>();

const cards = [
    { key: 'total_classrooms', label: 'Total Classrooms', icon: BookOpen, color: 'text-indigo-700 bg-indigo-50 dark:text-white dark:bg-indigo-950/60' },
    { key: 'total_users', label: 'Total Users', icon: Users, color: 'text-indigo-700 bg-indigo-50 dark:text-white dark:bg-indigo-950/60' },
    { key: 'total_submissions', label: 'Total Submissions', icon: FileText, color: 'text-teal-700 bg-teal-50 dark:text-white dark:bg-teal-950/60' },
    { key: 'pending_approvals', label: 'Pending Approvals', icon: Clock, color: 'text-amber-700 bg-amber-50 dark:text-white dark:bg-amber-950/60' },
] as const;

const controlLinks = [
    { title: 'System Health', description: 'Find schedule gaps, missing PDFs, overdue reviews, and release-ready results.', href: adminSystemHealthIndex(), icon: ShieldAlert },
    { title: 'Classroom Control', description: 'Open any subject to transfer owner, add users, override scores, or unlock reviews.', href: adminClassroomsIndex(), icon: BookOpen },
    { title: 'Users', description: 'Approve, block, unblock, delete, or change user roles.', href: adminUsersIndex(), icon: Users },
    { title: 'Reports', description: 'Export score, subject, and team CSV reports for checking and backup.', href: adminReportsIndex(), icon: FileText },
    { title: 'Audit Logs', description: 'Review unlock history and correction decisions.', href: adminAuditReviews(), icon: Clock },
    { title: 'Settings', description: 'Control system-wide admin settings.', href: adminSettingsEdit(), icon: ShieldAlert },
] as const;

const issueCards = [
    { key: 'missing_schedules', label: 'Missing schedules', icon: AlertTriangle, tone: 'text-amber-700 bg-amber-50 dark:text-amber-200 dark:bg-amber-950/40' },
    { key: 'missing_documents', label: 'Missing documents', icon: AlertTriangle, tone: 'text-amber-700 bg-amber-50 dark:text-amber-200 dark:bg-amber-950/40' },
    { key: 'overdue_reviews', label: 'Overdue reviews', icon: ShieldAlert, tone: 'text-red-700 bg-red-50 dark:text-red-200 dark:bg-red-950/40' },
    { key: 'ready_to_release', label: 'Ready to release', icon: CheckCircle2, tone: 'text-emerald-700 bg-emerald-50 dark:text-emerald-200 dark:bg-emerald-950/40' },
    { key: 'unlocked_reviews', label: 'Open corrections', icon: LockOpen, tone: 'text-indigo-700 bg-indigo-50 dark:text-indigo-200 dark:bg-indigo-950/40' },
    { key: 'pending_users', label: 'Pending users', icon: Users, tone: 'text-amber-700 bg-amber-50 dark:text-amber-200 dark:bg-amber-950/40' },
] as const;
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex flex-col gap-6 p-6">
        <section class="rounded-2xl bg-gradient-to-br from-indigo-700 to-[#24327a] p-6 text-white shadow-md">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-white/65">Admin control center</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight">Scormetry Admin Panel</h1>
                    <p class="mt-2 max-w-3xl text-sm text-white/75">
                        Recover classroom problems, inspect system health, manage users, and correct academic records with audit history.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button as-child class="border-0 bg-white text-[#24327a] hover:bg-white/90">
                        <Link :href="adminSystemHealthIndex()">
                            System Health
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                    <Button as-child variant="outline" class="border-white/30 bg-white/10 text-white hover:bg-white/20">
                        <Link :href="adminClassroomsIndex()">
                            Classrooms
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="card in cards" :key="card.key" class="rounded-2xl shadow-sm transition-shadow hover:shadow-md">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground">{{ card.label }}</CardTitle>
                    <div :class="['flex h-9 w-9 items-center justify-center rounded-lg', card.color]">
                        <component :is="card.icon" class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-3xl font-bold text-[#24327a] dark:text-white">{{ props.stats[card.key] }}</div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
            <Card class="rounded-2xl shadow-sm">
                <CardHeader class="border-b">
                    <CardTitle class="text-base">Admin recovery controls</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Use these when a classroom owner cannot fix a real defense problem by themselves.
                    </p>
                </CardHeader>
                <CardContent class="grid gap-3 p-5 md:grid-cols-2">
                    <Link
                        v-for="link in controlLinks"
                        :key="link.title"
                        :href="link.href"
                        class="group rounded-2xl border bg-card p-4 shadow-sm transition hover:border-[#24327a]/40 hover:shadow-md"
                    >
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-[#24327a] dark:bg-indigo-950/50 dark:text-indigo-200">
                                <component :is="link.icon" class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="font-semibold text-foreground group-hover:text-[#24327a] dark:group-hover:text-indigo-200">{{ link.title }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ link.description }}</p>
                            </div>
                        </div>
                    </Link>
                </CardContent>
            </Card>

            <Card class="rounded-2xl shadow-sm">
                <CardHeader class="border-b">
                    <CardTitle class="text-base">Needs admin attention</CardTitle>
                    <p class="text-sm text-muted-foreground">Open system health to see the exact classroom, team, and next action.</p>
                </CardHeader>
                <CardContent class="space-y-3 p-5">
                    <Link
                        v-for="item in issueCards"
                        :key="item.key"
                        :href="adminSystemHealthIndex()"
                        class="flex items-center justify-between gap-3 rounded-xl border p-3 transition hover:bg-muted/50"
                    >
                        <div class="flex items-center gap-3">
                            <div :class="['flex h-9 w-9 items-center justify-center rounded-lg', item.tone]">
                                <component :is="item.icon" class="h-4 w-4" />
                            </div>
                            <span class="text-sm font-semibold">{{ item.label }}</span>
                        </div>
                        <span class="text-lg font-bold text-[#24327a] dark:text-white">{{ props.stats[item.key] }}</span>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
