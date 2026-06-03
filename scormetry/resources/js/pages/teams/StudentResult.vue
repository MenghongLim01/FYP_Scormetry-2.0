<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Star, BarChart2, CheckCircle2, XCircle, Calendar } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed } from 'vue';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';

const props = defineProps<{
    team: { id: number; name: string; results_released_at: string | null };
    subject: { id: number; title: string; passing_score: number };
    paper: {
        id: number;
        final_score: number | null;
        final_score_override: number | null;
        final_score_override_reason: string | null;
        visibility_status: string;
    };
    criteriaBreakdown: Array<{
        criteria: string;
        weight: number;
        max_score: number;
        avg_score: number | null;
        weighted: number | null;
        judge_scores: Array<{ judge: string; score: number | null; comment: string | null }>;
    }>;
    judgeComments: Array<{ judge: string; comment: string }>;
}>();

// Use admin override score if set, otherwise fall back to auto-calculated score
const displayScore = computed(
    () => props.paper.final_score_override ?? props.paper.final_score,
);

const passed = computed(
    () => displayScore.value != null && displayScore.value >= props.subject.passing_score,
);

function scoreLabel(score: number): string {
    const labels: Record<number, string> = { 1: 'Unsatisfactory', 2: 'Satisfactory', 3: 'Very Satisfactory', 4: 'Excellent' };
    return labels[score] ?? String(score);
}

function scoreColor(score: number): string {
    if (score >= 4) return 'text-green-600 dark:text-green-400';
    if (score >= 3) return 'text-blue-600 dark:text-blue-400';
    if (score >= 2) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-500';
}

function avgColor(avg: number | null): string {
    if (avg === null) return 'text-muted-foreground';
    return scoreColor(Math.round(avg));
}

function formatDate(val: string | null): string {
    if (!val) return '—';
    return new Date(val).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
}
</script>

