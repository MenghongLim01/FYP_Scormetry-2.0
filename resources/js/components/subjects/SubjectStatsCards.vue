<script setup lang="ts">
import { BarChart3, FileText, ShieldCheck, Users } from 'lucide-vue-next';

defineProps<{
    stats: { students: number; reviewers: number; papers: number; reviewed: number };
    hasInviteCodes: boolean;
}>();
</script>

<template>
    <!-- Stats cards -->
    <div
        class="grid grid-cols-2 gap-2"
        :class="hasInviteCodes ? '' : 'lg:grid-cols-4'"
    >
        <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 border-l-4 border-l-blue-500 bg-white px-3 py-2 shadow-sm dark:border-slate-800 dark:border-l-blue-500 dark:bg-background">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950/40">
                <Users class="h-4 w-4 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <p class="stat-num text-lg font-bold leading-none text-blue-600 dark:text-blue-400">{{ stats.students }}</p>
                <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Students</p>
            </div>
        </div>
        <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 border-l-4 border-l-violet-500 bg-white px-3 py-2 shadow-sm dark:border-slate-800 dark:border-l-violet-500 dark:bg-background">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-950/40">
                <ShieldCheck class="h-4 w-4 text-violet-600 dark:text-violet-400" />
            </div>
            <div>
                <p class="stat-num text-lg font-bold leading-none text-violet-600 dark:text-violet-400">{{ stats.reviewers }}</p>
                <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Scoring roles</p>
            </div>
        </div>
        <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 border-l-4 border-l-indigo-500 bg-white px-3 py-2 shadow-sm dark:border-slate-800 dark:border-l-indigo-500 dark:bg-background">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/40">
                <FileText class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
            </div>
            <div>
                <p class="stat-num text-lg font-bold leading-none text-indigo-600 dark:text-indigo-400">{{ stats.papers }}</p>
                <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Documents</p>
            </div>
        </div>
        <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 border-l-4 border-l-emerald-500 bg-white px-3 py-2 shadow-sm dark:border-slate-800 dark:border-l-emerald-500 dark:bg-background">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40">
                <BarChart3 class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <p class="stat-num text-lg font-bold leading-none text-emerald-600 dark:text-emerald-400">{{ stats.reviewed }}%</p>
                <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Reviewed</p>
            </div>
        </div>
    </div>
</template>
