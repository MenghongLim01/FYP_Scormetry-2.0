<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Star, Users, Globe, ClipboardCheck, BookOpen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger, DialogClose } from '@/components/ui/dialog';
import { useAuth } from '@/composables/useAuth';
import { update as adminOverrideScore } from '@/actions/App/Http/Controllers/Admin/PaperScoreController';
import { index as paperIndex } from '@/actions/App/Http/Controllers/PaperController';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { create as rubricCreate } from '@/actions/App/Http/Controllers/RubricController';
import { create as reviewCreate, show as reviewShow } from '@/actions/App/Http/Controllers/ReviewController';
import { publish as paperPublish } from '@/actions/App/Http/Controllers/PaperController';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    paper: {
        id: number;
        file_path: string;
        final_score: number | string | null;
        final_score_override: number | string | null;
        final_score_override_reason: string | null;
        final_score_override_by: number | null;
        visibility_status: string;
        team: { id: number; name: string; members: Array<{ id: number; name: string }> };
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
            scores_json: Array<{ criteria: string; score: number }> | null;
            comment: string | null;
            reviewer: { id: number; name: string };
        }>;
    };
    paperPdfUrl: string;
    rubricPdfUrl: string | null;
}>();

const { isTeacherOrAdmin, isAdmin, user } = useAuth();

const isSubjectOwner = computed(() => props.paper.subject && user.value?.id === props.paper.subject.teacher_id);

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

type TabKey = 'rubric' | 'paper' | 'scoring';
const activeTab = ref<TabKey>('rubric');

const tabs: Array<{ key: TabKey; label: string; icon: typeof FileText }> = [
    { key: 'rubric', label: 'Rubric PDF', icon: ClipboardCheck },
    { key: 'paper', label: 'Paper PDF', icon: FileText },
    { key: 'scoring', label: 'AI Scoring', icon: BookOpen },
];

const canPublish = computed(() =>
    effectiveFinalScoreNumber.value !== null
    && props.paper.visibility_status !== 'published'
    && effectiveFinalScoreNumber.value >= props.paper.subject.passing_score,
);

const submittedReviewCount = computed(() =>
    props.paper.reviews.filter((r) => r.is_submitted).length,
);

function publishPaper() {
    router.post(paperPublish.url(props.paper.id));
}

const overrideForm = useForm({
    override_score: effectiveFinalScoreNumber.value ?? 0,
    override_note: '',
});

function submitOverride() {
    overrideForm.patch(adminOverrideScore.url(props.paper.id), {
        preserveScroll: true,
        onSuccess: () => overrideForm.reset('override_note'),
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
</script>

<template>
    <Head :title="`Paper — ${paper.team.name}`" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="paperIndex.url()">
                    <ArrowLeft class="h-4 w-4" />
                    Papers
                </Link>
            </Button>
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
                            <Users class="inline h-3 w-3 mr-1" />{{ paper.team.members.map((m) => m.name).join(', ') }}
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
	                                    <p><span class="font-medium">Reviews:</span> {{ submittedReviewCount }}/{{ paper.reviews.length }} submitted</p>
	                                </div>
	                                <DialogFooter>
	                                    <DialogClose as-child>
                                        <Button variant="outline">Cancel</Button>
                                    </DialogClose>
                                    <Button class="gap-2" @click="publishPaper">
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

                <div v-if="isAdmin" class="mt-4 rounded-xl border bg-muted/30 px-4 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold">Admin: Score Override</p>
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

                    <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="submitOverride">
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
                        title="Rubric PDF"
                    />
                </div>
                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                    <ClipboardCheck class="mb-3 h-9 w-9 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">No rubric PDF available.</p>
                    <Button v-if="isTeacherOrAdmin" size="sm" class="mt-3" as-child>
                        <Link :href="rubricCreate.url(paper.subject.id)">Upload Rubric</Link>
                    </Button>
                </div>
            </div>

            <div v-show="activeTab === 'paper'" class="p-0">
                <iframe
                    :src="paperPdfUrl"
                    class="h-[70vh] w-full border-0"
                    title="Student Paper PDF"
                />
            </div>

            <div v-show="activeTab === 'scoring'" class="p-6">
                <div v-if="paper.subject.rubric?.structure_json && paper.subject.rubric.structure_json.length > 0">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold">AI-Extracted Rubric Criteria</h3>
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
                                <tr v-for="(item, index) in paper.subject.rubric.structure_json" :key="index" class="transition-colors hover:bg-muted/70">
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

        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">
                Reviews
                <span class="ml-1.5 text-sm font-normal text-muted-foreground">({{ submittedReviewCount }}/{{ paper.reviews.length }} submitted)</span>
            </h2>
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
                :href="reviewShow.url(review.id)"
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
                            <Badge :variant="review.is_submitted ? 'default' : 'outline'">
                                {{ review.is_submitted ? 'Submitted' : 'Draft' }}
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
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">No scores recorded.</p>
                        <p v-if="review.comment" class="mt-3 line-clamp-2 text-xs text-muted-foreground italic">
                            Has reviewer comment
                        </p>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </div>
</template>
