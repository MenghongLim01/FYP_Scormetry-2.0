<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Star, Users, Globe, ClipboardCheck, BookOpen, Presentation } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger, DialogClose } from '@/components/ui/dialog';
import { useAuth } from '@/composables/useAuth';
import { update as adminOverrideScore } from '@/actions/App/Http/Controllers/Admin/PaperScoreController';
import { overrideScore as teacherOverrideScore } from '@/actions/App/Http/Controllers/DefenseAttemptController';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { create as rubricCreate } from '@/actions/App/Http/Controllers/RubricController';
import { create as reviewCreate, show as reviewShow } from '@/actions/App/Http/Controllers/ReviewController';
import { publish as paperPublish, turnIn as paperTurnIn, unsubmit as paperUnsubmit, removeSubmission as paperRemove } from '@/actions/App/Http/Controllers/PaperController';
import { create as paperCreate } from '@/actions/App/Http/Controllers/PaperController';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { CheckCircle2, Clock, Upload, RefreshCw, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    paper: {
        id: number;
        file_path: string;
        final_score: number | string | null;
        final_score_override: number | string | null;
        final_score_override_reason: string | null;
        final_score_override_by: number | null;
        visibility_status: string;
        turned_in_at: string | null;
        team: {
            id: number;
            name: string;
            members: Array<{ id: number; name: string }>;
            student_members?: Array<{ id: number; name: string }>;
            review_panel?: Array<{ id: number; name: string; role_label?: string }>;
        };
        subject: {
            id: number;
            title: string;
            teacher_id: number;
            passing_score: number;
            rubric: {
                id: number;
                status: string;
                structure_json: Array<{ criteria: string; max_score: number; weight: number }> | null;
            } | null;
        };
        reviews: Array<{
            id: number;
            is_submitted: boolean;
            auto_submitted_at: string | null;
            scores_json: Array<{ criteria: string; score: number; comment?: string | null }> | null;
            comment: string | null;
            reviewer: { id: number; name: string };
        }>;
        defense_attempt?: {
            id: number;
            active_reviewer_assignments?: Array<{ id: number; reviewer_id: number }>;
            paper_upload_deadline_at?: string | null;
            results_released_at?: string | null;
            period?: {
                rubric?: {
                    structure_json: Array<{ criteria: string; max_score: number; weight: number }> | null;
                } | null;
            } | null;
        } | null;
    };
    paperPdfUrl: string;
    slidesPdfUrl: string | null;
    rubricPdfUrl: string | null;
}>();

const { isTeacherOrAdmin, isAdmin, user } = useAuth();

const isSubjectOwner = computed(() => props.paper.subject && user.value?.id === props.paper.subject.teacher_id);

// Both the admin and the room-owner teacher may override the final score — even
// after results are released. Judges and students never see this panel.
const canOverrideScore = computed(() => isAdmin.value || isSubjectOwner.value);

const effectiveFinalScoreRaw = computed(() =>
    props.paper.final_score_override !== null ? props.paper.final_score_override : props.paper.final_score,
);

const effectiveFinalScoreNumber = computed(() => {
    const raw = effectiveFinalScoreRaw.value;
    if (raw === null || raw === undefined) return null;

    return Number(raw);
});

const hasReviewed = computed(() =>
    props.paper.reviews.some((r) => r.reviewer.id === user.value?.id),
);

const canReview = computed(() =>
    isTeacherOrAdmin.value && !hasReviewed.value,
);

// The current user's own review (draft or submitted) for this paper, if any.
const myReview = computed(() => props.paper.reviews.find((r) => r.reviewer.id === user.value?.id));
// An assigned judge (or the teacher/admin) can score. Show a resume/submit link
// whenever their own review still isn't submitted — so a saved draft can always
// be reopened and finished, not stranded on the read-only view.
const isAssignedJudge = computed(() =>
    (props.paper.defense_attempt?.active_reviewer_assignments ?? []).some((a) => a.reviewer_id === user.value?.id),
);
const canScore = computed(() => isTeacherOrAdmin.value || isAssignedJudge.value);
const myReviewSubmitted = computed(() => myReview.value?.is_submitted === true);

type TabKey = 'rubric' | 'paper' | 'slides' | 'scoring';
// Open on the student's uploaded manuscript first — that's what they (and judges)
// want to see; the rubric and slides are one click away.
const activeTab = ref<TabKey>('paper');