<template>
    <Head :title="`Results — ${team.name}`" />

    <div class="flex flex-col">

        <!-- Page hero panel -->
        <div class="bg-gradient-to-br from-teal-500 to-teal-600 px-6 pt-5 pb-20 text-white shadow-md">
            <!-- Back -->
            <div class="mb-4">
                <Button variant="ghost" size="sm" as-child class="gap-1 text-white/80 hover:bg-white/15 hover:text-white">
                    <Link :href="subjectShow.url(subject.id)">
                        <ArrowLeft class="h-4 w-4" />
                        Back to Subject
                    </Link>
                </Button>
            </div>

            <!-- Header -->
            <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Defense Results</h1>
                    <p class="text-sm text-white/70">{{ team.name }} · {{ subject.title }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <div v-if="displayScore != null" class="flex flex-col items-end gap-1">
                        <div
                            class="flex items-center gap-2 rounded-2xl px-5 py-2 text-base font-bold shadow-sm"
                            :class="passed ? 'bg-white text-teal-700' : 'bg-red-100 text-red-700'"
                        >
                            <component :is="passed ? CheckCircle2 : XCircle" class="h-5 w-5" />
                            {{ displayScore }} / 100 — {{ passed ? 'Pass' : 'Fail' }}
                        </div>
                        <!-- Shown when an admin has manually overridden the calculated score -->
                        <span v-if="paper.final_score_override != null"
                            class="rounded-full bg-amber-400/30 px-3 py-0.5 text-[11px] font-semibold text-amber-100 ring-1 ring-amber-300/40"
                        >
                            ✦ Score overridden by admin
                        </span>
                    </div>
                    <span v-else class="rounded-full bg-white/20 px-4 py-1.5 text-sm font-semibold text-white/90">Score pending</span>
                </div>
            </div>

            <!-- Release info + pass threshold -->
            <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-white/70">
                <span v-if="team.results_released_at" class="flex items-center gap-1">
                    <Calendar class="h-3.5 w-3.5" />
                    Released {{ formatDate(team.results_released_at) }}
                </span>
                <span>Passing score: <strong class="text-white/90">{{ subject.passing_score }}%</strong></span>
            </div>
        </div>

        <!-- Floating content -->
        <div class="relative z-10 -mt-12 flex flex-col gap-5 px-6 pb-6">

        <!-- Admin override notice card -->
        <div
            v-if="paper.final_score_override != null"
            class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 shadow-md dark:border-amber-800 dark:bg-amber-950/30"
        >
            <Star class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <div class="text-sm">
                <p class="font-semibold text-amber-800 dark:text-amber-300">Score overridden by administrator</p>
                <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">
                    The displayed score ({{ paper.final_score_override }}/100) was set manually by an administrator
                    and supersedes the reviewer-calculated score
                    <template v-if="paper.final_score != null">({{ paper.final_score }}/100)</template>.
                </p>
                <p v-if="paper.final_score_override_reason" class="mt-1.5 text-xs italic text-amber-700 dark:text-amber-400">
                    Reason: "{{ paper.final_score_override_reason }}"
                </p>
            </div>
        </div>

        <!-- Criteria breakdown -->
        <Card v-if="criteriaBreakdown.length > 0" class="rounded-2xl shadow-md overflow-hidden">
            <CardHeader class="border-b px-6 py-4">
                <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                    <BarChart2 class="h-4 w-4 text-indigo-600" />
                    Score Breakdown by Criterion
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-for="c in criteriaBreakdown" :key="c.criteria" class="border-b px-6 py-4 last:border-0">
                    <!-- Criterion header -->
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <p class="font-medium">{{ c.criteria }}</p>
                            <p class="text-xs text-muted-foreground">Weight: {{ c.weight }}% · Max: {{ c.max_score }}</p>
                        </div>
                        <div class="text-right">
                            <p :class="['text-lg font-bold', avgColor(c.avg_score)]">
                                {{ c.avg_score !== null ? c.avg_score.toFixed(1) : '—' }}
                                <span class="text-sm font-normal text-muted-foreground">/ {{ c.max_score }}</span>
                            </p>
                            <p v-if="c.weighted !== null" class="text-xs text-muted-foreground">
                                Weighted: {{ c.weighted.toFixed(1) }} pts
                            </p>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div v-if="c.avg_score !== null" class="mb-3 h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-indigo-500 transition-all"
                            :style="{ width: `${(c.avg_score / c.max_score) * 100}%` }"
                        />
                    </div>

                    <!-- Per-judge scores -->
                    <div v-if="c.judge_scores.length > 0" class="flex flex-wrap gap-2">
                        <div
                            v-for="(js, i) in c.judge_scores"
                            :key="i"
                            class="flex flex-col gap-0.5 rounded-lg border bg-muted/30 px-3 py-2 text-xs"
                        >
                            <span class="text-muted-foreground">{{ js.judge }}</span>
                            <span v-if="js.score !== null" :class="['font-semibold', scoreColor(js.score)]">
                                {{ js.score }} — {{ scoreLabel(js.score) }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                            <span v-if="js.comment" class="mt-0.5 text-muted-foreground italic">{{ js.comment }}</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div v-else class="rounded-2xl border bg-card p-10 text-center text-sm text-muted-foreground shadow-md">
            No rubric criteria defined for this subject.
        </div>

        <!-- Overall judge comments -->
        <div v-if="judgeComments.length > 0" class="flex flex-col gap-3">
            <h2 class="flex items-center gap-2 text-sm font-semibold">
                <CheckCircle2 class="h-4 w-4 text-indigo-600" />
                Judge Overall Comments
            </h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <Card v-for="(c, i) in judgeComments" :key="i">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-semibold text-muted-foreground">{{ c.judge }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="prose prose-sm dark:prose-invert max-w-none" v-html="c.comment" />
                    </CardContent>
                </Card>
            </div>
        </div>

        </div><!-- /floating content -->
    </div>
</template>
