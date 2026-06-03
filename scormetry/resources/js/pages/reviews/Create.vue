<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft, Star, FileText, ClipboardCheck, MessageSquare, ChevronDown, ChevronUp, Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import TiptapEditor from '@/components/TiptapEditor.vue';
import { computed, ref } from 'vue';
import { show as paperShow } from '@/actions/App/Http/Controllers/PaperController';
import { store as reviewStore } from '@/actions/App/Http/Controllers/ReviewController';

const props = defineProps<{
    paper: {
        id: number;
        team: { id: number; name: string; members: Array<{ id: number; name: string }> };
        subject: {
            id: number;
            title: string;
            rubric: {
                id: number;
                structure_json: Array<{ criteria: string; max_score: number; weight: number }> | null;
            } | null;
        };
        defense_attempt?: {
            id: number;
            label: string;
            period?: {
                id: number;
                name: string;
                rubric?: {
                    id: number;
                    structure_json: Array<{ criteria: string; max_score: number; weight: number }> | null;
                } | null;
            } | null;
        } | null;
        reviews: Array<{
            id: number;
            is_submitted: boolean;
            scores_json: Array<{ criteria: string; score: number; comment?: string }> | null;
            reviewer: { id: number; name: string };
        }>;
    };
    paperPdfUrl: string;
    rubricPdfUrl: string | null;
    existingReview: {
        id: number;
        scores_json: Array<{ criteria: string; score: number; comment?: string }> | null;
        comment: string | null;
        locked_at: string | null;
    } | null;
}>();

const criteria = props.paper.defense_attempt?.period?.rubric?.structure_json
    ?? props.paper.subject.rubric?.structure_json
    ?? [];

const isLocked = props.existingReview?.locked_at != null;

const form = useForm({
    scores_json: criteria.map((c, i) => ({
        criteria: c.criteria,
        score: props.existingReview?.scores_json?.[i]?.score ?? 0,
        comment: props.existingReview?.scores_json?.[i]?.comment ?? '',
    })),
    comment: props.existingReview?.comment ?? '',
});

const allScored = computed(() => form.scores_json.every((s) => s.score >= 1));

function submit() {
    form.post(reviewStore.url(props.paper.id));
}