const tabs = computed<Array<{ key: TabKey; label: string; icon: typeof FileText }>>(() => [
    { key: 'rubric', label: 'Defense Rubric', icon: ClipboardCheck },
    { key: 'paper', label: 'Student Document / Manuscript (PDF)', icon: FileText },
    ...(props.slidesPdfUrl ? [{ key: 'slides' as TabKey, label: 'Presentation Slides (PDF)', icon: Presentation }] : []),
    { key: 'scoring', label: 'Dynamic Rubric', icon: BookOpen },
]);

const canPublish = computed(() =>
    effectiveFinalScoreNumber.value !== null
    && props.paper.visibility_status !== 'published'
    && effectiveFinalScoreNumber.value >= props.paper.subject.passing_score,
);

const submittedReviewCount = computed(() =>
    props.paper.reviews.filter((r) => r.is_submitted).length,
);

// The rubric for this document = the defense period's rubric (Midterm/Final),
// falling back to a subject-level rubric. Matches how scoring resolves it.
const rubricStructure = computed(() =>
    props.paper.defense_attempt?.period?.rubric?.structure_json
    ?? props.paper.subject.rubric?.structure_json
    ?? [],
);

// Total expected reviews = number of reviewers assigned to this team's defense
// attempt (falls back to existing review rows when there's no attempt). This is
// the denominator for "X/Y submitted" so 5 assigned reviewers shows "1/5".
const reviewTotalCount = computed(() =>
    Math.max(
        props.paper.defense_attempt?.active_reviewer_assignments?.length ?? 0,
        props.paper.reviews.length,
    ),
);

// Results can only be released once EVERY assigned judge has submitted their score.
const allReviewsSubmitted = computed(() =>
    reviewTotalCount.value > 0 && submittedReviewCount.value >= reviewTotalCount.value,
);

function publishPaper() {
    if (!allReviewsSubmitted.value) return;
    router.post(paperPublish.url(props.paper.id));
}

// --- Google-Classroom-style turn-in (student team members) ---
const isTurnedIn = computed(() => props.paper.turned_in_at !== null);
const isStudentTeamMember = computed(() =>
    !isTeacherOrAdmin.value && props.paper.team.members.some((m) => m.id === user.value?.id),
);
// Anyone who can reach this page may preview an attached document — including the
// team's draft that isn't turned in yet. The status banner above flags drafts so a
// judge/teacher knows it isn't the final, turned-in version.
const hasDocument = computed(() => !!props.paper.file_path);
const canViewDocument = computed(() => hasDocument.value);
const uploadClosed = computed(() => {
    if (props.paper.defense_attempt?.results_released_at) return true;
    const deadline = props.paper.defense_attempt?.paper_upload_deadline_at;
    if (!deadline) return false;
    return new Date(deadline) < new Date();
});

const turnInConfirmOpen = ref(false);
const unsubmitConfirmOpen = ref(false);
const removeConfirmOpen = ref(false);

function confirmTurnIn() {
    router.post(paperTurnIn.url(props.paper.id), {}, { preserveScroll: true, onFinish: () => { turnInConfirmOpen.value = false; } });
}
function confirmUnsubmit() {
    router.post(paperUnsubmit.url(props.paper.id), {}, { preserveScroll: true, onFinish: () => { unsubmitConfirmOpen.value = false; } });
}
function confirmRemove() {
    router.delete(paperRemove.url(props.paper.id), { onFinish: () => { removeConfirmOpen.value = false; } });
}

const overrideForm = useForm({
    override_score: effectiveFinalScoreNumber.value ?? 0,
    override_note: '',
});

const overrideConfirmOpen = ref(false);

const overrideConfirmDescription = computed(() => {
    const from = effectiveFinalScoreRaw.value !== null ? `${effectiveFinalScoreRaw.value}` : '—';
    const to = overrideForm.override_score;

    return `This will set ${props.paper.team.name}'s final score from ${from} to ${to} / 100`
        + (props.paper.visibility_status === 'published' ? ', and the released result will update accordingly.' : '.')
        + ' The change is logged for auditing and can be re-overridden, but cannot be undone automatically.';
});

// Clicking "Override Score" opens a confirmation first — it never submits directly.
function requestOverride() {
    overrideConfirmOpen.value = true;
}

function confirmOverride() {
    // Admin overrides the paper directly; the room-owner teacher overrides via
    // the defense attempt (which authorizes the subject owner).
    const target = isAdmin.value || !props.paper.defense_attempt
        ? adminOverrideScore.url(props.paper.id)
        : teacherOverrideScore.url(props.paper.defense_attempt.id);

    overrideForm.patch(target, {
        preserveScroll: true,
        onSuccess: () => overrideForm.reset('override_note'),
        onFinish: () => { overrideConfirmOpen.value = false; },
    });
}

function statusBadgeVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'published') return 'default';
    if (status === 'submitted') return 'secondary';
    return 'outline';
}

function statusLabel(status: string): string {
    if (status === 'published') return 'Review Completed';
    return status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function reviewStatusLabel(review: { is_submitted: boolean; auto_submitted_at: string | null }): string {
    if (review.auto_submitted_at) return 'Auto-submitted';
    return review.is_submitted ? 'Submitted' : 'Draft';
}

function reviewStatusVariant(review: { is_submitted: boolean; auto_submitted_at: string | null }): 'default' | 'outline' | 'warning' {
    if (review.auto_submitted_at) return 'warning';
    return review.is_submitted ? 'default' : 'outline';
}

function scoreColor(score: number): string {
    if (score >= 4) return 'text-green-600';
    if (score >= 3) return 'text-blue-600';
    if (score >= 2) return 'text-amber-600';
    return 'text-red-500';
}

function scoreLabel(score: number): string {
    const labels: Record<number, string> = { 1: 'Unsatisfactory', 2: 'Satisfactory', 3: 'Very Satisfactory', 4: 'Excellent' };
    return labels[score] ?? String(score);
}

const studentMemberNames = computed(() => {
    const members = props.paper.team.student_members ?? props.paper.team.members;

    return members.map((member) => member.name).join(', ') || 'No student members listed';
});
</script>

<template>
    <Head :title="`Document — ${paper.team.name}`" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="subjectShow.url(paper.subject.id)">
                    <ArrowLeft class="h-4 w-4" />
                    Back to {{ paper.subject.title }}
                </Link>
            </Button>
        </div>

        <!-- Student turn-in panel (Google-Classroom style) -->
        <div
            v-if="isStudentTeamMember"
            class="rounded-xl border-2 p-4"
            :class="isTurnedIn ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20'"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <component :is="isTurnedIn ? CheckCircle2 : Clock" class="h-6 w-6" :class="isTurnedIn ? 'text-emerald-600' : 'text-amber-600'" />
                    <div>
                        <p class="text-sm font-semibold">
                            {{ isTurnedIn ? 'Turned in' : 'Attached — not turned in yet' }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="isTurnedIn">Your judges can now review this document. You can unsubmit to make changes before the deadline.</template>
                            <template v-else-if="uploadClosed">The upload window is closed — contact your instructor if you need to submit.</template>
                            <template v-else>Review the file below, then click <strong>Turn in</strong> to submit it to your judges.</template>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Not turned in: Turn in / Replace / Remove -->
                    <template v-if="!isTurnedIn">
                        <Button v-if="!uploadClosed" size="sm" class="gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]" @click="turnInConfirmOpen = true">
                            <Upload class="h-4 w-4" />
                            Turn in
                        </Button>
                        <Button v-if="!uploadClosed" size="sm" variant="outline" class="gap-1.5" as-child>
                            <Link :href="paperCreate.url(paper.subject.id, { query: { defense_attempt_id: paper.defense_attempt?.id } })">
                                <RefreshCw class="h-3.5 w-3.5" />
                                Replace file
                            </Link>
                        </Button>
                        <Button v-if="!uploadClosed" size="sm" variant="ghost" class="gap-1.5 text-destructive hover:bg-destructive/10" @click="removeConfirmOpen = true">
                            <Trash2 class="h-3.5 w-3.5" />
                            Remove
                        </Button>
                    </template>
                    <!-- Turned in: Unsubmit -->
                    <template v-else>
                        <Button v-if="!uploadClosed" size="sm" variant="outline" class="gap-1.5" @click="unsubmitConfirmOpen = true">
                            <RefreshCw class="h-3.5 w-3.5" />
                            Unsubmit
                        </Button>
                        <Badge v-else variant="outline" class="border-slate-200 bg-slate-50 text-slate-600">Locked (deadline passed)</Badge>
                    </template>
                </div>
            </div>
        </div>

        <!-- Judge / teacher status banner: previewing the student's document -->
        <div
            v-else-if="canScore && hasDocument"
            class="rounded-xl border-2 p-4"
            :class="isTurnedIn ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900 dark:bg-emerald-950/20' : 'border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20'"
        >
            <div class="flex items-center gap-3">
                <component :is="isTurnedIn ? CheckCircle2 : Clock" class="h-6 w-6" :class="isTurnedIn ? 'text-emerald-600' : 'text-amber-600'" />
                <div>
                    <p class="text-sm font-semibold">
                        {{ isTurnedIn ? 'Turned in — ready to review' : 'Draft — not turned in yet' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="isTurnedIn">The student has turned in this document for review.</template>
                        <template v-else>The student has attached this document but hasn't turned it in yet. You're previewing their working copy — it may still change before it's final.</template>
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border bg-card p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                        <FileText class="h-6 w-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-bold tracking-tight">{{ paper.team.name }}</h1>
                            <Badge :variant="statusBadgeVariant(paper.visibility_status)">
                                {{ statusLabel(paper.visibility_status) }}
                            </Badge>
                        </div>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            <Link :href="subjectShow.url(paper.subject.id)" class="hover:underline">{{ paper.subject.title }}</Link>
                            &bull; Passing score: {{ paper.subject.passing_score }}%
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            <Users class="inline h-3 w-3 mr-1" />{{ studentMemberNames }}
                        </p>
                    </div>
                </div>

	                <div class="flex flex-col items-end gap-2">
	                    <div class="text-right">
	                        <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Final Score</p>
                            <div class="flex items-center justify-end gap-2">
                                <p class="text-3xl font-bold" :class="effectiveFinalScoreNumber !== null && effectiveFinalScoreNumber >= paper.subject.passing_score ? 'text-green-600' : 'text-foreground'">
                                    {{ effectiveFinalScoreRaw !== null ? `${effectiveFinalScoreRaw} / 100` : '—' }}
                                </p>
                                <Badge
                                    v-if="paper.final_score_override !== null"
                                    variant="outline"
                                    class="border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300"
                                >
                                    Overridden
                                </Badge>
                            </div>
	                    </div>
	                    <Button v-if="canPublish && isSubjectOwner" size="sm" class="gap-2" as-child>
	                        <Dialog>
	                            <DialogTrigger as-child>
	                                <Button size="sm" class="gap-2">
                                    <Globe class="h-4 w-4" />
                                    Mark Review Completed
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Mark Review Completed</DialogTitle>
                                    <DialogDescription>
                                        This will make the paper and its scores visible to all team members. This action cannot be undone.
                                    </DialogDescription>
	                                </DialogHeader>
	                                <div class="rounded-lg bg-muted/50 px-4 py-3 text-sm">
	                                    <p><span class="font-medium">Team:</span> {{ paper.team.name }}</p>
	                                    <p><span class="font-medium">Final Score:</span> {{ effectiveFinalScoreRaw }} / 100</p>
	                                    <p><span class="font-medium">Scoring roles:</span> {{ submittedReviewCount }}/{{ reviewTotalCount }} submitted</p>
	                                </div>
	                                <p v-if="!allReviewsSubmitted" class="rounded-lg bg-amber-50 px-4 py-2.5 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">
	                                    All assigned judges must submit their scoring before results can be released ({{ submittedReviewCount }}/{{ reviewTotalCount }} so far).
	                                </p>
	                                <DialogFooter>
	                                    <DialogClose as-child>
                                        <Button variant="outline">Cancel</Button>
                                    </DialogClose>
                                    <Button class="gap-2" :disabled="!allReviewsSubmitted" @click="publishPaper">
                                        <Globe class="h-4 w-4" />
                                        Mark Completed
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
	                    </Button>
	                </div>
	            </div>

	            <div v-if="effectiveFinalScoreNumber !== null && effectiveFinalScoreNumber < paper.subject.passing_score && paper.visibility_status !== 'published'" class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:bg-amber-950 dark:text-amber-300">
	                Final score {{ effectiveFinalScoreRaw }} / 100 is below the passing score of {{ paper.subject.passing_score }}%. Cannot mark review completed.
	            </div>

                <div v-if="canOverrideScore" class="mt-4 rounded-xl border bg-muted/30 px-4 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold">Score Override</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Override the final score for this paper. A reason is required for auditing.
                            </p>
                        </div>
                        <Badge
                            v-if="paper.final_score_override !== null"
                            variant="outline"
                            class="border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300"
                        >
                            Overridden
                        </Badge>
                    </div>

                    <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="requestOverride">
                        <div class="flex flex-col gap-1.5">
                            <Label for="override_score">Override score (0 - 100)</Label>
                            <Input
                                id="override_score"
                                v-model.number="overrideForm.override_score"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                            />
                            <p v-if="overrideForm.errors.override_score" class="text-xs text-destructive">
                                {{ overrideForm.errors.override_score }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5 sm:col-span-2">
                            <Label for="override_note">Reason for override</Label>
                            <textarea
                                id="override_note"
                                v-model="overrideForm.override_note"
                                rows="3"
                                placeholder="Explain why you are overriding this score..."
                                class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex min-h-[90px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px]"
                            />
                            <p v-if="overrideForm.errors.override_note" class="text-xs text-destructive">
                                {{ overrideForm.errors.override_note }}
                            </p>
                        </div>

                        <div class="sm:col-span-2 flex justify-end">
                            <Button type="submit" :disabled="overrideForm.processing">
                                {{ overrideForm.processing ? 'Saving...' : 'Override Score' }}
                            </Button>
                        </div>
                    </form>

                    <p v-if="paper.final_score_override_reason" class="mt-3 text-xs text-muted-foreground">
                        Current override reason:
                        <span class="font-medium text-foreground">{{ paper.final_score_override_reason }}</span>
                    </p>
                </div>
	        </div>

        <div class="rounded-xl border bg-card shadow-sm overflow-hidden">
            <div class="flex border-b bg-muted/30">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition-colors focus:outline-none"
                    :class="activeTab === tab.key
                        ? 'border-b-2 border-primary text-primary bg-background'
                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/50'"
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ tab.label }}
                </button>
            </div>

            <div v-show="activeTab === 'rubric'" class="p-0">
                <div v-if="rubricPdfUrl" class="flex flex-col">
                    <iframe
                        :src="rubricPdfUrl"
                        class="h-[70vh] w-full border-0"
                        title="Defense Rubric"
                    />
                </div>
                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                    <ClipboardCheck class="mb-3 h-9 w-9 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">No defense rubric available.</p>
                    <Button v-if="isTeacherOrAdmin" size="sm" class="mt-3" as-child>
                        <Link :href="rubricCreate.url(paper.subject.id)">Upload Defense Rubric</Link>
                    </Button>
                </div>
            </div>

            <div v-show="activeTab === 'paper'" class="p-0">
                <iframe
                    v-if="canViewDocument"
                    :src="paperPdfUrl"
                    class="h-[70vh] w-full border-0"
                    title="Student Document / Manuscript (PDF)"
                />
                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                    <Clock class="mb-3 h-9 w-9 text-muted-foreground/40" />
                    <p class="text-sm font-medium text-foreground">No document uploaded yet</p>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">The student hasn't uploaded a document for this defense yet.</p>
                </div>
            </div>

            <div v-if="slidesPdfUrl" v-show="activeTab === 'slides'" class="p-0">
                <iframe
                    :src="slidesPdfUrl"
                    class="h-[70vh] w-full border-0"
                    title="Presentation Slides (PDF)"
                />
            </div>

            <div v-show="activeTab === 'scoring'" class="p-6">
                <div v-if="rubricStructure.length > 0">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold">Dynamic Rubric Criteria</h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                AI converts the Defense Rubric file only. Judges manually enter scores and feedback; the system calculates the total.
                            </p>
                        </div>
                        <Button v-if="canReview" size="sm" as-child>
                            <Link :href="reviewCreate.url(paper.id)">
                                <Star class="mr-1.5 h-3.5 w-3.5" />
                                Add Review
                            </Link>
                        </Button>
                    </div>
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border bg-primary text-left text-xs font-semibold uppercase tracking-wider text-primary-foreground">
                                    <th class="px-4 py-2.5">Criteria</th>
                                    <th class="px-4 py-2.5 w-20 text-center">Weight</th>
                                    <th class="px-4 py-2.5 w-24 text-center">Max Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(item, index) in rubricStructure" :key="index" class="transition-colors hover:bg-muted/70">
                                    <td class="px-4 py-3 font-medium">{{ item.criteria }}</td>
                                    <td class="px-4 py-3 text-center text-muted-foreground">{{ item.weight }}%</td>
                                    <td class="px-4 py-3 text-center">{{ item.max_score }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                    <BookOpen class="mb-3 h-9 w-9 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">No AI rubric structure available yet. Upload and approve a rubric to generate it.</p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">
                Scoring role feedback
                <span class="ml-1.5 text-sm font-normal text-muted-foreground">({{ submittedReviewCount }}/{{ reviewTotalCount }} scoring roles submitted)</span>
            </h2>
            <Button v-if="(canScore || myReview) && !myReviewSubmitted" size="sm" as-child class="shrink-0 gap-2">
                <Link :href="reviewCreate.url(paper.id)">
                    <Star class="h-3.5 w-3.5" />
                    {{ myReview ? 'Continue / submit your review' : 'Start scoring' }}
                </Link>
            </Button>
        </div>

        <div v-if="paper.reviews.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center">
            <Star class="mb-3 h-8 w-8 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">No reviews yet.</p>
            <Button v-if="canReview" size="sm" class="mt-3" as-child>
                <Link :href="reviewCreate.url(paper.id)" class="gap-2">
                    <Star class="h-3.5 w-3.5" />
                    Add First Review
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <Link
                v-for="review in paper.reviews"
                :key="review.id"
                :href="review.reviewer.id === user?.id && !review.is_submitted ? reviewCreate.url(paper.id) : reviewShow.url(review.id)"
                class="block transition-shadow hover:shadow-md"
            >
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                    {{ review.reviewer.name.charAt(0).toUpperCase() }}
                                </div>
                                {{ review.reviewer.name }}
                            </CardTitle>
                            <Badge :variant="reviewStatusVariant(review)">
                                {{ reviewStatusLabel(review) }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="review.scores_json" class="flex flex-col gap-2.5">
                            <div
                                v-for="(score, index) in review.scores_json"
                                :key="index"
                                class="flex flex-col gap-1"
                            >
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-muted-foreground">{{ score.criteria }}</span>
                                    <span :class="['font-semibold', scoreColor(score.score)]">
                                        {{ score.score }}/4
                                    </span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full rounded-full transition-all" :class="{
                                        'bg-red-500': score.score === 1,
                                        'bg-amber-500': score.score === 2,
                                        'bg-blue-500': score.score === 3,
                                        'bg-green-500': score.score === 4,
                                    }" :style="{ width: `${(score.score / 4) * 100}%` }" />
                                </div>
                                <p v-if="score.comment" class="mt-0.5 text-xs italic text-muted-foreground">
                                    “{{ score.comment }}”
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No scores recorded.</p>
                        <div v-if="review.comment" class="mt-4 rounded-lg border bg-muted/30 px-3 py-2.5">
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Reviewer comment</p>
                            <div class="review-comment text-sm leading-relaxed text-foreground" v-html="review.comment" />
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <ConfirmDialog
            v-model:open="turnInConfirmOpen"
            title="Turn in this document?"
            description="Your judges and instructor will be able to review it. You can unsubmit to make changes before the upload deadline."
            cancel-text="Cancel"
            confirm-text="Yes, Turn in"
            @confirm="confirmTurnIn"
        />
        <ConfirmDialog
            v-model:open="unsubmitConfirmOpen"
            title="Unsubmit this document?"
            description="It will go back to a draft that judges can't see, so you can replace or remove it. Remember to Turn in again before the deadline."
            cancel-text="Keep submitted"
            confirm-text="Yes, Unsubmit"
            @confirm="confirmUnsubmit"
        />
        <ConfirmDialog
            v-model:open="removeConfirmOpen"
            title="Remove this attached file?"
            description="The attached file will be deleted so you can attach the correct one. This cannot be undone."
            cancel-text="Cancel"
            confirm-text="Yes, Remove"
            @confirm="confirmRemove"
        />
        <ConfirmDialog
            v-model:open="overrideConfirmOpen"
            title="Override the final score?"
            :description="overrideConfirmDescription"
            cancel-text="Cancel"
            confirm-text="Yes, Override Score"
            @confirm="confirmOverride"
        />
    </div>
</template>

<style scoped>
/* Render the sanitized rich-text reviewer comment (paragraphs, lists, emphasis). */
.review-comment :deep(p) { margin: 0 0 0.5rem; }
.review-comment :deep(p:last-child) { margin-bottom: 0; }
.review-comment :deep(ul) { list-style: disc; padding-left: 1.25rem; margin: 0 0 0.5rem; }
.review-comment :deep(ol) { list-style: decimal; padding-left: 1.25rem; margin: 0 0 0.5rem; }
.review-comment :deep(li) { margin: 0.15rem 0; }
.review-comment :deep(strong) { font-weight: 600; }
.review-comment :deep(em) { font-style: italic; }
.review-comment :deep(h1),
.review-comment :deep(h2),
.review-comment :deep(h3) { font-weight: 600; margin: 0.25rem 0; }
.review-comment :deep(blockquote) { border-left: 3px solid var(--border); padding-left: 0.75rem; color: var(--muted-foreground); margin: 0 0 0.5rem; }
</style>
