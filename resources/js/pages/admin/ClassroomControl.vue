<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import {
    BookOpen,
    CheckCircle2,
    ExternalLink,
    FileText,
    LockOpen,
    Shield,
    SlidersHorizontal,
    Trash2,
    Users,
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminClassroomControlController from '@/actions/App/Http/Controllers/Admin/ClassroomControlController';
import AdminPaperScoreController from '@/actions/App/Http/Controllers/Admin/PaperScoreController';
import AdminReviewScoreController from '@/actions/App/Http/Controllers/Admin/ReviewScoreController';
import ReviewController from '@/actions/App/Http/Controllers/ReviewController';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminClassroomsIndex } from '@/routes/admin/classrooms';
import { index as adminSystemHealthIndex } from '@/routes/admin/system-health';

type Person = {
    id: number;
    name: string;
    email: string;
    role?: string;
    role_label?: string | null;
};

type SubjectPayload = {
    id: number;
    title: string;
    description: string | null;
    teacher_id: number;
    teacher: Person | null;
    join_code: string | null;
    reviewer_code: string | null;
    passing_score: number;
    url: string;
    students: Person[];
    reviewers: Person[];
    pending_members: Person[];
};

type TeamPayload = {
    id: number;
    name: string;
    topic: string | null;
    advisor: { id: number; name: string } | null;
    members: Person[];
};

type AttemptPayload = {
    id: number;
    team_id: number;
    team_name: string;
    period_name: string;
    label: string;
    status: string;
    defense_date: string | null;
    defense_time: string | null;
    defense_room: string | null;
    paper_id: number | null;
    paper_url: string | null;
    active_reviewers_count: number;
    submitted_reviews_count: number;
    score_deadline_at: string | null;
    results_released_at: string | null;
    final_score: number | null;
    override_score: string | number | null;
    override_note: string | null;
};

type ScoreEntry = {
    criteria: string;
    score: number | string;
    max_score?: number | string | null;
    weight?: number | string | null;
    comment?: string;
};

type ReviewPayload = {
    id: number;
    reviewer_name: string;
    team_name: string;
    period_name: string;
    label: string | null;
    scores_count: number;
    scores_json: ScoreEntry[];
    comment: string | null;
    is_submitted: boolean;
    locked_at: string | null;
    unlocked_at: string | null;
    url: string;
};

const props = defineProps<{
    subject: SubjectPayload;
    ownerCandidates: Person[];
    availableUsers: Person[];
    teams: TeamPayload[];
    attempts: AttemptPayload[];
    reviews: ReviewPayload[];
    stats: Record<string, number>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Classrooms', href: adminClassroomsIndex() },
        ],
    },
});

const ownerForm = useForm({
    teacher_id: props.subject.teacher_id,
});

const memberForm = useForm({
    user_id: '',
    member_type: 'student',
    reviewer_role: 'advisor',
    team_id: '',
});

const overrideDrafts = reactive<Record<number, { override_score: string; override_note: string }>>({});
const reviewDrafts = reactive<Record<number, { scores_json: ScoreEntry[]; comment: string; reason: string }>>({});

props.attempts.forEach((attempt) => {
    if (attempt.paper_id) {
        overrideDrafts[attempt.paper_id] = {
            override_score: String(attempt.override_score ?? attempt.final_score ?? ''),
            override_note: attempt.override_note ?? '',
        };
    }
});

props.reviews.forEach((review) => {
    reviewDrafts[review.id] = {
        scores_json: review.scores_json.map((score) => ({ ...score, comment: score.comment ?? '' })),
        comment: review.comment ?? '',
        reason: '',
    };
});

