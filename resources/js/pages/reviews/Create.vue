<script setup lang="ts">
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Star, FileText, ClipboardCheck, MessageSquare, ChevronDown, ChevronUp, Pencil, Save, Check, Loader2, History } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import TiptapEditor from '@/components/TiptapEditor.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { show as paperShow } from '@/actions/App/Http/Controllers/PaperController';
import { feedbackHistory as teamFeedbackHistory } from '@/actions/App/Http/Controllers/TeamController';
import { store as reviewStore, create as reviewCreate } from '@/actions/App/Http/Controllers/ReviewController';
import { formatDateTimeWithAmPm } from '@/lib/utils';

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
            score_deadline_at: string | null;
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
    // The current judge's scoring responsibilities (one per assigned role) for this
    // defense session. A judge holding two roles scores and submits each separately.
    responsibilities?: Array<{
        assignment_id: number;
        committee_role: string | null;
        has_review: boolean;
        is_submitted: boolean;
        locked: boolean;
    }>;
    selectedAssignmentId?: number | null;
    selectedRole?: string | null;
    existingReview: {
        id: number;
        scores_json: Array<{ criteria: string; score: number; comment?: string }> | null;
        comment: string | null;
        locked_at: string | null;
        auto_submitted_at: string | null;
    } | null;
}>();

const responsibilities = computed(() => props.responsibilities ?? []);
// Show a role picker only when the judge holds more than one role and hasn't yet
// chosen which one to score on this visit.
const needsRoleSelection = computed(
    () => responsibilities.value.length > 1 && !props.selectedAssignmentId,
);
function roleScoringUrl(assignmentId: number): string {
    return `${reviewCreate.url(props.paper.id)}?assignment=${assignmentId}`;
}

const criteria = props.paper.defense_attempt?.period?.rubric?.structure_json
    ?? props.paper.subject.rubric?.structure_json
    ?? [];

const isLocked = props.existingReview?.locked_at != null;

const form = useForm({
    scores_json: criteria.map((c, i) => ({
        criteria: c.criteria,
        max_score: c.max_score,
        weight: c.weight,
        score: props.existingReview?.scores_json?.[i]?.score ?? 0,
        comment: props.existingReview?.scores_json?.[i]?.comment ?? '',
    })),
    comment: props.existingReview?.comment ?? '',
    submit_final: false,
    defense_attempt_reviewer_id: props.selectedAssignmentId ?? null,
});

const deadlineError = computed(() => (form.errors as Record<string, string | undefined>).deadline);
const scoredCount = computed(() => form.scores_json.filter((s) => s.score >= 1).length);
const totalCriteria = computed(() => form.scores_json.length);
const allScored = computed(() => totalCriteria.value > 0 && scoredCount.value === totalCriteria.value);
const wasAutoSubmitted = computed(() => props.existingReview?.auto_submitted_at != null);
const scoreDeadlineLabel = computed(() => {
    const deadline = props.paper.defense_attempt?.score_deadline_at;

    if (!deadline) return null;

    return formatDateTimeWithAmPm(deadline, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
});

// ── Draft + autosave ─────────────────────────────────────────────────────────
type SaveState = 'idle' | 'saving' | 'saved';
const saveState = ref<SaveState>('idle');
const savedAt = ref<string | null>(null);
let autosaveTimer: ReturnType<typeof setTimeout> | null = null;
const dirty = ref(false);

function persistDraft() {
    if (isLocked) return;
    saveState.value = 'saving';
    router.post(reviewStore.url(props.paper.id), {
        scores_json: form.scores_json,
        comment: form.comment,
        submit_final: false,
        defense_attempt_reviewer_id: props.selectedAssignmentId ?? null,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: [],
        onSuccess: () => {
            saveState.value = 'saved';
            savedAt.value = formatDateTimeWithAmPm(new Date().toISOString(), { hour: 'numeric', minute: '2-digit' }, '');
            dirty.value = false;
        },
        onError: () => { saveState.value = 'idle'; },
    });
}

function saveDraftNow() {
    if (autosaveTimer) clearTimeout(autosaveTimer);
    persistDraft();
}

// Debounced autosave whenever the judge changes a score or comment.
watch(
    () => JSON.stringify([form.scores_json, form.comment]),
    () => {
        if (isLocked) return;
        dirty.value = true;
        saveState.value = 'idle';
        if (autosaveTimer) clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(persistDraft, 1500);
    },
);

// Flush any pending draft (e.g. a comment typed within the 1.5s debounce) before
// the judge leaves — SPA navigation, tab close, or refresh. `keepalive` lets the
// request complete even as the page unloads, so the draft comes back exactly as left.
function flushDraft() {
    if (isLocked || !dirty.value) return;
    if (autosaveTimer) clearTimeout(autosaveTimer);
    dirty.value = false;
    const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
    void fetch(reviewStore.url(props.paper.id), {
        method: 'POST',
        keepalive: true,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': xsrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            scores_json: form.scores_json,
            comment: form.comment,
            submit_final: false,
            defense_attempt_reviewer_id: props.selectedAssignmentId ?? null,
        }),
    }).catch(() => {});
}

