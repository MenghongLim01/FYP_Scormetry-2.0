<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BarChart2, Users, Calendar, Star, Lock, Send, CheckCircle2, AlertTriangle, FileText } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { computed, ref } from 'vue';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { releaseScores as teamReleaseScores } from '@/actions/App/Http/Controllers/TeamController';
import { show as paperShow } from '@/actions/App/Http/Controllers/PaperController';

const props = defineProps<{
    team: {
        id: number;
        name: string;
        defense_date: string | null;
        defense_time: string | null;
        defense_room: string | null;
        score_deadline_at: string | null;
        results_released_at: string | null;
    };
    subject: { id: number; title: string; passing_score: number };
    paper: { id: number; final_score: number | null; visibility_status: string } | null;
    criteria: Array<{ criteria: string; max_score: number; weight: number }>;
    assignedJudges: Array<{ id: number; name: string }>;
    reviews: Array<{
        id: number;
        reviewer: { id: number; name: string };
        scores_json: Array<{ criteria: string; score: number; comment?: string }> | null;
        comment: string | null;
        is_submitted: boolean;
        locked_at: string | null;
    }>;
    isOwnerOrAdmin: boolean;
}>();

const submittedReviews = computed(() => props.reviews.filter((r) => r.is_submitted));
const missingJudges = computed(() =>
    props.assignedJudges.filter((j) => !props.reviews.some((r) => r.reviewer.id === j.id && r.is_submitted)),
);

function scoreForCriteria(review: (typeof props.reviews)[number], criteriaName: string): number | null {
    if (!review.scores_json) return null;
    const s = review.scores_json.find((s) => s.criteria === criteriaName);
    return s?.score ?? null;
}

function commentForCriteria(review: (typeof props.reviews)[number], criteriaName: string): string {
    if (!review.scores_json) return '';
    const s = review.scores_json.find((s) => s.criteria === criteriaName);
    return s?.comment ?? '';
}

function avgForCriteria(criteriaName: string): number | null {
    const scores = submittedReviews.value
        .map((r) => scoreForCriteria(r, criteriaName))
        .filter((s): s is number => s !== null);
    if (scores.length === 0) return null;
    return scores.reduce((a, b) => a + b, 0) / scores.length;
}

function isOutlier(score: number | null, criteriaName: string): boolean {
    if (score === null || submittedReviews.value.length < 3) return false;
    const avg = avgForCriteria(criteriaName);
    if (avg === null) return false;
    return Math.abs(score - avg) >= 1.5;
}

function scoreColor(score: number): string {
    if (score >= 4) return 'text-green-600 dark:text-green-400';
    if (score >= 3) return 'text-blue-600 dark:text-blue-400';
    if (score >= 2) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-500 dark:text-red-400';
}

function scoreLabel(score: number): string {
    const labels: Record<number, string> = { 1: 'U', 2: 'S', 3: 'VS', 4: 'E' };
    return labels[score] ?? String(score);
}

