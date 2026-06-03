<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Eye,
    FileText,
    Filter,
    UploadCloud,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { create as paperCreate, show as paperShow } from '@/actions/App/Http/Controllers/PaperController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useAuth } from '@/composables/useAuth';
import { formatClockTime } from '@/lib/utils';

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'success' | 'warning' | 'destructive';
type FilterKey = 'all' | 'missing' | 'needs_review' | 'ready' | 'released';

type ReviewerAssignment = {
    id: number;
    reviewer_id: number;
    status: string;
    reviewer?: { id: number; name: string } | null;
};

type DefenseAttempt = {
    id: number;
    label: string;
    attempt_number: number;
    attempt_type: string;
    status: string;
    defense_date: string | null;
    defense_time: string | null;
    defense_duration: number | null;
    defense_room: string | null;
    results_released_at: string | null;
    period?: {
        id: number;
        name: string;
        sequence: number;
        type: string;
    } | null;
    active_reviewer_assignments?: ReviewerAssignment[];
};

type PaperRow = {
    id: number;
    file_path: string;
    final_score: number | string | null;
    final_score_override?: number | string | null;
    visibility_status: string;
    team?: {
        id: number;
        name: string;
        members: Array<{ id: number; name: string }>;
        student_members?: Array<{ id: number; name: string }>;
    };
    subject: { id: number; title: string };
    defense_attempt?: DefenseAttempt | null;
    reviews?: Array<{
        id: number;
        is_submitted: boolean;
        reviewer: { id: number; name: string };
    }>;
};

type PaperGroup = {
    key: string;
    subjectTitle: string;
    teamId: number | null;
    teamName: string;
    members: Array<{ id: number; name: string }>;
    papers: PaperRow[];
};

const props = defineProps<{
    papers: PaperRow[];
    reviewerTeamIds: number[];
}>();

const { isStudent, isTeacherOrAdmin } = useAuth();

const filterMyTeamsOnly = ref(false);
const activeFilter = ref<FilterKey>('all');

const isJudgeWithTeams = computed(
    () => isTeacherOrAdmin.value && props.reviewerTeamIds.length > 0,
);

const roleSummary = computed(() => {
    if (isStudent.value) {
        return 'Track your team document, review status, and released result in one place.';
    }

    if (isJudgeWithTeams.value) {
        return 'Open approved team documents, see the correct defense session, and know which reviews still need work.';
    }

    return 'Review team documents by defense session, check reviewer progress, and release results when ready.';
});

const teamScopedPapers = computed(() => {
    if (filterMyTeamsOnly.value && props.reviewerTeamIds.length > 0) {
        return props.papers.filter((paper) => paper.team && props.reviewerTeamIds.includes(paper.team.id));
    }

    return props.papers;
});

const filteredPapers = computed(() =>
    teamScopedPapers.value.filter((paper) => {
        if (activeFilter.value === 'all') {
            return true;
        }

        if (activeFilter.value === 'missing') {
            return isDocumentMissing(paper);
        }

        if (activeFilter.value === 'needs_review') {
            return !isPaperReleased(paper) && !isDocumentMissing(paper) && !isReadyToRelease(paper);
        }

        if (activeFilter.value === 'ready') {
            return isReadyToRelease(paper);
        }

        return isPaperReleased(paper);
    }),
);

const filterOptions = computed(() => {
    const papers = teamScopedPapers.value;

    return [
        { key: 'all' as const, label: 'All', count: papers.length },
        { key: 'missing' as const, label: 'Missing document', count: papers.filter(isDocumentMissing).length },
        { key: 'needs_review' as const, label: 'Needs review', count: papers.filter((paper) => !isPaperReleased(paper) && !isDocumentMissing(paper) && !isReadyToRelease(paper)).length },
        { key: 'ready' as const, label: 'Ready to release', count: papers.filter(isReadyToRelease).length },
        { key: 'released' as const, label: 'Released', count: papers.filter(isPaperReleased).length },
    ];
});

