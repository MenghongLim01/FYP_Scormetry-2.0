<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Star } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { show as paperShow } from '@/actions/App/Http/Controllers/PaperController';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';

defineProps<{
    review: {
        id: number;
        is_submitted: boolean;
        scores_json: Array<{ criteria: string; score: number }> | null;
        comment: string | null;
        reviewer: { id: number; name: string };
        paper: {
            id: number;
            team: { id: number; name: string; members: Array<{ id: number; name: string }> };
            subject: { id: number; title: string };
        };
    };
}>();

function scoreColor(score: number): string {
    if (score >= 4) return 'text-green-600';
    if (score >= 3) return 'text-blue-600';
    if (score >= 2) return 'text-amber-600';
    return 'text-red-500';
}

function scoreBgColor(score: number): string {
    if (score >= 4) return 'bg-green-500';
    if (score >= 3) return 'bg-blue-500';
    if (score >= 2) return 'bg-amber-500';
    return 'bg-red-500';
}

function scoreLabel(score: number): string {
    const labels: Record<number, string> = { 1: 'Unsatisfactory', 2: 'Satisfactory', 3: 'Very Satisfactory', 4: 'Excellent' };
    return labels[score] ?? String(score);
}
</script>

<template>
    <Head title="Review Details" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="paperShow.url(review.paper.id)">
                    <ArrowLeft class="h-4 w-4" />
                    Back to Paper
                </Link>
            </Button>
        </div>

        <Card class="mx-auto w-full max-w-2xl">
            <CardHeader class="border-b">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                            <Star class="h-5 w-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base">Review by {{ review.reviewer.name }}</CardTitle>
                            <p class="text-sm text-muted-foreground">
                                {{ review.paper.team.name }}
                                &bull;
                                <Link :href="subjectShow.url(review.paper.subject.id)" class="hover:underline">{{ review.paper.subject.title }}</Link>
                            </p>
                        </div>
                    </div>
                    <Badge :variant="review.is_submitted ? 'default' : 'outline'">
                        {{ review.is_submitted ? 'Submitted' : 'Draft' }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="review.scores_json && review.scores_json.length > 0">
                    <div v-for="(score, index) in review.scores_json" :key="index" class="border-b px-6 py-4 last:border-0">
                        <div class="mb-2 flex items-center justify-between text-sm">
                            <span class="font-medium">{{ score.criteria }}</span>
                            <span :class="['font-semibold', scoreColor(score.score)]">
                                {{ score.score }} / 4 &mdash; {{ scoreLabel(score.score) }}
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full transition-all" :class="scoreBgColor(score.score)" :style="{ width: `${(score.score / 4) * 100}%` }" />
                        </div>
                    </div>
                </div>
                <div v-else class="px-6 py-10 text-center text-sm text-muted-foreground">
                    No scores recorded yet.
                </div>

                <!-- Review Comment -->
                <div v-if="review.comment" class="border-t px-6 py-4">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Reviewer Comment</h3>
                    <div class="prose prose-sm dark:prose-invert max-w-none" v-html="review.comment" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