onMounted(() => window.addEventListener('beforeunload', flushDraft));
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', flushDraft);
    flushDraft();
});

function submit() {
    if (autosaveTimer) clearTimeout(autosaveTimer);
    form.submit_final = true;
    form.post(reviewStore.url(props.paper.id), {
        onFinish: () => { form.submit_final = false; },
    });
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

    <!-- Multi-role judge: choose which scoring responsibility to work on. Each role
         is scored, submitted, and locked separately. -->
    <div v-if="needsRoleSelection" class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-2xl flex-col justify-center gap-6 p-6">
        <div class="text-center">
            <Star class="mx-auto h-8 w-8 text-amber-600" />
            <h1 class="mt-3 text-xl font-bold">Choose a scoring role</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                You hold more than one scoring role for {{ paper.team.name }} &bull; {{ paper.defense_attempt?.label ?? paper.subject.title }}.
                Each role is scored and submitted separately.
            </p>
        </div>
        <div class="flex flex-col gap-3">
            <Link
                v-for="r in responsibilities"
                :key="r.assignment_id"
                :href="roleScoringUrl(r.assignment_id)"
                class="flex items-center justify-between rounded-xl border bg-card p-4 transition-colors hover:border-primary hover:bg-accent"
            >
                <div class="flex items-center gap-3">
                    <ClipboardCheck class="h-5 w-5 text-primary" />
                    <span class="font-semibold">{{ r.committee_role }}</span>
                </div>
                <Badge v-if="r.locked || r.is_submitted" variant="secondary">Submitted</Badge>
                <Badge v-else-if="r.has_review" variant="outline">Continue draft</Badge>
                <Badge v-else>Start scoring</Badge>
            </Link>
        </div>
    </div>

    <div v-else class="flex h-[calc(100vh-4rem)] flex-col">
        <!-- Locked banner -->
        <div v-if="isLocked" class="flex shrink-0 items-center gap-2 border-b bg-amber-50 px-4 py-2 text-sm text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
            <span class="font-semibold">{{ wasAutoSubmitted ? 'Review auto-submitted.' : 'Review locked.' }}</span>
            {{ wasAutoSubmitted ? 'A completed draft was submitted automatically at the score deadline.' : 'Contact the subject instructor to unlock it for editing.' }}
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
                    <span class="text-sm font-semibold">Review Student Document</span>
                    <Badge v-if="selectedRole" variant="secondary" class="ml-1">{{ selectedRole }}</Badge>
                </div>
                <span class="text-sm text-muted-foreground">&mdash; {{ paper.team.name }} &bull; {{ paper.subject.title }}</span>
            </div>
            <Button variant="outline" size="sm" as-child class="gap-1.5">
                <Link :href="teamFeedbackHistory.url(paper.team.id)">
                    <History class="h-4 w-4" />
                    Previous feedback
                </Link>
            </Button>
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
                        Student Document / Manuscript
                    </button>
                    <button
                        v-if="rubricPdfUrl"
                        class="flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-medium transition-colors"
                        :class="activePdf === 'rubric' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="activePdf = 'rubric'"
                    >
                        <ClipboardCheck class="h-3 w-3" />
                        Defense Rubric
                    </button>
                </div>
                <iframe
                    v-show="activePdf === 'paper'"
                    :src="paperPdfUrl"
                    class="w-full flex-1 border-0"
                    title="Student Document / Manuscript (PDF)"
                />
                <iframe
                    v-if="rubricPdfUrl"
                    v-show="activePdf === 'rubric'"
                    :src="rubricPdfUrl"
                    class="w-full flex-1 border-0"
                    title="Defense Rubric"
                />
            </div>

            <!-- Right: Scoring interface -->
            <div class="flex w-1/2 flex-col overflow-y-auto bg-background">
                <!-- Sticky progress + autosave status -->
                <div class="sticky top-0 z-10 -mx-5 mb-1 flex items-center justify-between border-b bg-background/95 px-5 py-2.5 backdrop-blur">
                    <div class="flex items-center gap-2.5">
                        <span class="text-xs font-semibold text-foreground">Scored {{ scoredCount }} / {{ totalCriteria }}</span>
                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-blue-500 transition-all duration-300"
                                :style="{ width: totalCriteria ? (scoredCount / totalCriteria * 100) + '%' : '0%' }"
                            />
                        </div>
                    </div>
                    <div v-if="!isLocked" class="flex items-center gap-1.5 text-xs">
                        <template v-if="saveState === 'saving'">
                            <Loader2 class="h-3.5 w-3.5 animate-spin text-muted-foreground" />
                            <span class="text-muted-foreground">Saving…</span>
                        </template>
                        <template v-else-if="saveState === 'saved'">
                            <Check class="h-3.5 w-3.5 text-emerald-600" />
                            <span class="text-emerald-600 dark:text-emerald-400">Draft saved {{ savedAt }}</span>
                        </template>
                        <span v-else-if="dirty" class="text-amber-600 dark:text-amber-400">Unsaved changes…</span>
                    </div>
                </div>

                <form id="review-form" class="flex flex-col gap-5 px-5 pb-5" @submit.prevent="submit">
                    <!-- Scoring header -->
                    <div>
                        <h2 class="flex items-center gap-2 text-sm font-semibold">
                            <ClipboardCheck class="h-4 w-4 text-indigo-600" />
                            Scoring Criteria
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Rate each criterion from 1 (Unsatisfactory) to 4 (Excellent). Your work autosaves as a draft.
                            Feedback is optional.
                        </p>
                        <p v-if="scoreDeadlineLabel" class="mt-1 text-xs text-muted-foreground">
                            Score deadline: {{ scoreDeadlineLabel }}
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
                            <div class="flex gap-2">
                                <button
                                    v-for="opt in scoreOptions"
                                    :key="opt.value"
                                    type="button"
                                    :disabled="isLocked"
                                    class="flex min-h-[3.25rem] flex-1 flex-col items-center justify-center gap-0.5 rounded-xl border px-1.5 py-2.5 text-center transition-all active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="item.score === opt.value
                                        ? `${opt.bgLight} ring-2 ring-offset-1 ring-current ${opt.textColor} font-semibold`
                                        : 'border-border bg-background text-muted-foreground hover:bg-accent'"
                                    @click="item.score = opt.value"
                                >
                                    <span class="text-lg font-bold leading-none">{{ opt.value }}</span>
                                    <span class="text-[10px] leading-tight">{{ opt.label }}</span>
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
                    <div class="flex flex-wrap items-center justify-end gap-3 border-t pt-4">
                        <Button variant="ghost" as-child>
                            <Link :href="paperShow.url(paper.id)">Cancel</Link>
                        </Button>
                        <Button
                            v-if="!isLocked"
                            type="button"
                            variant="outline"
                            class="gap-2"
                            :disabled="saveState === 'saving'"
                            @click="saveDraftNow"
                        >
                            <Save class="h-4 w-4" />
                            Save draft
                        </Button>
                        <Button type="submit" :disabled="form.processing || !allScored || isLocked" class="gap-2">
                            <Star class="h-4 w-4" />
                            {{ form.processing ? 'Submitting…' : 'Submit &amp; lock' }}
                        </Button>
                    </div>
                    <p v-if="!allScored && !isLocked" class="-mt-2 text-right text-xs text-muted-foreground">
                        Score all {{ totalCriteria }} criteria to submit. Drafts save anytime.
                    </p>
                    <p v-else-if="allScored && !isLocked" class="-mt-2 text-right text-xs text-emerald-700 dark:text-emerald-300">
                        Ready to submit. If you forget, Scormetry will auto-submit this completed draft at the score deadline.
                    </p>
                    <p v-if="deadlineError" class="-mt-2 text-right text-xs text-destructive">{{ deadlineError }}</p>
                </form>
            </div>
        </div>
    </div>
</template>