const groupedPapers = computed<PaperGroup[]>(() => {
    const groups = new Map<string, PaperGroup>();

    for (const paper of filteredPapers.value) {
        const key = `${paper.subject.id}-${paper.team?.id ?? 'unassigned'}`;
        const existing = groups.get(key);

        if (existing) {
            existing.papers.push(paper);
            continue;
        }

        groups.set(key, {
            key,
            subjectTitle: paper.subject.title,
            teamId: paper.team?.id ?? null,
            teamName: paper.team?.name ?? 'Unassigned team',
            members: paper.team?.student_members ?? paper.team?.members ?? [],
            papers: [paper],
        });
    }

    return Array.from(groups.values())
        .map((group) => ({
            ...group,
            papers: [...group.papers].sort((a, b) => paperSortKey(a).localeCompare(paperSortKey(b))),
        }))
        .sort((a, b) => `${a.subjectTitle}-${a.teamName}`.localeCompare(`${b.subjectTitle}-${b.teamName}`));
});

function scoreValue(paper: PaperRow): number | null {
    const rawScore = paper.final_score_override ?? paper.final_score;

    if (rawScore === null || rawScore === undefined) {
        return null;
    }

    const score = Number(rawScore);

    return Number.isFinite(score) ? score : null;
}

function activeReviewerCount(paper: PaperRow): number {
    return paper.defense_attempt?.active_reviewer_assignments?.length ?? paper.reviews?.length ?? 0;
}

function submittedReviewCount(paper: PaperRow): number {
    return paper.reviews?.filter((review) => review.is_submitted).length ?? 0;
}

function isDocumentMissing(paper: PaperRow): boolean {
    return paper.visibility_status === 'draft';
}

function hasReleaseMarker(paper: PaperRow): boolean {
    return paper.visibility_status === 'published'
        || (paper.defense_attempt?.results_released_at !== null && paper.defense_attempt?.results_released_at !== undefined);
}

function isPaperReleased(paper: PaperRow): boolean {
    return hasReleaseMarker(paper) && scoreValue(paper) !== null;
}

function isReadyToRelease(paper: PaperRow): boolean {
    return !isPaperReleased(paper) && scoreValue(paper) !== null && submittedReviewCount(paper) > 0;
}

function documentLabel(paper: PaperRow): string {
    if (isDocumentMissing(paper)) {
        return 'No document';
    }

    return 'Document submitted';
}

function documentBadgeVariant(paper: PaperRow): BadgeVariant {
    if (isDocumentMissing(paper)) {
        return 'warning';
    }

    return 'success';
}

function reviewerProgressLabel(paper: PaperRow): string {
    const total = activeReviewerCount(paper);
    const submitted = submittedReviewCount(paper);

    if (total === 0) {
        return 'No scoring roles assigned';
    }

    if (submitted === total) {
        return 'All scoring roles submitted';
    }

    return `${submitted}/${total} scoring roles submitted`;
}

function reviewerBadgeVariant(paper: PaperRow): BadgeVariant {
    const total = activeReviewerCount(paper);

    if (total === 0) {
        return 'warning';
    }

    if (submittedReviewCount(paper) === total) {
        return 'success';
    }

    return 'secondary';
}

function resultLabel(paper: PaperRow): string {
    if (isPaperReleased(paper)) {
        return 'Released';
    }

    if (hasReleaseMarker(paper) && scoreValue(paper) === null) {
        return 'Release pending score';
    }

    if (isReadyToRelease(paper)) {
        return 'Ready to release';
    }

    if (scoreValue(paper) !== null) {
        return 'Score calculated';
    }

    return 'Not calculated';
}

function resultBadgeVariant(paper: PaperRow): BadgeVariant {
    if (isPaperReleased(paper)) {
        return 'success';
    }

    if (hasReleaseMarker(paper) && scoreValue(paper) === null) {
        return 'warning';
    }

    if (isReadyToRelease(paper)) {
        return 'warning';
    }

    return 'outline';
}

function scoreLabel(paper: PaperRow): string {
    const score = scoreValue(paper);

    return score === null ? 'Not calculated' : `${score.toFixed(score % 1 === 0 ? 0 : 2)} / 100`;
}