// Human label for a stored membership role. Legacy/retired values (guest_panel,
// fyp_instructor) display safely; nothing renders as raw snake_case.
const roleDisplayLabels: Record<string, string> = {
    advisor: 'Advisor',
    fyp_instructor: 'FYP Instructor',
    technical_examiner: 'Technical examiner',
    academic_examiner: 'Academic examiner',
    guest_panel: 'Custom role',
    custom: 'Custom role',
    student: 'Student',
};
function displayRole(role?: string | null): string {
    if (!role) return '';
    return roleDisplayLabels[role] ?? role.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

const availableStudents = computed(() => props.availableUsers.filter((user) => user.role === 'student'));
const availableReviewers = computed(() => props.availableUsers.filter((user) => user.role === 'teacher'));
const selectedPool = computed(() => (memberForm.member_type === 'student' ? availableStudents.value : availableReviewers.value));
const unlockedReviews = computed(() => props.reviews.filter((review) => review.unlocked_at && !review.locked_at));
const submittedReviews = computed(() => props.reviews.filter((review) => review.is_submitted));
const attemptsWithPapers = computed(() =>
    props.attempts.filter((attempt): attempt is AttemptPayload & { paper_id: number } => attempt.paper_id !== null && overrideDrafts[attempt.paper_id] !== undefined),
);

function updateOwner() {
    ownerForm.patch(AdminClassroomControlController.updateOwner.url({ subject: props.subject.id }), {
        preserveScroll: true,
    });
}

function addMember() {
    memberForm.post(AdminClassroomControlController.addMember.url({ subject: props.subject.id }), {
        preserveScroll: true,
        onSuccess: () => {
            memberForm.reset('user_id', 'team_id');
        },
    });
}

function removeMember(user: Person) {
    if (!window.confirm(`Remove ${user.name} from ${props.subject.title}? Submitted review history will stay saved.`)) {
        return;
    }

    router.delete(AdminClassroomControlController.removeMember.url({ subject: props.subject.id, user: user.id }), {
        preserveScroll: true,
    });
}

function saveOverride(attempt: AttemptPayload) {
    if (!attempt.paper_id || !overrideDrafts[attempt.paper_id]?.override_score) {
        return;
    }

    router.patch(
        AdminPaperScoreController.update.url({ paper: attempt.paper_id }),
        {
            override_score: overrideDrafts[attempt.paper_id].override_score,
            override_note: overrideDrafts[attempt.paper_id].override_note,
        },
        { preserveScroll: true },
    );
}

function saveReviewCorrection(review: ReviewPayload) {
    const draft = reviewDrafts[review.id];

    if (!draft || draft.reason.trim().length === 0) {
        return;
    }

    router.patch(
        AdminReviewScoreController.update.url({ review: review.id }),
        {
            scores_json: draft.scores_json,
            comment: draft.comment,
            reason: draft.reason,
        },
        { preserveScroll: true },
    );
}

function unlockReview(review: ReviewPayload) {
    const reason = window.prompt(`Reason for unlocking ${review.reviewer_name}'s review?`);

    if (!reason) {
        return;
    }

    router.post(ReviewController.unlock.url({ review: review.id }), { reason }, { preserveScroll: true });
}

function formatDate(date: string | null) {
    if (!date) {
        return 'Not scheduled';
    }

    return new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTime(time: string | null) {
    if (!time) {
        return 'No time';
    }

    const [hours, minutes] = time.split(':').map(Number);
    return new Date(2026, 0, 1, hours, minutes).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

function statusClass(attempt: AttemptPayload) {
    if (attempt.results_released_at) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200';
    }

    if (!attempt.paper_id || !attempt.defense_date) {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200';
    }

    return 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-200';
}
</script>

<template>
    <Head :title="`Admin Control - ${subject.title}`" />

    <div class="flex flex-col gap-6 p-6">
        <section class="rounded-2xl bg-gradient-to-br from-[#24327a] to-indigo-800 p-6 text-white shadow-md">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/60">Admin classroom control</p>
                    <h1 class="mt-2 text-2xl font-bold">{{ subject.title }}</h1>
                    <p class="mt-1 max-w-3xl text-sm text-white/75">
                        Use this page only for admin intervention: owner transfer, emergency access, score override, and review correction.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child class="bg-white text-[#24327a] hover:bg-white/90">
                        <Link :href="subject.url">
                            Open Subject
                            <ExternalLink class="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                    <Button as-child variant="outline" class="border-white/30 bg-white/10 text-white hover:bg-white/20">
                        <Link :href="adminSystemHealthIndex()">System Health</Link>
                    </Button>
                </div>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <Card v-for="(value, key) in stats" :key="key" class="rounded-2xl shadow-sm">
                <CardContent class="p-4">
                    <p class="text-2xl font-bold">{{ value }}</p>
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ String(key).replaceAll('_', ' ') }}</p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card class="rounded-2xl shadow-sm">
                <CardHeader class="border-b">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Shield class="h-4 w-4 text-[#24327a]" />
                        Ownership and emergency access
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-5 p-5">
                    <form class="grid gap-3 md:grid-cols-[1fr_auto]" @submit.prevent="updateOwner">
                        <div>
                            <label class="text-sm font-semibold">FYP Instructor / subject owner</label>
                            <select v-model="ownerForm.teacher_id" class="mt-2 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                <option v-for="candidate in ownerCandidates" :key="candidate.id" :value="candidate.id">
                                    {{ candidate.name }} · {{ candidate.email }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-muted-foreground">Changing owner adds the new instructor to every defense attempt automatically.</p>
                        </div>
                        <Button class="self-end bg-[#24327a] text-white hover:bg-[#1d2863]" :disabled="ownerForm.processing">
                            Save owner
                        </Button>
                    </form>

                    <form class="grid gap-3 rounded-2xl border bg-muted/30 p-4" @submit.prevent="addMember">
                        <div class="grid gap-3 md:grid-cols-3">
                            <div>
                                <label class="text-sm font-semibold">Add as</label>
                                <select v-model="memberForm.member_type" class="mt-2 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                    <option value="student">Student</option>
                                    <option value="reviewer">Reviewer/Judge</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-semibold">User</label>
                                <select v-model="memberForm.user_id" class="mt-2 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                    <option value="">Choose user</option>
                                    <option v-for="user in selectedPool" :key="user.id" :value="user.id">
                                        {{ user.name }} · {{ user.email }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="memberForm.member_type === 'student'">
                                <label class="text-sm font-semibold">Optional team</label>
                                <select v-model="memberForm.team_id" class="mt-2 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                    <option value="">Subject only</option>
                                    <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>
                            </div>
                            <div v-else>
                                <label class="text-sm font-semibold">Default reviewer role</label>
                                <select v-model="memberForm.reviewer_role" class="mt-2 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                                    <option value="advisor">Advisor</option>
                                    <option value="technical_examiner">Technical examiner</option>
                                    <option value="academic_examiner">Academic examiner</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <Button class="bg-[#24327a] text-white hover:bg-[#1d2863]" :disabled="memberForm.processing || !memberForm.user_id">
                                Add user
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card class="rounded-2xl shadow-sm">
                <CardHeader class="border-b">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Users class="h-4 w-4 text-[#24327a]" />
                        Subject members
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 p-5 lg:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-semibold">Students</h3>
                        <div class="mt-3 max-h-72 space-y-2 overflow-auto pr-1">
                            <div v-for="student in subject.students" :key="student.id" class="flex items-center justify-between gap-3 rounded-xl border p-3">
                                <div>
                                    <p class="font-semibold">{{ student.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ student.email }}</p>
                                </div>
                                <Button variant="ghost" size="icon" class="text-red-600 hover:bg-red-50 hover:text-red-700" @click="removeMember(student)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold">Review panel</h3>
                        <div class="mt-3 max-h-72 space-y-2 overflow-auto pr-1">
                            <div v-for="reviewer in subject.reviewers" :key="reviewer.id" class="flex items-center justify-between gap-3 rounded-xl border p-3">
                                <div>
                                    <p class="font-semibold">{{ reviewer.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ reviewer.email }}</p>
                                    <Badge variant="secondary" class="mt-1 text-xs">{{ reviewer.role_label || displayRole(reviewer.role) }}</Badge>
                                </div>
                                <Button v-if="reviewer.id !== subject.teacher_id" variant="ghost" size="icon" class="text-red-600 hover:bg-red-50 hover:text-red-700" @click="removeMember(reviewer)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card class="rounded-2xl shadow-sm">
            <CardHeader class="border-b">
                <CardTitle class="flex items-center gap-2 text-base">
                    <BookOpen class="h-4 w-4 text-[#24327a]" />
                    Teams and defense attempts
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-muted/60 text-left text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <tr>
                                <th class="px-5 py-3">Team</th>
                                <th class="px-5 py-3">Defense</th>
                                <th class="px-5 py-3">Schedule</th>
                                <th class="px-5 py-3">Reviews</th>
                                <th class="px-5 py-3">Result</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="attempt in attempts" :key="attempt.id" class="align-top">
                                <td class="px-5 py-4">
                                    <p class="font-semibold">{{ attempt.team_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ teams.find((team) => team.id === attempt.team_id)?.topic || 'Project topic not set' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge variant="outline">{{ attempt.period_name }}</Badge>
                                    <p class="mt-1 text-xs text-muted-foreground">{{ attempt.label }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ formatDate(attempt.defense_date) }}</p>
                                    <p class="text-xs text-muted-foreground">{{ formatTime(attempt.defense_time) }} · {{ attempt.defense_room || 'No room' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <Badge variant="outline" :class="statusClass(attempt)">
                                        {{ attempt.submitted_reviews_count }}/{{ attempt.active_reviewers_count }} submitted
                                    </Badge>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-semibold">{{ attempt.final_score ?? 'Not calculated' }}</p>
                                    <p class="text-xs text-muted-foreground">{{ attempt.results_released_at ? 'Released' : 'Not released' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button v-if="attempt.paper_url" as-child size="sm" variant="outline">
                                            <Link :href="attempt.paper_url">
                                                <FileText class="mr-2 h-4 w-4" />
                                                Paper
                                            </Link>
                                        </Button>
                                        <Button as-child size="sm" variant="outline">
                                            <Link :href="subject.url">
                                                <SlidersHorizontal class="mr-2 h-4 w-4" />
                                                Manage
                                            </Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card class="rounded-2xl shadow-sm">
                <CardHeader class="border-b">
                    <CardTitle class="text-base">Final score override</CardTitle>
                    <p class="text-sm text-muted-foreground">Use only for correction cases. The note is stored with the override.</p>
                </CardHeader>
                <CardContent class="space-y-3 p-5">
                    <div v-for="attempt in attemptsWithPapers" :key="`override-${attempt.id}`" class="rounded-xl border p-3">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ attempt.team_name }} · {{ attempt.period_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ attempt.label }} · Current: {{ attempt.final_score ?? 'Not calculated' }}</p>
                            </div>
                            <div class="grid flex-1 gap-2 sm:grid-cols-[120px_1fr_auto]">
                                <Input v-model="overrideDrafts[attempt.paper_id].override_score" placeholder="Score" />
                                <Input v-model="overrideDrafts[attempt.paper_id].override_note" placeholder="Reason / admin note" />
                                <Button class="bg-[#24327a] text-white hover:bg-[#1d2863]" @click="saveOverride(attempt)">
                                    Save
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="rounded-2xl shadow-sm">
                <CardHeader class="border-b">
                    <CardTitle class="text-base">Submitted reviews and corrections</CardTitle>
                    <p class="text-sm text-muted-foreground">Unlock a submitted review only when a judge needs to correct it. Every unlock is logged.</p>
                </CardHeader>
                <CardContent class="space-y-3 p-5">
                    <div v-if="submittedReviews.length === 0" class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground">
                        No submitted reviews yet.
                    </div>
                    <div v-for="review in submittedReviews" :key="review.id" class="space-y-3 rounded-xl border p-3">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="font-semibold">{{ review.team_name }} · {{ review.period_name }}</p>
                                <p class="text-sm text-muted-foreground">{{ review.reviewer_name }} · {{ review.scores_count }} criteria scored</p>
                                <Badge v-if="unlockedReviews.some((item) => item.id === review.id)" variant="outline" class="mt-2 border-amber-200 bg-amber-50 text-amber-700">
                                    Open for correction
                                </Badge>
                            </div>
                            <div class="flex gap-2">
                                <Button as-child size="sm" variant="outline">
                                    <Link :href="review.url">View</Link>
                                </Button>
                                <Button size="sm" variant="outline" class="border-amber-200 text-amber-700 hover:bg-amber-50" @click="unlockReview(review)">
                                    <LockOpen class="mr-2 h-4 w-4" />
                                    Unlock
                                </Button>
                            </div>
                        </div>

                        <div v-if="reviewDrafts[review.id]?.scores_json.length" class="space-y-2 rounded-xl bg-muted/30 p-3">
                            <div
                                v-for="(score, index) in reviewDrafts[review.id].scores_json"
                                :key="`${review.id}-${index}`"
                                class="grid gap-2 rounded-lg border bg-background p-3 md:grid-cols-[1fr_100px_1fr]"
                            >
                                <div>
                                    <p class="text-sm font-semibold">{{ score.criteria }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        Max {{ score.max_score ?? '—' }}<span v-if="score.weight"> · Weight {{ score.weight }}%</span>
                                    </p>
                                </div>
                                <Input v-model="score.score" type="number" min="0" step="0.01" aria-label="Corrected score" />
                                <Input v-model="score.comment" placeholder="Criterion comment" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <textarea
                                v-model="reviewDrafts[review.id].comment"
                                class="min-h-20 rounded-xl border border-input bg-background px-3 py-2 text-sm"
                                placeholder="Overall feedback / comment"
                            />
                            <Input v-model="reviewDrafts[review.id].reason" placeholder="Required admin correction reason" />
                        </div>

                        <div class="flex justify-end">
                            <Button
                                class="bg-[#24327a] text-white hover:bg-[#1d2863]"
                                :disabled="reviewDrafts[review.id].reason.trim().length === 0"
                                @click="saveReviewCorrection(review)"
                            >
                                Save score correction
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
