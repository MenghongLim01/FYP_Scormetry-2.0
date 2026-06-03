<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BarChart3, BookOpen, FileText, Users, Download } from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { dashboard as adminDashboard } from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Reports', href: '#' },
        ],
    },
});

defineProps<{
    counts: {
        subjects: number;
        teams: number;
        papers: number;
        scoredPapers: number;
    };
}>();

// CSV endpoints (named routes — Wayfinder doesn't expose admin.reports.* yet so we
// hit the URLs directly. They open in the same tab and trigger a download.)
const exports = [
    {
        key: 'scores',
        href: '/admin/reports/scores.csv',
        title: 'Scores',
        description: 'One row per submitted document with final, override, pass/fail, judge, members, and defense session details.',
        icon: BarChart3,
        accent: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
    },
    {
        key: 'subjects',
        href: '/admin/reports/subjects.csv',
        title: 'Subjects',
        description: 'Every subject with teacher, codes, passing score, and counts of students / reviewers / teams / documents.',
        icon: BookOpen,
        accent: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400',
    },
    {
        key: 'teams',
        href: '/admin/reports/teams.csv',
        title: 'Teams',
        description: 'Every team with subject, members, defense schedule and results-released status.',
        icon: Users,
        accent: 'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-400',
    },
];
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-col">
        <!-- Hero -->
        <div class="bg-gradient-to-br from-indigo-700 to-indigo-800 px-6 pt-6 pb-20 text-white shadow-md">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                    <BarChart3 class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Reports & Exports</h1>
                    <p class="text-sm text-white/70">CSV exports for grade sharing, accreditation, or external archival.</p>
                </div>
            </div>
        </div>

        <!-- Stats summary -->
        <div class="relative z-10 -mt-12 px-6">
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 border-l-4 border-l-indigo-500 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:border-l-indigo-500 dark:bg-background">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/40">
                        <BookOpen class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <p class="text-xl font-bold leading-none text-indigo-600 dark:text-indigo-400">{{ counts.subjects }}</p>
                        <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Subjects</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 border-l-4 border-l-violet-500 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:border-l-violet-500 dark:bg-background">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-950/40">
                        <Users class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div>
                        <p class="text-xl font-bold leading-none text-violet-600 dark:text-violet-400">{{ counts.teams }}</p>
                        <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Teams</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 border-l-4 border-l-amber-500 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:border-l-amber-500 dark:bg-background">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-950/40">
                        <FileText class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-xl font-bold leading-none text-amber-600 dark:text-amber-400">{{ counts.papers }}</p>
                        <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Documents</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-slate-200 border-l-4 border-l-emerald-500 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:border-l-emerald-500 dark:bg-background">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40">
                        <BarChart3 class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-xl font-bold leading-none text-emerald-600 dark:text-emerald-400">{{ counts.scoredPapers }}</p>
                        <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Scored</p>
                    </div>
                </div>
            </div>

            <!-- Export cards -->
            <div class="mt-6 grid gap-4 lg:grid-cols-3">
                <Card v-for="exp in exports" :key="exp.key" class="overflow-hidden">
                    <CardHeader class="flex flex-row items-start gap-3 border-b pb-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg" :class="exp.accent">
                            <component :is="exp.icon" class="h-5 w-5" />
                        </div>
                        <div class="flex-1">
                            <CardTitle class="text-base">{{ exp.title }}</CardTitle>
                            <p class="mt-1 text-sm text-muted-foreground">{{ exp.description }}</p>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-4">
                        <Button as-child class="w-full gap-2 bg-[#24327a] text-white hover:bg-[#1b255c]">
                            <a :href="exp.href" download>
                                <Download class="h-4 w-4" />
                                Download CSV
                            </a>
                        </Button>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Includes a UTF-8 BOM so Excel opens it cleanly. Open with Numbers, Excel, or Google Sheets.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