function roundName(paper: PaperRow): string {
    return paper.defense_attempt?.period?.name ?? 'Subject document';
}

function attemptLabel(paper: PaperRow): string {
    return (paper.defense_attempt?.label ?? 'Latest defense session')
        .replace(/\bAttempt\b/gi, 'Defense Session');
}

function scheduleLabel(paper: PaperRow): string {
    const attempt = paper.defense_attempt;

    if (!attempt?.defense_date) {
        return 'Schedule not set';
    }

    return [attempt.defense_date, formatClockTime(attempt.defense_time, ''), attempt.defense_room]
        .filter(Boolean)
        .join(' · ');
}

function paperSortKey(paper: PaperRow): string {
    const attempt = paper.defense_attempt;

    return [
        String(attempt?.period?.sequence ?? 99).padStart(2, '0'),
        String(attempt?.attempt_number ?? 99).padStart(2, '0'),
        String(paper.id).padStart(6, '0'),
    ].join('-');
}

function isFollowUpRow(paper: PaperRow, rowIndex: number): boolean {
    return rowIndex > 0 || paper.defense_attempt?.attempt_type === 're_defense';
}

function memberNames(group: PaperGroup): string {
    return group.members.map((member) => member.name).join(', ') || 'No student members listed';
}

const TEAM_COLORS = [
    'bg-blue-500',
    'bg-rose-500',
    'bg-emerald-500',
    'bg-violet-500',
    'bg-amber-500',
    'bg-cyan-500',
    'bg-orange-500',
];

function teamColorKey(group: PaperGroup): number {
    const match = group.teamName.match(/\d+/);

    if (match) {
        return Math.max(1, Number(match[0]));
    }

    return Math.max(1, group.teamId ?? 1);
}

function teamAccentColor(group: PaperGroup): string {
    return TEAM_COLORS[(teamColorKey(group) - 1) % TEAM_COLORS.length];
}

function actionLabel(paper: PaperRow): string {
    if (isStudent.value && isDocumentMissing(paper)) {
        return 'Upload document';
    }

    if (isTeacherOrAdmin.value && activeReviewerCount(paper) === 0) {
        return 'Open subject';
    }

    if (isReadyToRelease(paper)) {
        return 'Review result';
    }

    return isPaperReleased(paper) ? 'View result' : 'View document';
}

function actionHref(paper: PaperRow): string {
    if (isStudent.value && isDocumentMissing(paper)) {
        return paper.defense_attempt?.id
            ? paperCreate.url(paper.subject.id, { query: { defense_attempt_id: paper.defense_attempt.id } })
            : paperCreate.url(paper.subject.id);
    }

    if (isTeacherOrAdmin.value && activeReviewerCount(paper) === 0) {
        return subjectShow.url(paper.subject.id);
    }

    return paperShow.url(paper.id);
}

type GroupCellPosition = 'first' | 'middle' | 'last';
function groupCellClasses(index: number, total: number, position: GroupCellPosition): string {
    const isFirstRow = index === 0;
    const isLastRow = index === total - 1;
    const classes: string[] = [];

    if (position === 'first') classes.push('border-l border-slate-200 dark:border-slate-800');
    if (position === 'last') classes.push('border-r border-slate-200 dark:border-slate-800');
    if (isFirstRow) classes.push('border-t border-slate-200 dark:border-slate-800');
    if (isLastRow) classes.push('border-b border-slate-200 dark:border-slate-800');
    if (!isFirstRow) classes.push('border-t border-slate-100 dark:border-slate-800/70');
    if (isFirstRow && position === 'first') classes.push('rounded-tl-xl');
    if (isFirstRow && position === 'last') classes.push('rounded-tr-xl');
    if (isLastRow && position === 'first') classes.push('rounded-bl-xl');
    if (isLastRow && position === 'last') classes.push('rounded-br-xl');

    return classes.join(' ');
}
</script>