const scoreOptions = [
    { value: 1, label: 'Unsatisfactory', textColor: 'text-red-700 dark:text-red-400', bgLight: 'bg-red-50 dark:bg-red-950/30 border-red-200 dark:border-red-900' },
    { value: 2, label: 'Satisfactory', textColor: 'text-amber-700 dark:text-amber-400', bgLight: 'bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900' },
    { value: 3, label: 'Very Satisfactory', textColor: 'text-blue-700 dark:text-blue-400', bgLight: 'bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-900' },
    { value: 4, label: 'Excellent', textColor: 'text-green-700 dark:text-green-400', bgLight: 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-900' },
];

function getScoreOption(score: number) {
    return scoreOptions.find((o) => o.value === score);
}

const otherReviews = computed(() =>
    props.paper.reviews.filter((r) => r.is_submitted && r.scores_json),
);

const showOtherReviews = ref(false);

type PdfView = 'paper' | 'rubric';
const activePdf = ref<PdfView>('paper');
</script>

<template>
    <Head title="Submit Review" />

    <div class="flex h-[calc(100vh-4rem)] flex-col">
        <!-- Locked banner -->
        <div v-if="isLocked" class="flex shrink-0 items-center gap-2 border-b bg-amber-50 px-4 py-2 text-sm text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
            <span class="font-semibold">Review locked.</span> Contact the subject instructor to unlock it for editing.
        </div>

        <!-- Top bar -->
        <div class="flex shrink-0 items-center justify-between border-b bg-card px-4 py-2.5">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="sm" as-child class="gap-1">
                    <Link :href="paperShow.url(paper.id)">
                        <ArrowLeft class="h-4 w-4" />
                        Back
                    </Link>
                </Button>
                <Separator orientation="vertical" class="h-5" />
                <div class="flex items-center gap-2">
                    <Star class="h-4 w-4 text-amber-600" />
                    <span class="text-sm font-semibold">Review Paper</span>
                </div>
                <span class="text-sm text-muted-foreground">&mdash; {{ paper.team.name }} &bull; {{ paper.subject.title }}</span>
            </div>
        </div>

        <!-- Split screen -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Left: PDF viewer -->
            <div class="flex w-1/2 flex-col border-r">
                <div class="flex items-center gap-1 border-b bg-muted/30 px-3 py-1.5">
                    <button
                        class="flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-medium transition-colors"
                        :class="activePdf === 'paper' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="activePdf = 'paper'"
                    >
                        <FileText class="h-3 w-3" />
                        Paper PDF
                    </button>
                    <button
                        v-if="rubricPdfUrl"
                        class="flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-medium transition-colors"
                        :class="activePdf === 'rubric' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="activePdf = 'rubric'"
                    >
                        <ClipboardCheck class="h-3 w-3" />
                        Rubric PDF
                    </button>
                </div>
                <iframe
                    v-show="activePdf === 'paper'"
                    :src="paperPdfUrl"
                    class="w-full flex-1 border-0"
                    title="Student Paper PDF"
                />
                <iframe
                    v-if="rubricPdfUrl"
                    v-show="activePdf === 'rubric'"
                    :src="rubricPdfUrl"
                    class="w-full flex-1 border-0"
                    title="Rubric PDF"
                />
            </div>

            <!-- Right: Scoring interface -->
            <div class="flex w-1/2 flex-col overflow-y-auto bg-background">
                <form id="review-form" class="flex flex-col gap-5 p-5" @submit.prevent="submit">
                    <!-- Scoring header -->
                    <div>
                        <h2 class="flex items-center gap-2 text-sm font-semibold">
                            <ClipboardCheck class="h-4 w-4 text-indigo-600" />
                            Scoring Criteria
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Rate each criterion from 1 (Unsatisfactory) to 4 (Excellent)
                        </p>
                    </div>

                    <!-- No criteria warning -->
                    <div v-if="criteria.length === 0" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                        No rubric criteria available. The rubric must be locked before reviewing.
                    </div>

                    <!-- Criteria scoring cards -->
                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="(item, index) in form.scores_json"
                            :key="index"
                            class="rounded-lg border p-3.5 transition-colors"
                            :class="item.score >= 1 ? (getScoreOption(item.score)?.bgLight ?? '') : ''"
                        >
                            <div class="mb-2.5 flex items-center justify-between">
                                <span class="text-sm font-semibold">{{ item.criteria }}</span>
                                <div class="flex items-center gap-1.5">
                                    <Badge variant="outline" class="text-xs">{{ criteria[index]?.weight }}%</Badge>
                                    <Badge
                                        v-if="item.score >= 1"
                                        :class="['border-0 text-xs', getScoreOption(item.score)?.textColor]"
                                        variant="outline"
                                    >
                                        {{ getScoreOption(item.score)?.label }}
                                    </Badge>
                                </div>
                            </div>
                            <div class="flex gap-1.5">
                                <button
                                    v-for="opt in scoreOptions"
                                    :key="opt.value"
                                    type="button"
                                    :disabled="isLocked"
                                    class="flex flex-1 flex-col items-center gap-0.5 rounded-lg border px-1.5 py-2 text-center transition-all disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="item.score === opt.value
                                        ? `${opt.bgLight} ring-2 ring-offset-1 ring-current ${opt.textColor} font-semibold`
                                        : 'border-border bg-background text-muted-foreground hover:bg-accent'"
                                    @click="item.score = opt.value"
                                >
                                    <span class="text-base font-bold">{{ opt.value }}</span>
                                    <span class="text-[9px] leading-tight">{{ opt.label }}</span>
                                </button>
                            </div>
                            <div class="mt-2">
                                <label class="mb-1 flex items-center gap-1 text-xs text-muted-foreground">
                                    <Pencil class="h-3 w-3" />
                                    Comment for this criterion (optional)
                                </label>
                                <textarea
                                    v-model="item.comment"
                                    :disabled="isLocked"
                                    placeholder="Brief notes on this criterion…"
                                    rows="2"
                                    class="w-full resize-none rounded-md border bg-background px-3 py-1.5 text-xs ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                />
                            </div>
                        </div>
                    </div>

                    <p v-if="form.errors.scores_json" class="text-xs text-destructive">{{ form.errors.scores_json }}</p>

                    <!-- Comment section with Tiptap -->
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <MessageSquare class="h-4 w-4 text-blue-600" />
                            <h2 class="text-sm font-semibold">Review Comment</h2>
                            <span class="text-xs text-muted-foreground">(optional)</span>
                        </div>
                        <TiptapEditor v-model="form.comment" :readonly="isLocked" placeholder="Provide detailed feedback for the team…" />
                        <p v-if="form.errors.comment" class="text-xs text-destructive">{{ form.errors.comment }}</p>
                    </div>

                    <!-- Other reviewers' scores (collapsible) -->
                    <div v-if="otherReviews.length > 0" class="flex flex-col gap-2">
                        <button
                            type="button"
                            class="flex items-center gap-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                            @click="showOtherReviews = !showOtherReviews"
                        >
                            <component :is="showOtherReviews ? ChevronUp : ChevronDown" class="h-3.5 w-3.5" />
                            Other Reviews ({{ otherReviews.length }})
                        </button>
                        <div v-if="showOtherReviews" class="flex flex-col gap-3">
                            <Card v-for="review in otherReviews" :key="review.id" class="bg-muted/20">
                                <CardHeader class="px-4 py-2.5 pb-0">
                                    <CardTitle class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                                        <div class="flex h-5 w-5 items-center justify-center rounded-full bg-amber-100 text-[10px] font-bold text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                            {{ review.reviewer.name.charAt(0).toUpperCase() }}
                                        </div>
                                        {{ review.reviewer.name }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="px-4 py-2.5">
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            v-for="(score, idx) in review.scores_json"
                                            :key="idx"
                                            variant="outline"
                                            class="gap-1 text-xs"
                                        >
                                            {{ score.criteria }}: {{ score.score }}/4
                                        </Badge>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <!-- Submit footer -->
                    <div class="flex justify-end gap-3 border-t pt-4">
                        <Button variant="outline" as-child>
                            <Link :href="paperShow.url(paper.id)">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing || !allScored || isLocked" class="gap-2">
                            <Star class="h-4 w-4" />
                            {{ form.processing ? 'Submitting...' : existingReview ? 'Update Review' : 'Submit Review' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
