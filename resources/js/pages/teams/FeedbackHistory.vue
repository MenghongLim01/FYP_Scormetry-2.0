<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, History, MessageSquare, Star } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type ScoreEntry = { criteria: string; score: number | null; comment: string | null };
type ReviewEntry = {
    id: number;
    reviewer: string;
    role: string;
    comment: string | null;
    scores: ScoreEntry[];
};
type StageEntry = {
    id: number;
    stage: string;
    defense_date: string | null;
    reviews: ReviewEntry[];
};

defineProps<{
    team: { id: number; name: string };
    history: StageEntry[];
}>();

function scoreColor(score: number | null): string {
    if (score === null) return 'text-muted-foreground';
    if (score >= 4) return 'text-green-600';
    if (score >= 3) return 'text-blue-600';
    if (score >= 2) return 'text-amber-600';
    return 'text-red-500';
}

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head :title="`Feedback History — ${team.name}`" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" class="gap-1" @click="goBack">
                <ArrowLeft class="h-4 w-4" />
                Back
            </Button>
        </div>

        <div class="mx-auto w-full max-w-3xl">
            <div class="mb-2 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                    <History class="h-5 w-5" />
                </div>
                <div>
                    <h1 class="text-lg font-semibold">Feedback History</h1>
                    <p class="text-sm text-muted-foreground">{{ team.name }} — all submitted feedback across defense sessions</p>
                </div>
            </div>

            <!-- Empty state -->
            <Card v-if="history.length === 0" class="mt-4">
                <CardContent class="flex flex-col items-center gap-2 py-12 text-center">
                    <MessageSquare class="h-8 w-8 text-muted-foreground" />
                    <p class="text-sm font-medium">No submitted feedback yet</p>
                    <p class="text-xs text-muted-foreground">Feedback from previous defense sessions will appear here once judges submit their reviews.</p>
                </CardContent>
            </Card>

            <!-- Stages -->
            <div v-for="stage in history" :key="stage.id" class="mt-6">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">{{ stage.stage }}</h2>
                    <span v-if="stage.defense_date" class="text-xs text-muted-foreground">{{ stage.defense_date }}</span>
                </div>

                <div class="flex flex-col gap-4">
                    <Card v-for="review in stage.reviews" :key="review.id">
                        <CardHeader class="pb-3">
                            <div class="flex items-center justify-between gap-3">
                                <CardTitle class="text-base">{{ review.reviewer }}</CardTitle>
                                <Badge variant="secondary">{{ review.role }}</Badge>
                            </div>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-3">
                            <div v-for="(entry, i) in review.scores" :key="i" class="border-b pb-2 last:border-b-0 last:pb-0">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm text-foreground/90">{{ entry.criteria }}</p>
                                    <span class="flex shrink-0 items-center gap-1 text-sm font-semibold" :class="scoreColor(entry.score)">
                                        <Star class="h-3.5 w-3.5" />
                                        {{ entry.score ?? '—' }}
                                    </span>
                                </div>
                                <div v-if="entry.comment" class="prose prose-sm dark:prose-invert mt-1 max-w-none text-muted-foreground" v-html="entry.comment" />
                            </div>

                            <div v-if="review.comment" class="rounded-lg border bg-muted/30 px-3 py-2">
                                <p class="mb-1 text-xs font-medium text-muted-foreground">Reviewer comment</p>
                                <div class="prose prose-sm dark:prose-invert max-w-none" v-html="review.comment" />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