<template>
    <Head title="Documents" />

    <div class="flex flex-col gap-5 p-6">
        <div class="rounded-2xl border border-[#24327a]/15 bg-gradient-to-br from-[#24327a]/8 via-white to-white p-5 shadow-sm dark:from-[#24327a]/20 dark:via-background dark:to-background">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#24327a]/10 text-[#24327a] dark:bg-[#24327a]/30 dark:text-indigo-200">
                        <FileText class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#24327a] dark:text-indigo-200">Document Center</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Documents</h1>
                        <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-300">{{ roleSummary }}</p>
                    </div>
                </div>

                <button
                    v-if="isJudgeWithTeams"
                    class="flex h-10 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-medium transition-colors"
                    :class="filterMyTeamsOnly
                        ? 'border-[#24327a] bg-[#24327a]/10 text-[#24327a] dark:border-indigo-300 dark:bg-indigo-300/10 dark:text-indigo-100'
                        : 'border-slate-200 text-slate-600 hover:text-[#24327a] dark:border-slate-800 dark:text-slate-300'"
                    @click="filterMyTeamsOnly = !filterMyTeamsOnly"
                >
                    <Filter class="h-3.5 w-3.5" />
                    {{ filterMyTeamsOnly ? 'My teams only' : 'All teams' }}
                </button>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white/80 px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-950/50">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                        <UploadCloud class="h-4 w-4 text-[#24327a] dark:text-indigo-300" />
                        Document
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Student document/manuscript PDF must be submitted before scoring starts.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white/80 px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-950/50">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                        <ClipboardList class="h-4 w-4 text-[#24327a] dark:text-indigo-300" />
                        Review
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Approved reviewers submit scores under the correct round.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white/80 px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-950/50">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                        <CheckCircle2 class="h-4 w-4 text-[#24327a] dark:text-indigo-300" />
                        Release
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Instructor publishes the result only when the round is ready.</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="option in filterOptions"
                :key="option.key"
                class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                :class="activeFilter === option.key
                    ? 'border-[#24327a] bg-[#24327a] text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-[#24327a]/30 hover:text-[#24327a] dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300'"
                @click="activeFilter = option.key"
            >
                {{ option.label }}
                <span class="ml-1 opacity-75">{{ option.count }}</span>
            </button>
        </div>

        <Card class="overflow-hidden border-[#24327a]/15 shadow-sm">
            <CardHeader class="border-b bg-white px-6 py-4 dark:bg-slate-950">
                <CardTitle class="flex items-center gap-3 text-sm font-semibold text-slate-900 dark:text-white">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#24327a]/10 text-[#24327a] dark:bg-[#24327a]/30 dark:text-indigo-200">
                        <FileText class="h-4 w-4" />
                    </span>
                    {{ filteredPapers.length }} Document{{ filteredPapers.length !== 1 ? 's' : '' }}
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="filteredPapers.length === 0" class="flex flex-col items-center justify-center px-6 py-14 text-center">
                    <FileText class="mb-3 h-9 w-9 text-muted-foreground/40" />
                    <p class="text-sm font-medium text-foreground">No documents match this view.</p>
                    <p class="mt-1 text-xs text-muted-foreground">Change the filter to see other document states.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1180px] border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-[#24327a] text-left text-[11px] font-semibold uppercase tracking-wider text-white">
                                <th class="px-6 py-3">Team</th>
                                <th class="px-6 py-3">Defense Session</th>
                                <th class="px-6 py-3">Document</th>
                                <th class="px-6 py-3">Reviewers</th>
                                <th class="px-6 py-3">Score</th>
                                <th class="px-6 py-3">Result</th>
                                <th class="px-6 py-3 text-right">Next Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(group, groupIndex) in groupedPapers" :key="group.key">
                                <tr v-if="groupIndex > 0" aria-hidden="true">
                                    <td colspan="7" class="h-3 p-0"></td>
                                </tr>
                                <tr
                                    v-for="(paper, rowIndex) in group.papers"
                                    :key="paper.id"
                                    class="align-top transition-colors"
                                    :class="isFollowUpRow(paper, rowIndex)
                                        ? 'bg-[#24327a]/[0.03] hover:bg-[#24327a]/[0.06] dark:bg-[#24327a]/10 dark:hover:bg-[#24327a]/15'
                                        : 'bg-white hover:bg-slate-50/80 dark:bg-slate-950 dark:hover:bg-slate-900/60'"
                                >
                                    <td class="relative px-6 py-4" :class="groupCellClasses(rowIndex, group.papers.length, 'first')">
                                        <span
                                            class="pointer-events-none absolute left-0 w-1.5"
                                            :class="[
                                                teamAccentColor(group),
                                                rowIndex === 0 ? 'top-2 rounded-t-full' : 'top-0',
                                                rowIndex === group.papers.length - 1 ? 'bottom-2 rounded-b-full' : 'bottom-0',
                                            ]"
                                        />
                                        <div class="pl-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-slate-950 dark:text-white">{{ group.teamName }}</p>
                                                <Badge
                                                    v-if="paper.defense_attempt?.attempt_type === 're_defense'"
                                                    variant="outline"
                                                    class="border-amber-200 bg-amber-50 text-amber-700"
                                                >
                                                    Re-defense
                                                </Badge>
                                            </div>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ group.subjectTitle }}</p>
                                            <p class="mt-1 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                                <Users class="h-3 w-3" />
                                                {{ memberNames(group) }}
                                            </p>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4" :class="groupCellClasses(rowIndex, group.papers.length, 'middle')">
                                        <div class="flex flex-col gap-1.5">
                                            <Badge variant="outline" class="w-fit border-[#24327a]/20 bg-[#24327a]/5 text-[#24327a] dark:border-indigo-300/30 dark:bg-indigo-300/10 dark:text-indigo-100">
                                                {{ roundName(paper) }}
                                            </Badge>
                                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ attemptLabel(paper) }}</span>
                                            <span class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400">
                                                <CalendarDays class="h-3 w-3" />
                                                {{ scheduleLabel(paper) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4" :class="groupCellClasses(rowIndex, group.papers.length, 'middle')">
                                        <Badge :variant="documentBadgeVariant(paper)">
                                            {{ documentLabel(paper) }}
                                        </Badge>
                                    </td>

                                    <td class="px-6 py-4" :class="groupCellClasses(rowIndex, group.papers.length, 'middle')">
                                        <div class="flex flex-col gap-1.5">
                                            <Badge :variant="reviewerBadgeVariant(paper)">
                                                {{ reviewerProgressLabel(paper) }}
                                            </Badge>
                                            <span v-if="activeReviewerCount(paper) === 0" class="flex items-center gap-1 text-xs text-amber-700 dark:text-amber-300">
                                                <AlertTriangle class="h-3 w-3" />
                                                Approve reviewers first
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-6 py-4" :class="groupCellClasses(rowIndex, group.papers.length, 'middle')">
                                        <span
                                            :class="scoreValue(paper) === null
                                                ? 'text-slate-500 dark:text-slate-400'
                                                : 'font-semibold text-slate-950 dark:text-white'"
                                        >
                                            {{ scoreLabel(paper) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4" :class="groupCellClasses(rowIndex, group.papers.length, 'middle')">
                                        <Badge :variant="resultBadgeVariant(paper)">
                                            {{ resultLabel(paper) }}
                                        </Badge>
                                    </td>

                                    <td class="px-6 py-4 text-right" :class="groupCellClasses(rowIndex, group.papers.length, 'last')">
                                        <Button
                                            size="sm"
                                            :variant="isStudent && isDocumentMissing(paper) ? 'default' : 'outline'"
                                            class="h-8 gap-1.5"
                                            :class="isStudent && isDocumentMissing(paper) ? 'bg-[#24327a] text-white hover:bg-[#1b255c]' : ''"
                                            as-child
                                        >
                                            <Link :href="actionHref(paper)">
                                                <UploadCloud v-if="isStudent && isDocumentMissing(paper)" class="h-3.5 w-3.5" />
                                                <Eye v-else class="h-3.5 w-3.5" />
                                                {{ actionLabel(paper) }}
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