function formatDate(val: string | null): string {
    if (!val) return '—';
    return new Date(val).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(val: string | null): string {
    if (!val) return '—';
    return new Date(val).toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const allSubmitted = computed(
    () => props.assignedJudges.length > 0 && missingJudges.value.length === 0,
);

const releaseConfirm = ref(false);
function releaseScores() {
    router.post(teamReleaseScores.url(props.team.id), {}, { preserveScroll: true });
    releaseConfirm.value = false;
}
</script>

<template>
    <Head :title="`Scores — ${team.name}`" />

    <div class="flex flex-col">

        <!-- Page hero panel -->
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 px-6 pt-5 pb-20 text-white shadow-md">
            <!-- Back -->
            <div class="mb-4 flex items-center gap-3">
                <Button variant="ghost" size="sm" as-child class="gap-1 text-white/80 hover:bg-white/15 hover:text-white">
                    <Link :href="subjectShow.url(subject.id)">
                        <ArrowLeft class="h-4 w-4" />
                        Back to Subject
                    </Link>
                </Button>
            </div>

            <!-- Header -->
            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <BarChart2 class="h-6 w-6 text-white/80" />
                        {{ team.name }} — Scores
                    </h1>
                    <p class="text-sm text-white/70">{{ subject.title }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="paper" variant="outline" size="sm" class="gap-1.5 bg-white/15 text-white border-white/30 hover:bg-white/25" as-child>
                        <Link :href="paperShow.url(paper.id)">
                            <FileText class="h-3.5 w-3.5" />
                            View Paper
                        </Link>
                    </Button>
                    <template v-if="isOwnerOrAdmin && paper && !team.results_released_at">
                        <Button v-if="!releaseConfirm" size="sm" class="gap-1.5 bg-white/20 text-white hover:bg-white/30 border-0" @click="releaseConfirm = true">
                            <Send class="h-3.5 w-3.5" />
                            Release Results
                        </Button>
                        <div v-else class="flex items-center gap-2 rounded-lg border border-white/30 bg-white/15 px-3 py-1.5">
                            <span class="text-xs text-white/90">
                                <AlertTriangle class="mr-1 inline h-3.5 w-3.5" />
                                {{ !allSubmitted ? 'Not all scores in. ' : '' }}Release?
                            </span>
                            <Button size="sm" class="h-6 bg-white text-indigo-700 text-xs hover:bg-white/90" @click="releaseScores">Yes</Button>
                            <Button size="sm" variant="ghost" class="h-6 text-white/80 text-xs hover:bg-white/15" @click="releaseConfirm = false">No</Button>
                        </div>
                    </template>
                    <Badge v-else-if="team.results_released_at" variant="default" class="bg-white/20 text-white border-0">Released {{ formatDate(team.results_released_at) }}</Badge>
                </div>
            </div>
        </div>

        <!-- Floating content -->
        <div class="relative z-10 -mt-12 flex flex-col gap-5 px-6 pb-6">

        <!-- Info row -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card class="rounded-2xl border-blue-100 shadow-md dark:border-blue-900">
                <CardContent class="flex items-center gap-3 p-4">
                    <Calendar class="h-5 w-5 text-blue-600" />
                    <div>
                        <p class="text-sm font-semibold">{{ formatDate(team.defense_date) }}</p>
                        <p class="text-xs text-muted-foreground">{{ team.defense_time ?? '' }} {{ team.defense_room ? `· ${team.defense_room}` : '' }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card class="rounded-2xl border-indigo-100 shadow-md dark:border-indigo-900">
                <CardContent class="flex items-center gap-3 p-4">
                    <Users class="h-5 w-5 text-indigo-600" />
                    <div>
                        <p class="text-sm font-semibold">{{ submittedReviews.length }} / {{ assignedJudges.length }}</p>
                        <p class="text-xs text-muted-foreground">Scores submitted</p>
                    </div>
                </CardContent>
            </Card>
            <Card class="rounded-2xl border-emerald-100 shadow-md dark:border-emerald-900">
                <CardContent class="flex items-center gap-3 p-4">
                    <Star class="h-5 w-5 text-emerald-600" />
                    <div>
                        <p class="text-sm font-semibold">
                            <span v-if="paper?.final_score != null">{{ paper.final_score }} / 100</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Final score
                            <span v-if="paper?.final_score != null && paper.final_score >= subject.passing_score" class="text-emerald-600">(Pass)</span>
                            <span v-else-if="paper?.final_score != null" class="text-destructive">(Fail)</span>
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card class="rounded-2xl border-violet-100 shadow-md dark:border-violet-900">
                <CardContent class="flex items-center gap-3 p-4">
                    <Lock class="h-5 w-5 text-violet-600" />
                    <div>
                        <p class="text-sm font-semibold">{{ formatDateTime(team.score_deadline_at) }}</p>
                        <p class="text-xs text-muted-foreground">Score deadline</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Missing judges warning -->
        <div v-if="missingJudges.length > 0" class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
            <AlertTriangle class="h-4 w-4 shrink-0" />
            <span>
                Missing scores from:
                <strong>{{ missingJudges.map((j) => j.name).join(', ') }}</strong>
            </span>
        </div>

        <!-- No criteria -->
        <div v-if="criteria.length === 0" class="rounded-lg border p-10 text-center text-sm text-muted-foreground">
            No rubric criteria defined for this subject.
        </div>

        <!-- Comparison table -->
        <Card v-else-if="submittedReviews.length > 0">
            <CardHeader class="border-b px-6 py-4">
                <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                    <BarChart2 class="h-4 w-4 text-indigo-600" />
                    Score Breakdown by Criterion
                    <span class="ml-1 text-xs font-normal text-muted-foreground">U=Unsatisfactory S=Satisfactory VS=Very Satisfactory E=Excellent</span>
                </CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-primary text-left text-xs font-semibold uppercase tracking-wider text-primary-foreground">
                            <th class="px-6 py-3">Criterion</th>
                            <th class="px-4 py-3 text-center">Wt</th>
                            <th
                                v-for="r in submittedReviews"
                                :key="r.id"
                                class="px-4 py-3 text-center"
                            >
                                {{ r.reviewer.name.split(' ')[0] }}
                            </th>
                            <th class="px-4 py-3 text-center">Avg</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="c in criteria"
                            :key="c.criteria"
                            class="hover:bg-muted/50"
                        >
                            <td class="px-6 py-3 font-medium">
                                {{ c.criteria }}
                                <span class="ml-1 text-xs text-muted-foreground">/ {{ c.max_score }}</span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-muted-foreground">{{ c.weight }}%</td>
                            <td
                                v-for="r in submittedReviews"
                                :key="r.id"
                                class="px-4 py-3 text-center"
                            >
                                <span
                                    :class="[
                                        'rounded px-2 py-0.5 text-xs font-bold',
                                        scoreColor(scoreForCriteria(r, c.criteria) ?? 0),
                                        isOutlier(scoreForCriteria(r, c.criteria), c.criteria) ? 'ring-2 ring-amber-400' : '',
                                    ]"
                                    :title="isOutlier(scoreForCriteria(r, c.criteria), c.criteria) ? 'Possible outlier' : ''"
                                >
                                    <template v-if="scoreForCriteria(r, c.criteria) !== null">
                                        {{ scoreLabel(scoreForCriteria(r, c.criteria)!) }}
                                    </template>
                                    <span v-else class="text-muted-foreground">—</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="avgForCriteria(c.criteria) !== null" :class="['text-xs font-semibold', scoreColor(Math.round(avgForCriteria(c.criteria)!))]">
                                    {{ avgForCriteria(c.criteria)!.toFixed(1) }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-else-if="criteria.length > 0" class="rounded-lg border p-10 text-center text-sm text-muted-foreground">
            No scores submitted yet.
        </div>

        <!-- Per-judge comments -->
        <div v-if="submittedReviews.some((r) => r.comment)" class="flex flex-col gap-3">
            <h2 class="flex items-center gap-2 text-sm font-semibold">
                <CheckCircle2 class="h-4 w-4 text-indigo-600" />
                Judge Comments
            </h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <Card v-for="r in submittedReviews.filter((r) => r.comment)" :key="'cmt-' + r.id">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-xs font-semibold text-muted-foreground">{{ r.reviewer.name }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="prose prose-sm dark:prose-invert max-w-none" v-html="r.comment" />
                    </CardContent>
                </Card>
            </div>
        </div>

        </div><!-- /floating content -->
    </div>
</template>
