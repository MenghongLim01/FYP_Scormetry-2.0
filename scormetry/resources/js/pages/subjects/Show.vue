<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeft, BookOpen, Users, FileText, ClipboardCheck, Pencil, Upload, UserMinus, UserPlus,
    UsersRound, ShieldCheck, Trash2, LogOut, Copy, Check, BarChart3, Star, Eye, AlertTriangle,
    Clock, Calendar, Lock, Unlock, Send, BarChart2, CheckCircle2, MapPin, RefreshCw, CornerDownRight,
} from 'lucide-vue-next';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import {
    Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAuth } from '@/composables/useAuth';
import {
    index as subjectsIndex, edit as subjectEdit,
    addStudent as addStudentAction, removeStudent as removeStudentAction,
    addReviewer as addReviewerAction, removeReviewer as removeReviewerAction,
    resetJoinCode as resetJoinCodeAction, resetReviewerCode as resetReviewerCodeAction,
    approveMember as approveMemberAction, rejectMember as rejectMemberAction,
    destroy as subjectDestroy, leave as subjectLeave,
} from '@/actions/App/Http/Controllers/SubjectController';
import { create as rubricCreate, show as rubricShow, edit as rubricEdit } from '@/actions/App/Http/Controllers/RubricController';
import { create as paperCreate, show as paperShow } from '@/actions/App/Http/Controllers/PaperController';
import {
    store as teamStore, destroy as teamDestroy, leave as teamLeave,
    addMember as addMemberAction, removeMember as removeMemberAction,
    updateSchedule as teamScheduleUpdate, scores as teamScores,
    releaseScores as teamReleaseScores,
} from '@/actions/App/Http/Controllers/TeamController';
import {
    store as defenseAttemptStore,
    update as defenseAttemptUpdate,
    requestReviewer as requestAttemptReviewer,
    approveReviewer as approveAttemptReviewer,
    rejectReviewer as rejectAttemptReviewer,
} from '@/actions/App/Http/Controllers/DefenseAttemptController';
import { unlock as reviewUnlock } from '@/actions/App/Http/Controllers/ReviewController';

type UserData = { id: number; name: string; email: string };

type PaperData = {
    id: number;
    file_path: string;
    final_score: number | null;
    visibility_status: string;
    team: { id: number; name: string; members: Array<{ id: number; name: string }> };
    reviews: Array<{ id: number; is_submitted: boolean; scores_json: Array<{ criteria: string; score: number }> | null }>;
};

type RubricData = {
    id: number;
    pdf_path?: string;
    status: string;
    structure_json: Array<{ criteria: string; max_score: number; weight: number }> | null;
};

type ReviewerAssignmentData = {
    id: number;
    reviewer_id: number;
    committee_role: string | null;
    status: string;
    reviewer: UserData;
};

type DefenseAttemptData = {
    id: number;
    team_id: number;
    label: string;
    attempt_number: number;
    attempt_type: string;
    status: string;
    defense_date: string | null;
    defense_time: string | null;
    defense_duration: number | null;
    defense_room: string | null;
    paper_upload_deadline_at: string | null;
    score_deadline_at: string | null;
    results_released_at: string | null;
    team: { id: number; name: string; members: UserData[] };
    papers: PaperData[];
    reviewer_assignments: ReviewerAssignmentData[];
    active_reviewer_assignments: ReviewerAssignmentData[];
};

type DefensePeriodData = {
    id: number;
    name: string;
    type: string;
    sequence: number;
    status: string;
    score_scale: string;
    passing_score: number;
    rubric: RubricData | null;
    attempts: DefenseAttemptData[];
};

type ScheduleTeamData = {
    id: number;
    name: string;
    save_target?: 'team' | 'attempt';
    round_name?: string;
    defense_date?: string | null;
    defense_time?: string | null;
    defense_duration?: number | null;
    defense_room?: string | null;
    paper_upload_deadline_at?: string | null;
    score_deadline_at?: string | null;
    results_released_at?: string | null;
    members?: Array<{ id: number; name: string; email: string }>;
};

const props = defineProps<{
    subject: {
        id: number;
        title: string;
        description: string | null;
        passing_score: number;
        join_code: string | null;
        reviewer_code: string | null;
        require_approval: boolean;
        teacher: { id: number; name: string };
        students: Array<{ id: number; name: string; email: string }>;
        rubric: RubricData | null;
        defense_periods: DefensePeriodData[];
        papers: PaperData[];
        teams: Array<{
            id: number;
            name: string;
            defense_date: string | null;
            defense_time: string | null;
            defense_duration: number | null;
            defense_room: string | null;
            score_deadline_at: string | null;
            results_released_at: string | null;
            members: Array<{ id: number; name: string; email: string }>;
        }>;
        reviewers: Array<{ id: number; name: string; email: string; pivot: { role: string; role_label: string | null } }>;
        pending_invitations: Array<{ id: number; email: string; committee_role: string; role_label: string | null }>;
        pending_members: Array<{
            id: number;
            role: string;
            status: string;
            role_label: string | null;
            user: { id: number; name: string; email: string };
        }>;
    };
    stats: {
        students: number;
        reviewers: number;
        papers: number;
        reviewed: number;
    };
}>();

const { user, isAdmin, isStudent } = useAuth();

const isSubjectOwner = computed(() => user.value?.id === props.subject.teacher.id);
// Subject management actions (edit, create/delete team, add/remove members, upload rubric)
// are only available to the subject owner or an admin — NOT to other teachers acting as reviewers.
const isOwnerOrAdmin = computed(() => isAdmin.value || isSubjectOwner.value);
const hasVisibleInviteCodes = computed(() => isSubjectOwner.value && (Boolean(props.subject.join_code) || Boolean(props.subject.reviewer_code)));

const isMember = computed(() => {
    if (!user.value) return false;
    return (
        props.subject.students.some((s) => s.id === user.value!.id) ||
        props.subject.reviewers.some((r) => r.id === user.value!.id)
    );
});

const isSubjectReviewer = computed(() => {
    if (!user.value) return false;
    return props.subject.reviewers.some((reviewer) => reviewer.id === user.value!.id);
});

const canLeave = computed(() => isMember.value && !isSubjectOwner.value);

const committeeRoleLabels: Record<string, string> = {
    advisor: 'Advisor',
    fyp_instructor: 'FYP Instructor',
    guest_panel: 'Guest Panel',
    custom: 'Custom',
};

const codeCopied = ref(false);
function copyJoinCode() {
    if (!props.subject.join_code) return;
    navigator.clipboard.writeText(props.subject.join_code).then(() => {
        codeCopied.value = true;
        setTimeout(() => (codeCopied.value = false), 2000);
    });
}

const reviewerCodeCopied = ref(false);
function copyReviewerCode() {
    if (!props.subject.reviewer_code) return;
    navigator.clipboard.writeText(props.subject.reviewer_code).then(() => {
        reviewerCodeCopied.value = true;
        setTimeout(() => (reviewerCodeCopied.value = false), 2000);
    });
}

const resetStudentCodeConfirmOpen = ref(false);
const resetReviewerCodeConfirmOpen = ref(false);

function resetStudentJoinCode() {
    router.patch(resetJoinCodeAction.url(props.subject.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            resetStudentCodeConfirmOpen.value = false;
            codeCopied.value = false;
        },
    });
}

function resetReviewerJoinCode() {
    router.patch(resetReviewerCodeAction.url(props.subject.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            resetReviewerCodeConfirmOpen.value = false;
            reviewerCodeCopied.value = false;
        },
    });
}

const showDeleteDialog = ref(false);
const showLeaveDialog = ref(false);
const showEnrollStudentDialog = ref(false);
const showInviteReviewerDialog = ref(false);
const addMemberTeamId = ref<number | null>(null);

function deleteSubject() {
    router.delete(subjectDestroy.url(props.subject.id), {
        onSuccess: () => { showDeleteDialog.value = false; },
    });
}

function leaveSubject() {
    router.delete(subjectLeave.url(props.subject.id), {
        onSuccess: () => { showLeaveDialog.value = false; },
    });
}

const studentForm = useForm({ email: '' });
function addStudent() {
    studentForm.post(addStudentAction.url(props.subject.id), {
        onSuccess: () => { studentForm.reset(); showEnrollStudentDialog.value = false; },
    });
}

const reviewerForm = useForm({ email: '', committee_role: '', role_label: '' });
const reviewerNeedsCustomLabel = computed(() => reviewerForm.committee_role === 'custom');
function addReviewer() {
    reviewerForm.post(addReviewerAction.url(props.subject.id), {
        onSuccess: () => { reviewerForm.reset(); showInviteReviewerDialog.value = false; },
    });
}

function approvePendingMember(member: { user: { id: number } }) {
    router.patch(approveMemberAction.url({ subject: props.subject.id, user: member.user.id }), {}, {
        preserveScroll: true,
    });
}

function rejectPendingMember(member: { user: { id: number } }) {
    router.patch(rejectMemberAction.url({ subject: props.subject.id, user: member.user.id }), {}, {
        preserveScroll: true,
    });
}

const teamForm = useForm({ name: '' });
function createTeam() {
    teamForm.post(teamStore.url(props.subject.id), {
        onSuccess: () => teamForm.reset(),
    });
}

const memberForm = useForm({ email: '' });
const memberAddSuccess = ref<{ name: string } | null>(null);
let memberSuccessTimer: ReturnType<typeof setTimeout> | null = null;

function flashMemberSuccess(name: string) {
    if (memberSuccessTimer) clearTimeout(memberSuccessTimer);
    memberAddSuccess.value = { name };
    memberSuccessTimer = setTimeout(() => { memberAddSuccess.value = null; }, 3000);
}

function addMemberByEmail(teamId: number) {
    memberForm.post(addMemberAction.url(teamId), {
        onSuccess: () => {
            flashMemberSuccess(memberForm.email);
            memberForm.reset();
        },
    });
}
function addExistingMember(teamId: number, userId: number) {
    const student = props.subject.students.find((s) => s.id === userId);
    router.post(addMemberAction.url(teamId), { email: student?.email }, {
        preserveScroll: true,
        onSuccess: () => { flashMemberSuccess(student?.name ?? 'Member'); },
    });
}

function removeMember(teamId: number, userId: number) {
    router.delete(removeMemberAction.url({ team: teamId, user: userId }));
}
function leaveTeam(teamId: number) {
    router.delete(teamLeave.url(teamId));
}
function deleteTeam(teamId: number) {
    router.delete(teamDestroy.url(teamId));
}

type MemberRemoval = { kind: 'student' | 'reviewer'; id: number; name: string } | null;
const removeConfirmOpen = ref(false);
const pendingRemoval = ref<MemberRemoval>(null);
const removeConfirmDescription = computed(() => {
    if (!pendingRemoval.value) return '';

    return `Are you sure you want to remove ${pendingRemoval.value.name} from this room? This action cannot be undone.`;
});

function requestRemoveStudent(student: { id: number; name: string }) {
    pendingRemoval.value = { kind: 'student', id: student.id, name: student.name };
    removeConfirmOpen.value = true;
}

function requestRemoveReviewer(reviewer: { id: number; name: string }) {
    pendingRemoval.value = { kind: 'reviewer', id: reviewer.id, name: reviewer.name };
    removeConfirmOpen.value = true;
}

function confirmRemoveMember() {
    if (!pendingRemoval.value) return;

    const payload = { subject: props.subject.id, user: pendingRemoval.value.id };
    const action = pendingRemoval.value.kind === 'student' ? removeStudentAction : removeReviewerAction;

    router.delete(action.url(payload), {
        preserveScroll: true,
        onFinish: () => {
            removeConfirmOpen.value = false;
            pendingRemoval.value = null;
        },
    });
}

// Students enrolled in the subject but not yet assigned to any team
const unassignedStudents = computed(() => {
    const assignedIds = new Set(
        props.subject.teams.flatMap((t) => t.members.map((m) => m.id)),
    );
    return props.subject.students.filter((s) => !assignedIds.has(s.id));
});

// Members of a team who are reviewers of the subject (i.e., assigned reviewers)
const reviewerIdSet = computed(() => new Set(props.subject.reviewers.map((r) => r.id)));
function teamAssignedReviewers(team: { members: Array<{ id: number; name: string; email: string }> }) {
    return team.members.filter((m) => reviewerIdSet.value.has(m.id));
}
function teamStudentMembers(team: { members: Array<{ id: number; name: string; email: string }> }) {
    return team.members.filter((m) => !reviewerIdSet.value.has(m.id));
}

const rubricStatusColors: Record<string, string> = {
    uploaded: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950 dark:text-blue-300',
    pending_verification: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950 dark:text-amber-300',
    locked: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-950 dark:text-green-300',
};

function roleBadgeClass(role: string): string {
    if (role === 'fyp_instructor') return 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300';
    if (role === 'advisor')        return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300';
    if (role === 'guest_panel')    return 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300';
    return 'bg-muted text-muted-foreground';
}

function paperBadgeVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'published') return 'default';
    if (status === 'submitted') return 'secondary';
    return 'outline';
}

function statusLabel(status: string): string {
    if (status === 'published') return 'Review Completed';
    return status.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

const myTeams = computed(() =>
    props.subject.teams.filter((team) => team.members.some((m) => m.id === user.value?.id)),
);

const selectedDefensePeriodId = ref<number | null>(props.subject.defense_periods[0]?.id ?? null);
const selectedDefensePeriod = computed(() =>
    props.subject.defense_periods.find((period) => period.id === selectedDefensePeriodId.value)
    ?? props.subject.defense_periods[0]
    ?? null,
);
const visibleRoundAttempts = computed(() => {
    const attempts = selectedDefensePeriod.value?.attempts ?? [];

    if (isStudent.value && !isOwnerOrAdmin.value) {
        return attempts.filter((attempt) => attempt.team.members.some((member) => member.id === user.value?.id));
    }

    return attempts;
});

function attemptForTeam(teamId: number): DefenseAttemptData | null {
    return selectedDefensePeriod.value?.attempts.find((attempt) => attempt.team_id === teamId) ?? null;
}

const roundStats = computed(() => {
    const attempts = visibleRoundAttempts.value;
    const total = attempts.length;
    const scheduled = attempts.filter((a) => !!a.defense_date).length;
    const pdfsIn = attempts.filter((a) => (a.papers?.length ?? 0) > 0).length;
    const reviewersAssigned = attempts.filter((a) => activeAttemptAssignments(a).length > 0).length;
    const reDefenseCount = attempts.filter((a) => a.attempt_type === 're_defense').length;
    return { total, scheduled, awaiting: total - scheduled, pdfsIn, reviewersAssigned, reDefenseCount };
});

function periodScheduledCount(period: DefensePeriodData): number {
    return period.attempts.filter((a) => !!a.defense_date).length;
}

const groupedRoundAttempts = computed(() => {
    const groups: Array<{ teamId: number; attempts: DefenseAttemptData[] }> = [];
    const index = new Map<number, number>();
    for (const attempt of visibleRoundAttempts.value) {
        const existing = index.get(attempt.team_id);
        if (existing === undefined) {
            index.set(attempt.team_id, groups.length);
            groups.push({ teamId: attempt.team_id, attempts: [attempt] });
        } else {
            groups[existing].attempts.push(attempt);
        }
    }
    for (const group of groups) {
        group.attempts.sort((a, b) => a.attempt_number - b.attempt_number);
    }
    return groups;
});

type PaperAttemptInfo = { period: DefensePeriodData; attempt: DefenseAttemptData };
type PaperGroup = {
    teamId: number;
    teamName: string;
    members: PaperData['team']['members'];
    papers: PaperData[];
};

const paperAttemptInfoById = computed(() => {
    const map = new Map<number, PaperAttemptInfo>();

    for (const period of props.subject.defense_periods) {
        for (const attempt of period.attempts) {
            for (const paper of attempt.papers ?? []) {
                map.set(paper.id, { period, attempt });
            }
        }
    }

    return map;
});

function paperAttemptInfo(paper: PaperData): PaperAttemptInfo | null {
    return paperAttemptInfoById.value.get(paper.id) ?? null;
}

function paperRoundName(paper: PaperData): string {
    return paperAttemptInfo(paper)?.period.name ?? 'Subject Paper';
}

function paperAttemptLabel(paper: PaperData): string {
    return paperAttemptInfo(paper)?.attempt.label ?? 'Latest submission';
}

function paperSortKey(paper: PaperData): string {
    const info = paperAttemptInfo(paper);

    return [
        String(info?.period.sequence ?? 99).padStart(2, '0'),
        String(info?.attempt.attempt_number ?? 99).padStart(2, '0'),
        String(paper.id).padStart(6, '0'),
    ].join('-');
}

const groupedPapers = computed<PaperGroup[]>(() => {
    const groups = new Map<number, PaperGroup>();

    for (const paper of props.subject.papers) {
        const existing = groups.get(paper.team.id);

        if (existing) {
            existing.papers.push(paper);
        } else {
            groups.set(paper.team.id, {
                teamId: paper.team.id,
                teamName: paper.team.name,
                members: paper.team.members,
                papers: [paper],
            });
        }
    }

    return Array.from(groups.values())
        .map((group) => ({
            ...group,
            papers: [...group.papers].sort((a, b) => paperSortKey(a).localeCompare(paperSortKey(b))),
        }))
        .sort((a, b) => a.teamName.localeCompare(b.teamName));
});

function paperMemberNames(paper: PaperData): string {
    return paper.team.members.map((member) => member.name).join(', ') || 'No team members';
}

function submittedReviewCount(paper: PaperData): number {
    return paper.reviews.filter((review) => review.is_submitted).length;
}

function isPaperFollowUpRow(paper: PaperData, index: number): boolean {
    return index > 0 || paperAttemptInfo(paper)?.attempt.attempt_type === 're_defense';
}

function subjectTeamForPaper(paper: PaperData) {
    return props.subject.teams.find((team) => team.id === paper.team.id) ?? null;
}

function paperScoreDeadline(paper: PaperData): string | null {
    return subjectTeamForPaper(paper)?.score_deadline_at ?? null;
}

function isPaperScoreDeadlineOverdue(paper: PaperData): boolean {
    const deadline = paperScoreDeadline(paper);

    return deadline !== null && new Date(deadline) < new Date();
}

function isPaperResultReleased(paper: PaperData): boolean {
    return subjectTeamForPaper(paper)?.results_released_at !== null && subjectTeamForPaper(paper)?.results_released_at !== undefined;
}

type GroupCellPosition = 'first' | 'middle' | 'last';
function groupCellClasses(idx: number, total: number, position: GroupCellPosition): string {
    const isFirstRow = idx === 0;
    const isLastRow = idx === total - 1;
    const out: string[] = [];

    if (position === 'first') out.push('border-l border-slate-200 dark:border-slate-700');
    if (position === 'last') out.push('border-r border-slate-200 dark:border-slate-700');
    if (isFirstRow) out.push('border-t border-slate-200 dark:border-slate-700');
    if (isLastRow) out.push('border-b border-slate-200 dark:border-slate-700');
    if (!isFirstRow) out.push('border-t border-slate-100 dark:border-slate-800');

    if (isFirstRow && position === 'first') out.push('rounded-tl-xl');
    if (isFirstRow && position === 'last') out.push('rounded-tr-xl');
    if (isLastRow && position === 'first') out.push('rounded-bl-xl');
    if (isLastRow && position === 'last') out.push('rounded-br-xl');

    return out.join(' ');
}

function attemptPaper(attempt: DefenseAttemptData | null): PaperData | null {
    return attempt?.papers?.[0] ?? null;
}

function activeAttemptAssignments(attempt: DefenseAttemptData | null): ReviewerAssignmentData[] {
    return attempt?.reviewer_assignments?.filter((assignment) => assignment.status === 'active') ?? [];
}

function pendingAttemptAssignments(attempt: DefenseAttemptData | null): ReviewerAssignmentData[] {
    return attempt?.reviewer_assignments?.filter((assignment) => assignment.status === 'pending') ?? [];
}

function currentReviewerAssignment(attempt: DefenseAttemptData | null): ReviewerAssignmentData | null {
    if (!user.value || !attempt) return null;
    return attempt.reviewer_assignments.find((assignment) => assignment.reviewer_id === user.value!.id) ?? null;
}

const pendingRoundReviewerRequestCount = computed(() =>
    props.subject.defense_periods.reduce((total, period) =>
        total + period.attempts.reduce((attemptTotal, attempt) =>
            attemptTotal + pendingAttemptAssignments(attempt).length, 0), 0),
);

function roundRubricLabel(period: DefensePeriodData | null): string {
    if (!period?.rubric) return 'No rubric';
    return statusLabel(period.rubric.status);
}

function roundRubricClass(period: DefensePeriodData | null): string {
    if (!period?.rubric) {
        return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-950/30 dark:text-slate-300';
    }

    return rubricStatusColors[period.rubric.status] ?? 'border-slate-200 bg-slate-50 text-slate-700';
}

function requestReviewerAssignment(attempt: DefenseAttemptData | null) {
    if (!attempt) return;
    router.post(requestAttemptReviewer.url(attempt.id), {}, { preserveScroll: true });
}

function approveReviewerAssignment(attempt: DefenseAttemptData | null, reviewerId: number) {
    if (!attempt) return;
    router.patch(approveAttemptReviewer.url({ defenseAttempt: attempt.id, user: reviewerId }), {}, { preserveScroll: true });
}

function rejectReviewerAssignment(attempt: DefenseAttemptData | null, reviewerId: number) {
    if (!attempt) return;
    router.patch(rejectAttemptReviewer.url({ defenseAttempt: attempt.id, user: reviewerId }), {}, { preserveScroll: true });
}

function createReDefenseAttempt(periodId: number, teamId: number) {
    router.post(defenseAttemptStore.url(periodId), {
        team_id: teamId,
        attempt_type: 're_defense',
    }, { preserveScroll: true });
}

// --- Add Re-defense confirmation ---
type PendingReDefense = { periodId: number; teamId: number; teamName: string; periodName: string } | null;
const addReDefenseConfirmOpen = ref(false);
const pendingReDefense = ref<PendingReDefense>(null);
const addReDefenseDescription = computed(() => {
    if (!pendingReDefense.value) return '';
    return `Add a re-defense round for ${pendingReDefense.value.teamName} under ${pendingReDefense.value.periodName}? The same reviewers will be carried over and a new schedule will need to be set.`;
});

function requestAddReDefense(periodId: number, teamId: number, teamName: string, periodName: string) {
    pendingReDefense.value = { periodId, teamId, teamName, periodName };
    addReDefenseConfirmOpen.value = true;
}

function confirmAddReDefense() {
    if (!pendingReDefense.value) return;
    const { periodId, teamId } = pendingReDefense.value;
    router.post(defenseAttemptStore.url(periodId), {
        team_id: teamId,
        attempt_type: 're_defense',
    }, {
        preserveScroll: true,
        onFinish: () => {
            addReDefenseConfirmOpen.value = false;
            pendingReDefense.value = null;
        },
    });
}

// --- Remove Re-defense confirmation ---
type PendingReDefenseRemoval = { attemptId: number; teamName: string; label: string } | null;
const removeReDefenseConfirmOpen = ref(false);
const pendingReDefenseRemoval = ref<PendingReDefenseRemoval>(null);
const removeReDefenseDescription = computed(() => {
    if (!pendingReDefenseRemoval.value) return '';
    return `Remove ${pendingReDefenseRemoval.value.label} for ${pendingReDefenseRemoval.value.teamName}? This deletes the empty re-defense round. It cannot be removed if a paper has been submitted or a review recorded.`;
});

function requestRemoveReDefense(attemptId: number, teamName: string, label: string) {
    pendingReDefenseRemoval.value = { attemptId, teamName, label };
    removeReDefenseConfirmOpen.value = true;
}

function confirmRemoveReDefense() {
    if (!pendingReDefenseRemoval.value) return;
    router.delete(`/defense-attempts/${pendingReDefenseRemoval.value.attemptId}`, {
        preserveScroll: true,
        onFinish: () => {
            removeReDefenseConfirmOpen.value = false;
            pendingReDefenseRemoval.value = null;
        },
    });
}

// --- Action Needed cards ---
const actionItems = computed(() => {
    const items: Array<{ key: string; label: string; count: number; section: SectionKey; color: string }> = [];

    // Pending member requests
    if (props.subject.pending_members?.length) {
        items.push({ key: 'pending', label: 'Pending member requests', count: props.subject.pending_members.length, section: 'members', color: 'amber' });
    }

    if (pendingRoundReviewerRequestCount.value) {
        items.push({ key: 'reviewer-requests', label: 'Reviewer assignment requests', count: pendingRoundReviewerRequestCount.value, section: 'rounds', color: 'amber' });
    }

    // Teams with no judge assigned
    const attemptsNoJudge = props.subject.defense_periods.reduce((total, period) =>
        total + period.attempts.filter((attempt) => activeAttemptAssignments(attempt).length === 0).length, 0);
    if (attemptsNoJudge) {
        items.push({ key: 'nojudge', label: 'Rounds without approved reviewers', count: attemptsNoJudge, section: 'rounds', color: 'orange' });
    }

    // Papers with no submitted review
    const papersNoScore = props.subject.papers.filter((p) => !p.reviews.some((r) => r.is_submitted));
    if (papersNoScore.length) {
        items.push({ key: 'noscore', label: 'Papers with no scores yet', count: papersNoScore.length, section: 'scores', color: 'red' });
    }

    // Ready to release: all reviews submitted, not yet released
    const readyToRelease = props.subject.papers.filter((p) => {
        const team = props.subject.teams.find((t) => t.id === p.team?.id);
        if (!team || team.results_released_at) return false;
        const judgeCount = teamAssignedReviewers(team).length;
        const submitted = p.reviews.filter((r) => r.is_submitted).length;
        return judgeCount > 0 && submitted >= judgeCount;
    });
    if (readyToRelease.length) {
        items.push({ key: 'ready', label: 'Teams ready to release', count: readyToRelease.length, section: 'scores', color: 'green' });
    }

    // Overdue judges: deadline passed, review not submitted
    const now = new Date();
    const overdueCount = props.subject.teams.reduce((n, t) => {
        if (!t.score_deadline_at || new Date(t.score_deadline_at) > now) return n;
        const paper = getTeamPaper(t.id);
        if (!paper) return n;
        const assigned = teamAssignedReviewers(t);
        const missing = assigned.filter((rv) => !paper.reviews.some((r) => r.is_submitted));
        return n + missing.length;
    }, 0);
    if (overdueCount) {
        items.push({ key: 'overdue', label: 'Overdue judges', count: overdueCount, section: 'scores', color: 'red' });
    }

    return items;
});

const actionColorClasses: Record<string, string> = {
    amber: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
    orange: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950/30 dark:text-orange-300',
    red: 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300',
    green: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
};

function getTeamPaper(teamId: number) {
    return props.subject.papers.find((p) => p.team?.id === teamId) ?? null;
}

type SectionKey = 'rounds' | 'papers' | 'teams' | 'members' | 'schedule' | 'scores';
const activeSection = ref<SectionKey>('members');
const sections: Array<{ key: SectionKey; label: string; icon: typeof FileText }> = [
    { key: 'members', label: 'Members', icon: Users },
    { key: 'teams', label: 'Teams', icon: UsersRound },
    { key: 'schedule', label: 'Schedule', icon: Calendar },
    { key: 'rounds', label: 'Evaluation Rounds', icon: ClipboardCheck },
    { key: 'papers', label: 'Papers', icon: FileText },
    { key: 'scores', label: 'Scores', icon: BarChart2 },
];

// --- Defense Schedule ---
const TEAM_COLORS = [
    { border: 'border-l-blue-500',    header: 'bg-blue-50/60 dark:bg-blue-950/20',    icon: 'text-blue-600 dark:text-blue-400',    dot: 'bg-blue-500' },
    { border: 'border-l-violet-500',  header: 'bg-violet-50/60 dark:bg-violet-950/20', icon: 'text-violet-600 dark:text-violet-400', dot: 'bg-violet-500' },
    { border: 'border-l-emerald-500', header: 'bg-emerald-50/60 dark:bg-emerald-950/20', icon: 'text-emerald-600 dark:text-emerald-400', dot: 'bg-emerald-500' },
    { border: 'border-l-amber-500',   header: 'bg-amber-50/60 dark:bg-amber-950/20',  icon: 'text-amber-600 dark:text-amber-400',  dot: 'bg-amber-500' },
    { border: 'border-l-rose-500',    header: 'bg-rose-50/60 dark:bg-rose-950/20',    icon: 'text-rose-600 dark:text-rose-400',    dot: 'bg-rose-500' },
    { border: 'border-l-cyan-500',    header: 'bg-cyan-50/60 dark:bg-cyan-950/20',    icon: 'text-cyan-600 dark:text-cyan-400',    dot: 'bg-cyan-500' },
    { border: 'border-l-orange-500',  header: 'bg-orange-50/60 dark:bg-orange-950/20', icon: 'text-orange-600 dark:text-orange-400', dot: 'bg-orange-500' },
];
function teamColor(index: number) { return TEAM_COLORS[index % TEAM_COLORS.length]; }

const scheduleDialogTeam = ref<ScheduleTeamData | null>(null);
const scheduleDialogOpen = computed({
    get: () => scheduleDialogTeam.value !== null,
    set: (v) => { if (!v) scheduleDialogTeam.value = null; },
});

const scheduleForm = useForm({
    defense_date: '',
    defense_time: '',
    defense_duration: '' as string | number,
    defense_room: '',
    paper_upload_deadline_at: '',
    score_deadline_at: '',
});

// Tracks whether the team already had a schedule before the dialog was opened
// (used to show "modified" vs "new" wording in the confirmation).
const scheduleWasSet = ref(false);

// Second-step confirmation dialog state
const scheduleConfirmOpen = ref(false);

function openScheduleDialog(team: ScheduleTeamData) {
    scheduleDialogTeam.value  = { ...team, save_target: 'team' };
    scheduleWasSet.value      = !!team.defense_date;
    scheduleForm.defense_date = team.defense_date ?? '';
    scheduleForm.defense_time = team.defense_time ? team.defense_time.slice(0, 5) : '';
    scheduleForm.defense_duration = team.defense_duration ?? '';
    scheduleForm.defense_room = team.defense_room ?? '';
    scheduleForm.paper_upload_deadline_at = '';
    scheduleForm.score_deadline_at = team.score_deadline_at
        ? team.score_deadline_at.slice(0, 16)
        : '';
}

function openRoundScheduleDialog(attempt: DefenseAttemptData | null, teamName: string) {
    if (!attempt || !selectedDefensePeriod.value) return;

    scheduleDialogTeam.value = {
        id: attempt.id,
        name: teamName,
        save_target: 'attempt',
        round_name: `${selectedDefensePeriod.value.name} • Round ${attempt.attempt_number}`,
        defense_date: attempt.defense_date,
        defense_time: attempt.defense_time,
        defense_duration: attempt.defense_duration,
        defense_room: attempt.defense_room,
        score_deadline_at: attempt.score_deadline_at,
    };
    scheduleWasSet.value = !!attempt.defense_date;
    scheduleForm.defense_date = attempt.defense_date ?? '';
    scheduleForm.defense_time = attempt.defense_time ? attempt.defense_time.slice(0, 5) : '';
    scheduleForm.defense_duration = attempt.defense_duration ?? '';
    scheduleForm.defense_room = attempt.defense_room ?? '';
    scheduleForm.paper_upload_deadline_at = attempt.paper_upload_deadline_at
        ? attempt.paper_upload_deadline_at.slice(0, 16)
        : '';
    scheduleForm.score_deadline_at = attempt.score_deadline_at
        ? attempt.score_deadline_at.slice(0, 16)
        : '';
}

// Called when the user clicks "Save Schedule" — opens the confirmation step.
function saveSchedule() {
    if (!scheduleDialogTeam.value) return;
    scheduleConfirmOpen.value = true;
}

// Called after the user confirms — actually submits the form.
function doSaveSchedule() {
    if (!scheduleDialogTeam.value) return;
    const actionUrl = scheduleDialogTeam.value.save_target === 'attempt'
        ? defenseAttemptUpdate.url(scheduleDialogTeam.value.id)
        : teamScheduleUpdate.url(scheduleDialogTeam.value.id);

    scheduleForm.patch(actionUrl, {
        onSuccess: () => {
            scheduleConfirmOpen.value = false;
            scheduleDialogTeam.value  = null;
        },
        onError: () => {
            scheduleConfirmOpen.value = false;
        },
    });
}

function formatDate(val: string | null): string {
    if (!val) return '—';
    return new Date(val).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(val: string | null): string {
    if (!val) return '—';
    return new Date(val).toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function addMinutes(time: string, minutes: number): string {
    const [h, m] = time.split(':').map(Number);
    const total = h * 60 + m + minutes;
    const hh = Math.floor(total / 60) % 24;
    const mm = total % 60;
    return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
}

// --- Scores ---
const releaseConfirmTeamId = ref<number | null>(null);

function releaseTeamScores(teamId: number) {
    router.post(teamReleaseScores.url(teamId), {}, {
        preserveScroll: true,
        onSuccess: () => { releaseConfirmTeamId.value = null; },
    });
}

const unlockReviewId = ref<number | null>(null);
const unlockForm = useForm({ reason: '' });
function submitUnlock(reviewId: number) {
    unlockForm.post(reviewUnlock.url(reviewId), {
        onSuccess: () => { unlockReviewId.value = null; unlockForm.reset(); },
    });
}

</script>

<template>
    <Head :title="subject.title" />

    <div class="flex flex-col gap-6 p-6">
        <div class="rounded-[1.75rem] border border-slate-200 bg-white/90 p-4 shadow-sm dark:border-slate-800 dark:bg-background/80">
            <!-- Back navigation -->
            <div class="mb-4 flex items-center gap-3">
                <Button variant="ghost" size="sm" as-child class="gap-1 text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-900">
                    <Link :href="subjectsIndex.url()">
                        <ArrowLeft class="h-4 w-4" />
                        Subjects
                    </Link>
                </Button>
            </div>

            <!-- Subject hero banner -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#3157f4] via-[#3345e5] to-[#4631cf] px-7 py-7 text-white shadow-sm">
                <div class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 rounded-full bg-white/10" />
                <div class="pointer-events-none absolute -bottom-10 right-28 h-28 w-28 rounded-full bg-white/10" />
                <div class="pointer-events-none absolute bottom-0 right-0 h-32 w-44 rounded-tl-full bg-white/5" />

                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white/20 shadow-inner ring-2 ring-white/25">
                            <BookOpen class="h-8 w-8 text-white" />
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ subject.title }}</h1>
                            <p v-if="subject.description" class="mt-1 max-w-3xl text-sm text-white/75">{{ subject.description }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-white/80">
                                <span>Taught by <strong class="text-white">{{ subject.teacher.name }}</strong></span>
                                <span class="h-1 w-1 rounded-full bg-white/40" />
                                <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white">Pass: {{ subject.passing_score }}%</span>
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="subject.require_approval
                                        ? 'bg-amber-300 text-amber-950'
                                        : 'bg-emerald-400 text-emerald-950'"
                                >
                                    {{ subject.require_approval ? 'Approval Required' : 'Auto Join' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button v-if="isOwnerOrAdmin" size="sm" class="gap-1.5 bg-white/20 text-white hover:bg-white/30" as-child>
                            <Link :href="subjectEdit.url(subject.id)">
                                <Pencil class="h-3.5 w-3.5" />
                                Edit
                            </Link>
                        </Button>

                        <Dialog v-if="isSubjectOwner" v-model:open="showDeleteDialog">
                            <DialogTrigger as-child>
                                <Button size="sm" class="gap-1.5 bg-rose-500 text-white hover:bg-rose-600">
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Delete
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Delete Subject</DialogTitle>
                                    <DialogDescription>
                                        Are you sure you want to delete <strong>"{{ subject.title }}"</strong>?
                                        This will remove all teams, papers, and reviews permanently.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <DialogClose as-child><Button variant="outline">Cancel</Button></DialogClose>
                                    <Button variant="destructive" @click="deleteSubject">Delete Subject</Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>

                        <Dialog v-if="canLeave" v-model:open="showLeaveDialog">
                            <DialogTrigger as-child>
                                <Button size="sm" class="gap-1.5 bg-white/20 text-white hover:bg-white/30">
                                    <LogOut class="h-3.5 w-3.5" />
                                    Leave
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Leave Subject</DialogTitle>
                                    <DialogDescription>
                                        Are you sure you want to leave <strong>"{{ subject.title }}"</strong>?
                                        You will lose access to all papers and teams in this subject.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <DialogClose as-child><Button variant="outline">Cancel</Button></DialogClose>
                                    <Button variant="destructive" @click="leaveSubject">Leave Subject</Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
            </div>

            <div
                class="mt-4 grid gap-3"
                :class="hasVisibleInviteCodes ? 'lg:grid-cols-[minmax(0,1fr)_minmax(340px,0.85fr)]' : 'lg:grid-cols-1'"
            >
                <!-- Stats cards -->
                <div
                    class="grid grid-cols-2 gap-2.5"
                    :class="hasVisibleInviteCodes ? '' : 'lg:grid-cols-4'"
                >
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 border-l-4 border-l-blue-500 bg-white px-3.5 py-2.5 shadow-sm dark:border-slate-800 dark:border-l-blue-500 dark:bg-background">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950/40">
                            <Users class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold leading-none text-blue-600 dark:text-blue-400">{{ stats.students }}</p>
                            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Students</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 border-l-4 border-l-violet-500 bg-white px-3.5 py-2.5 shadow-sm dark:border-slate-800 dark:border-l-violet-500 dark:bg-background">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-950/40">
                            <ShieldCheck class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold leading-none text-violet-600 dark:text-violet-400">{{ stats.reviewers }}</p>
                            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Reviewers</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 border-l-4 border-l-indigo-500 bg-white px-3.5 py-2.5 shadow-sm dark:border-slate-800 dark:border-l-indigo-500 dark:bg-background">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/40">
                            <FileText class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold leading-none text-indigo-600 dark:text-indigo-400">{{ stats.papers }}</p>
                            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Papers</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-slate-200 border-l-4 border-l-emerald-500 bg-white px-3.5 py-2.5 shadow-sm dark:border-slate-800 dark:border-l-emerald-500 dark:bg-background">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40">
                            <BarChart3 class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <p class="text-xl font-bold leading-none text-emerald-600 dark:text-emerald-400">{{ stats.reviewed }}%</p>
                            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Reviewed</p>
                        </div>
                    </div>
                </div>

                <!-- Invite Codes -->
                <div v-if="hasVisibleInviteCodes" class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-background">
                    <div class="flex items-center gap-2">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40">
                            <Copy class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <p class="text-sm font-semibold">Invite Codes</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <div v-if="subject.join_code" class="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50/60 px-3 py-2 dark:border-blue-800 dark:bg-blue-950/20">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-blue-500">Student</p>
                                <p class="font-mono text-base font-bold tracking-[0.25em] text-blue-700 dark:text-blue-300">{{ subject.join_code }}</p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button variant="ghost" size="sm" class="h-7 w-7 p-0 hover:bg-blue-100 dark:hover:bg-blue-900" title="Copy student code" @click="copyJoinCode">
                                    <Check v-if="codeCopied" class="h-3.5 w-3.5 text-emerald-600" />
                                    <Copy v-else class="h-3.5 w-3.5 text-blue-600" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 gap-1 px-2 text-[11px] font-semibold text-blue-700 hover:bg-blue-100 dark:text-blue-300 dark:hover:bg-blue-900"
                                    type="button"
                                    @click="resetStudentCodeConfirmOpen = true"
                                >
                                    <RefreshCw class="h-3 w-3" />
                                    Reset
                                </Button>
                            </div>
                        </div>
                        <div v-if="subject.reviewer_code" class="flex items-center justify-between rounded-lg border border-violet-200 bg-violet-50/60 px-3 py-2 dark:border-violet-800 dark:bg-violet-950/20">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-violet-500">Reviewer</p>
                                <p class="font-mono text-base font-bold tracking-[0.25em] text-violet-700 dark:text-violet-300">{{ subject.reviewer_code }}</p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button variant="ghost" size="sm" class="h-7 w-7 p-0 hover:bg-violet-100 dark:hover:bg-violet-900" title="Copy reviewer code" @click="copyReviewerCode">
                                    <Check v-if="reviewerCodeCopied" class="h-3.5 w-3.5 text-emerald-600" />
                                    <Copy v-else class="h-3.5 w-3.5 text-violet-600" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 gap-1 px-2 text-[11px] font-semibold text-violet-700 hover:bg-violet-100 dark:text-violet-300 dark:hover:bg-violet-900"
                                    type="button"
                                    @click="resetReviewerCodeConfirmOpen = true"
                                >
                                    <RefreshCw class="h-3 w-3" />
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Needed (instructor/admin only) -->
        <div v-if="isOwnerOrAdmin && actionItems.length > 0" class="flex flex-wrap gap-2">
            <button
                v-for="item in actionItems"
                :key="item.key"
                class="flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-medium transition-colors hover:opacity-80"
                :class="actionColorClasses[item.color]"
                @click="activeSection = item.section"
            >
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-current/20 text-[10px] font-bold">
                    {{ item.count }}
                </span>
                {{ item.label }}
            </button>
        </div>

        <Separator />

        <!-- Section tabs -->
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-1 rounded-lg border bg-muted/40 p-1 w-fit">
                <button
                    v-for="section in sections"
                    :key="section.key"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="activeSection === section.key ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="activeSection = section.key"
                >
                    <component :is="section.icon" class="h-3.5 w-3.5" />
                    {{ section.label }}
                </button>
            </div>

            <!-- Evaluation Rounds section -->
            <Card v-if="activeSection === 'rounds'" class="overflow-hidden">
                <CardHeader class="border-b bg-gradient-to-br from-[#24327a]/5 via-white to-white px-6 py-5 dark:from-[#24327a]/10 dark:via-background dark:to-background">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-[#24327a]/10 p-2 text-[#24327a]">
                                <ClipboardCheck class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-semibold text-foreground">
                                    Evaluation Rounds
                                </CardTitle>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Schedule each team, track document submissions, and approve reviewer requests in one place.
                                </p>
                            </div>
                        </div>
                        <div v-if="isOwnerOrAdmin" class="flex items-center gap-2">
                            <Button size="sm" variant="outline" class="gap-1.5" as-child>
                                <Link :href="rubricCreate.url(subject.id)">
                                    <Upload class="h-3.5 w-3.5" />
                                    Upload Rubric
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <!-- Period switcher -->
                    <div v-if="subject.defense_periods.length > 0" class="mt-5 flex flex-wrap items-center gap-2">
                        <button
                            v-for="period in subject.defense_periods"
                            :key="period.id"
                            type="button"
                            class="group flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition-all"
                            :class="selectedDefensePeriodId === period.id
                                ? 'border-[#24327a] bg-[#24327a] text-white shadow-sm'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-[#24327a]/40 hover:bg-[#24327a]/5 dark:border-slate-700 dark:bg-background dark:text-slate-200'"
                            @click="selectedDefensePeriodId = period.id"
                        >
                            <!-- Rubric status indicator -->
                            <Lock
                                v-if="period.rubric?.status === 'locked'"
                                class="h-3.5 w-3.5"
                                :class="selectedDefensePeriodId === period.id ? 'text-emerald-300' : 'text-emerald-600'"
                                :title="`${period.name} rubric is locked and ready for grading`"
                            />
                            <AlertTriangle
                                v-else-if="period.rubric"
                                class="h-3.5 w-3.5"
                                :class="selectedDefensePeriodId === period.id ? 'text-amber-300' : 'text-amber-600'"
                                :title="`${period.name} rubric needs to be locked before grading`"
                            />
                            <Upload
                                v-else
                                class="h-3.5 w-3.5"
                                :class="selectedDefensePeriodId === period.id ? 'text-white/70' : 'text-slate-400'"
                                :title="`${period.name} rubric not uploaded yet`"
                            />
                            <span>{{ period.name }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="selectedDefensePeriodId === period.id
                                    ? 'bg-white/20 text-white'
                                    : 'bg-slate-100 text-slate-600 group-hover:bg-[#24327a]/10 group-hover:text-[#24327a] dark:bg-slate-800 dark:text-slate-300'"
                            >
                                {{ periodScheduledCount(period) }}/{{ period.attempts.length }}
                            </span>
                        </button>
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <div v-if="!selectedDefensePeriod" class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <ClipboardCheck class="h-6 w-6 text-slate-400" />
                        </div>
                        <p class="mt-3 text-sm font-medium text-foreground">No evaluation rounds yet</p>
                        <p class="mt-1 text-xs text-muted-foreground">Create a round to start scheduling defenses.</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <!-- Stats summary bar -->
                        <div class="grid grid-cols-2 gap-px border-b bg-slate-100 sm:grid-cols-4 dark:bg-slate-800">
                            <div class="flex flex-col gap-1 bg-white px-5 py-3 dark:bg-background">
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total rounds</span>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xl font-bold text-foreground">{{ roundStats.total }}</span>
                                    <span v-if="roundStats.reDefenseCount > 0" class="text-xs font-medium text-[#24327a]">
                                        incl. {{ roundStats.reDefenseCount }} re-defense
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1 bg-white px-5 py-3 dark:bg-background">
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Scheduled</span>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xl font-bold" :class="roundStats.scheduled === roundStats.total ? 'text-emerald-600' : 'text-foreground'">
                                        {{ roundStats.scheduled }}
                                    </span>
                                    <span v-if="roundStats.awaiting > 0" class="text-xs font-medium text-amber-600">
                                        {{ roundStats.awaiting }} pending
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1 bg-white px-5 py-3 dark:bg-background">
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">PDFs submitted</span>
                                <span class="text-xl font-bold text-foreground">{{ roundStats.pdfsIn }}<span class="text-sm font-normal text-muted-foreground">/{{ roundStats.total }}</span></span>
                            </div>
                            <div class="flex flex-col gap-1 bg-white px-5 py-3 dark:bg-background">
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Reviewers assigned</span>
                                <span class="text-xl font-bold text-foreground">{{ roundStats.reviewersAssigned }}<span class="text-sm font-normal text-muted-foreground">/{{ roundStats.total }}</span></span>
                            </div>
                        </div>

                        <!-- Round meta strip -->
                        <div class="flex flex-col gap-3 border-b bg-slate-50/60 px-6 py-3 md:flex-row md:items-center md:justify-between dark:bg-slate-900/30">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-semibold text-foreground">{{ selectedDefensePeriod.name }}</h2>
                                <Badge variant="outline" :class="roundRubricClass(selectedDefensePeriod)">
                                    Rubric: {{ roundRubricLabel(selectedDefensePeriod) }}
                                </Badge>
                                <Badge variant="outline" class="border-slate-200 bg-white text-slate-700">
                                    Pass {{ Number(selectedDefensePeriod.passing_score).toFixed(0) }}
                                </Badge>
                            </div>
                            <Button
                                v-if="selectedDefensePeriod.rubric"
                                size="sm"
                                variant="ghost"
                                class="h-7 gap-1.5 text-xs"
                                as-child
                            >
                                <Link :href="rubricShow.url(selectedDefensePeriod.rubric.id)">
                                    <Eye class="h-3 w-3" />
                                    View Rubric
                                </Link>
                            </Button>
                        </div>

                        <table v-if="visibleRoundAttempts.length > 0" class="w-full min-w-[1050px] border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr class="bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-900/40 dark:text-slate-400">
                                    <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Team</th>
                                    <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Schedule</th>
                                    <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Document</th>
                                    <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Reviewers</th>
                                    <th class="border-b border-slate-200 px-6 py-3 text-right dark:border-slate-800">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="(group, gIdx) in groupedRoundAttempts" :key="'group-' + group.teamId">
                                    <!-- Spacer between team groups -->
                                    <tr v-if="gIdx > 0" aria-hidden="true">
                                        <td colspan="5" class="h-3 p-0"></td>
                                    </tr>
                                    <tr
                                        v-for="(attempt, idx) in group.attempts"
                                        :key="'round-attempt-' + attempt.id"
                                        class="align-top transition-colors"
                                        :class="attempt.attempt_type === 're_defense'
                                            ? 'bg-[#24327a]/[0.03] hover:bg-[#24327a]/[0.06]'
                                            : 'hover:bg-slate-50/80 dark:hover:bg-slate-900/40'"
                                    >
                                    <td class="relative px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'first')">
                                        <!-- Team accent bar — different color per team group, continuous across the team's rows -->
                                        <span
                                            class="pointer-events-none absolute left-0 w-1.5"
                                            :class="[
                                                teamColor(gIdx).dot,
                                                idx === 0 ? 'top-2 rounded-t-full' : 'top-0',
                                                idx === group.attempts.length - 1 ? 'bottom-2 rounded-b-full' : 'bottom-0',
                                            ]"
                                        />
                                        <!-- Branch arm (the L-shape) — re-defense rows only -->
                                        <CornerDownRight
                                            v-if="attempt.attempt_type === 're_defense'"
                                            class="absolute left-3 top-[1.05rem] h-4 w-4 text-[#24327a]/70"
                                        />
                                        <div class="flex flex-col gap-1" :class="attempt.attempt_type === 're_defense' ? 'pl-8' : 'pl-2'">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-foreground">{{ attempt.team.name }}</p>
                                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                                <Badge
                                                    v-if="attempt.attempt_type === 're_defense'"
                                                    variant="outline"
                                                    class="gap-1 border-[#24327a]/30 bg-[#24327a]/10 font-semibold text-[#24327a]"
                                                >
                                                    <RefreshCw class="h-3 w-3" />
                                                    Round {{ attempt.attempt_number }}
                                                </Badge>
                                                <Badge v-else variant="outline" class="border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-900">
                                                    Round {{ attempt.attempt_number }}
                                                </Badge>
                                            </div>
                                            <p class="text-xs text-muted-foreground">
                                                {{ teamStudentMembers(attempt.team).map((member) => member.name).join(', ') || 'No student members' }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'middle')">
                                        <div v-if="attempt.defense_date" class="flex flex-col gap-1">
                                            <p class="font-medium">{{ formatDate(attempt.defense_date) }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ attempt.defense_time?.slice(0, 5) ?? 'Time not set' }}
                                                <template v-if="attempt.defense_time && attempt.defense_duration">
                                                    - {{ addMinutes(attempt.defense_time, attempt.defense_duration) }}
                                                </template>
                                            </p>
                                            <p class="flex items-center gap-1 text-xs text-muted-foreground">
                                                <MapPin class="h-3 w-3" />
                                                {{ attempt.defense_room || 'Room not set' }}
                                            </p>
                                        </div>
                                        <Badge v-else variant="outline" class="border-amber-200 bg-amber-50 text-amber-700">
                                            Schedule needed
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'middle')">
                                        <div v-if="attemptPaper(attempt)" class="flex flex-col gap-2">
                                            <Badge variant="outline" class="w-fit border-emerald-200 bg-emerald-50 text-emerald-700">
                                                PDF submitted
                                            </Badge>
                                            <Button size="sm" variant="ghost" class="h-7 w-fit gap-1 px-2 text-xs" as-child>
                                                <Link :href="paperShow.url(attemptPaper(attempt)!.id)">
                                                    <Eye class="h-3 w-3" />
                                                    Open
                                                </Link>
                                            </Button>
                                        </div>
                                        <Badge v-else variant="outline" class="border-slate-200 bg-slate-50 text-slate-700">
                                            Waiting for PDF
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'middle')">
                                        <div class="flex flex-col gap-2">
                                            <div class="flex flex-wrap gap-1.5">
                                                <Badge variant="outline" class="border-emerald-200 bg-emerald-50 text-emerald-700">
                                                    {{ activeAttemptAssignments(attempt).length }} approved
                                                </Badge>
                                                <Badge variant="outline" class="border-amber-200 bg-amber-50 text-amber-700">
                                                    {{ pendingAttemptAssignments(attempt).length }} pending
                                                </Badge>
                                            </div>
                                            <div v-if="activeAttemptAssignments(attempt).length" class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="assignment in activeAttemptAssignments(attempt)"
                                                    :key="'active-' + assignment.id"
                                                    class="rounded-full bg-[#24327a]/10 px-2 py-0.5 text-xs font-medium text-[#24327a]"
                                                >
                                                    {{ assignment.reviewer.name }}
                                                </span>
                                            </div>
                                            <div v-if="isOwnerOrAdmin && pendingAttemptAssignments(attempt).length" class="flex flex-col gap-1.5">
                                                <div
                                                    v-for="assignment in pendingAttemptAssignments(attempt)"
                                                    :key="'pending-' + assignment.id"
                                                    class="flex items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs"
                                                >
                                                    <span class="font-medium text-amber-900">{{ assignment.reviewer.name }}</span>
                                                    <span class="flex items-center gap-1">
                                                        <Button size="sm" class="h-6 px-2 text-xs" @click="approveReviewerAssignment(attempt, assignment.reviewer_id)">
                                                            Approve
                                                        </Button>
                                                        <Button size="sm" variant="ghost" class="h-6 px-2 text-xs text-destructive" @click="rejectReviewerAssignment(attempt, assignment.reviewer_id)">
                                                            Reject
                                                        </Button>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'last')">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <Button
                                                v-if="isOwnerOrAdmin"
                                                size="sm"
                                                :variant="attempt.defense_date ? 'outline' : 'default'"
                                                class="h-8 gap-1.5"
                                                :class="!attempt.defense_date ? 'bg-[#24327a] text-white hover:bg-[#1b255c]' : ''"
                                                @click="openRoundScheduleDialog(attempt, attempt.team.name)"
                                            >
                                                <Calendar class="h-3.5 w-3.5" />
                                                {{ attempt.defense_date ? 'Edit Schedule' : 'Set Schedule' }}
                                            </Button>
                                            <Button
                                                v-if="isOwnerOrAdmin && selectedDefensePeriod"
                                                size="sm"
                                                variant="outline"
                                                class="h-8 gap-1.5 text-amber-700"
                                                @click="requestAddReDefense(selectedDefensePeriod.id, attempt.team_id, attempt.team.name, selectedDefensePeriod.name)"
                                            >
                                                <RefreshCw class="h-3.5 w-3.5" />
                                                Add Re-defense
                                            </Button>
                                            <Button
                                                v-if="isOwnerOrAdmin && attempt.attempt_type === 're_defense'"
                                                size="sm"
                                                variant="outline"
                                                class="h-8 gap-1.5 border-red-200 text-red-700 hover:bg-red-50"
                                                @click="requestRemoveReDefense(attempt.id, attempt.team.name, `Round ${attempt.attempt_number}`)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                Remove
                                            </Button>
                                            <template v-if="!isOwnerOrAdmin && isSubjectReviewer">
                                                <Button
                                                    v-if="!currentReviewerAssignment(attempt)"
                                                    size="sm"
                                                    class="h-8 gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]"
                                                    @click="requestReviewerAssignment(attempt)"
                                                >
                                                    <Send class="h-3.5 w-3.5" />
                                                    Request to Review
                                                </Button>
                                                <Badge v-else-if="currentReviewerAssignment(attempt)?.status === 'pending'" variant="outline" class="border-amber-200 bg-amber-50 text-amber-700">
                                                    Waiting approval
                                                </Badge>
                                                <Badge v-else-if="currentReviewerAssignment(attempt)?.status === 'rejected'" variant="outline" class="border-red-200 bg-red-50 text-red-700">
                                                    Request rejected
                                                </Badge>
                                                <Button
                                                    v-else-if="currentReviewerAssignment(attempt)?.status === 'active' && attemptPaper(attempt)"
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-8 gap-1.5"
                                                    as-child
                                                >
                                                    <Link :href="paperShow.url(attemptPaper(attempt)!.id)">
                                                        <Eye class="h-3.5 w-3.5" />
                                                        Open Team Room
                                                    </Link>
                                                </Button>
                                                <Badge v-else variant="outline" class="border-slate-200 bg-slate-50 text-slate-700">
                                                    Waiting for PDF
                                                </Badge>
                                            </template>
                                            <Button
                                                v-if="!isOwnerOrAdmin && isStudent && attempt.team.members.some((member) => member.id === user?.id)"
                                                size="sm"
                                                variant="outline"
                                                class="h-8 gap-1.5"
                                                as-child
                                            >
                                                <Link :href="paperCreate.url(subject.id, { query: { defense_attempt_id: attempt.id } })">
                                                    <Upload class="h-3.5 w-3.5" />
                                                    Upload PDF
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <div v-else class="px-6 py-16 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                <UsersRound class="h-6 w-6 text-slate-400" />
                            </div>
                            <p class="mt-3 text-sm font-medium text-foreground">No teams in this round</p>
                            <p class="mt-1 text-xs text-muted-foreground">Create a team under the Teams tab to start scheduling.</p>
                            <Button
                                v-if="isOwnerOrAdmin"
                                size="sm"
                                variant="outline"
                                class="mt-4 gap-1.5"
                                @click="activeSection = 'teams'"
                            >
                                <UsersRound class="h-3.5 w-3.5" />
                                Go to Teams
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Papers section -->
            <Card v-if="activeSection === 'papers'" class="overflow-hidden">
                <CardHeader class="border-b bg-gradient-to-br from-[#24327a]/5 via-white to-white px-6 py-5 dark:from-[#24327a]/10 dark:via-background dark:to-background">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-[#24327a]/10 p-2 text-[#24327a]">
                                <FileText class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-semibold text-foreground">
                                    Submitted Papers
                                </CardTitle>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ subject.papers.length }} paper{{ subject.papers.length === 1 ? '' : 's' }} submitted across all teams.
                                </p>
                            </div>
                        </div>
                        <Button v-if="isStudent" size="sm" class="gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]" as-child>
                            <Link :href="paperCreate.url(subject.id)">
                                <Upload class="h-3.5 w-3.5" />
                                Submit Paper
                            </Link>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="subject.papers.length === 0" class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <FileText class="h-6 w-6 text-slate-400" />
                        </div>
                        <p class="mt-3 text-sm font-medium text-foreground">No papers submitted yet</p>
                        <p v-if="isStudent" class="mt-1 text-xs text-muted-foreground">Submit a paper from your team to get started.</p>
                        <p v-else class="mt-1 text-xs text-muted-foreground">Papers will appear here once teams start submitting.</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1120px] border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-900/40 dark:text-slate-400">
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Team</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Round</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Document</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Reviews</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Score</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Status</th>
                                <th class="border-b border-slate-200 px-6 py-3 text-right dark:border-slate-800">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(group, gIdx) in groupedPapers" :key="'paper-group-' + group.teamId">
                                <tr v-if="gIdx > 0" aria-hidden="true">
                                    <td colspan="7" class="h-3 p-0"></td>
                                </tr>
                                <tr
                                    v-for="(paper, idx) in group.papers"
                                    :key="'paper-row-' + paper.id"
                                    class="align-top transition-colors"
                                    :class="isPaperFollowUpRow(paper, idx)
                                        ? 'bg-[#24327a]/[0.03] hover:bg-[#24327a]/[0.06]'
                                        : 'hover:bg-slate-50/80 dark:hover:bg-slate-900/40'"
                                >
                                    <td class="relative px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'first')">
                                        <span
                                            class="pointer-events-none absolute left-0 w-1.5"
                                            :class="[
                                                teamColor(gIdx).dot,
                                                idx === 0 ? 'top-2 rounded-t-full' : 'top-0',
                                                idx === group.papers.length - 1 ? 'bottom-2 rounded-b-full' : 'bottom-0',
                                            ]"
                                        />
                                        <CornerDownRight
                                            v-if="isPaperFollowUpRow(paper, idx)"
                                            class="absolute left-3 top-[1.05rem] h-4 w-4 text-[#24327a]/70"
                                        />
                                        <div class="flex flex-col gap-1" :class="isPaperFollowUpRow(paper, idx) ? 'pl-8' : 'pl-2'">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-foreground">{{ paper.team.name }}</p>
                                                <Badge
                                                    v-if="paperAttemptInfo(paper)?.attempt.attempt_type === 're_defense'"
                                                    variant="outline"
                                                    class="gap-1 border-[#24327a]/30 bg-[#24327a]/10 font-semibold text-[#24327a]"
                                                >
                                                    <RefreshCw class="h-3 w-3" />
                                                    Re-defense
                                                </Badge>
                                            </div>
                                            <p class="text-xs text-muted-foreground">{{ paperMemberNames(paper) }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                        <div class="flex flex-col gap-1.5">
                                            <Badge variant="outline" class="w-fit border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900">
                                                {{ paperRoundName(paper) }}
                                            </Badge>
                                            <span class="text-xs text-muted-foreground">{{ paperAttemptLabel(paper) }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                        <div class="flex flex-col gap-2">
                                            <Badge variant="outline" class="w-fit border-emerald-200 bg-emerald-50 text-emerald-700">
                                                PDF submitted
                                            </Badge>
                                            <Button size="sm" variant="ghost" class="h-7 w-fit gap-1 px-2 text-xs" as-child>
                                                <Link :href="paperShow.url(paper.id)">
                                                    <Eye class="h-3 w-3" />
                                                    Open
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                        <Badge
                                            variant="outline"
                                            :class="submittedReviewCount(paper) === paper.reviews.length && paper.reviews.length > 0
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-amber-200 bg-amber-50 text-amber-700'"
                                        >
                                            {{ submittedReviewCount(paper) }} / {{ paper.reviews.length }} submitted
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                        <span v-if="paper.final_score !== null" class="font-semibold">
                                            {{ paper.final_score }} / 100
                                            <Star v-if="paper.final_score >= subject.passing_score" class="ml-0.5 inline h-3 w-3 text-amber-500" />
                                        </span>
                                        <span v-else class="text-muted-foreground">—</span>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                        <Badge :variant="paperBadgeVariant(paper.visibility_status)">
                                            {{ statusLabel(paper.visibility_status) }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 text-right" :class="groupCellClasses(idx, group.papers.length, 'last')">
                                        <Button variant="outline" size="sm" class="h-8 gap-1.5" as-child>
                                            <Link :href="paperShow.url(paper.id)">
                                                <Eye class="h-3.5 w-3.5" />
                                                View Paper
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

            <!-- Teams section -->
            <div v-if="activeSection === 'teams'" class="flex flex-col gap-4">
                <Card class="overflow-hidden">
                    <CardHeader class="border-b bg-gradient-to-br from-[#24327a]/5 via-white to-white px-6 py-5 dark:from-[#24327a]/10 dark:via-background dark:to-background">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-[#24327a]/10 p-2 text-[#24327a]">
                                    <UsersRound class="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle class="text-base font-semibold text-foreground">
                                        Teams
                                    </CardTitle>
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        {{ subject.teams.length }} team{{ subject.teams.length === 1 ? '' : 's' }} in this subject. Solo and pair setups both supported.
                                    </p>
                                </div>
                            </div>
                            <form v-if="isOwnerOrAdmin" @submit.prevent="createTeam" class="flex flex-col gap-1">
                                <div class="flex gap-2">
                                    <Input v-model="teamForm.name" placeholder="New team name" class="h-9 max-w-xs" required />
                                    <Button type="submit" size="sm" class="h-9 gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]" :disabled="teamForm.processing">
                                        <UserPlus class="h-3.5 w-3.5" />
                                        Create Team
                                    </Button>
                                </div>
                                <p v-if="teamForm.errors.name" class="text-xs text-destructive">{{ teamForm.errors.name }}</p>
                            </form>
                        </div>
                    </CardHeader>
                </Card>

                <div v-if="subject.teams.length === 0" class="rounded-xl border bg-card px-6 py-16 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                        <UsersRound class="h-6 w-6 text-slate-400" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-foreground">No teams yet</p>
                    <p class="mt-1 text-xs text-muted-foreground">Use the form above to create the first team.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="(team, idx) in (isOwnerOrAdmin ? subject.teams : myTeams)"
                        :key="team.id"
                        class="overflow-hidden border-l-4"
                        :class="teamColor(idx).border"
                    >
                        <CardHeader class="pb-2" :class="teamColor(idx).header">
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                    <UsersRound class="h-4 w-4" :class="teamColor(idx).icon" />
                                    {{ team.name }}
                                    <span class="rounded-full px-1.5 py-0.5 text-[11px] font-medium" :class="teamColor(idx).header">{{ team.members.length }}</span>
                                </CardTitle>
                                <div class="flex items-center gap-1">
                                    <Button
                                        v-if="!isOwnerOrAdmin && isStudent && team.members.some((m) => m.id === user?.id)"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 w-7 p-0 text-muted-foreground hover:text-amber-600"
                                        title="Leave team"
                                        @click="leaveTeam(team.id)"
                                    >
                                        <LogOut class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button v-if="isOwnerOrAdmin" variant="ghost" size="sm" class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive" @click="deleteTeam(team.id)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="flex flex-col gap-2 pt-3">
                            <div class="flex flex-col divide-y">
                                <div v-for="member in teamStudentMembers(team)" :key="member.id" class="flex items-center justify-between py-1.5">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                                            :class="teamColor(idx).header + ' ' + teamColor(idx).icon"
                                        >
                                            {{ member.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">{{ member.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ member.email }}</p>
                                        </div>
                                    </div>
                                    <Button v-if="isOwnerOrAdmin" variant="ghost" size="sm" class="h-6 w-6 p-0 text-muted-foreground hover:text-destructive" @click="removeMember(team.id, member.id)">
                                        <UserMinus class="h-3 w-3" />
                                    </Button>
                                </div>
                                <p v-if="teamStudentMembers(team).length === 0" class="py-2 text-xs text-muted-foreground">No student members yet.</p>
                            </div>

                            <!-- Assigned reviewers -->
                            <div class="flex flex-col gap-1.5 rounded-md border bg-violet-50/30 p-2 dark:bg-violet-950/20">
                                <div class="flex items-center gap-1.5">
                                    <ShieldCheck class="h-3.5 w-3.5 text-violet-600" />
                                    <p class="text-xs font-semibold text-violet-700 dark:text-violet-300">
                                        Assigned Reviewers ({{ teamAssignedReviewers(team).length }})
                                    </p>
                                </div>
                                <div v-if="teamAssignedReviewers(team).length > 0" class="flex flex-col divide-y divide-violet-100 dark:divide-violet-900">
                                    <div v-for="rv in teamAssignedReviewers(team)" :key="'tr-' + rv.id" class="flex items-center justify-between py-1.5">
                                        <div class="flex items-center gap-2">
                                            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-violet-100 text-xs font-medium text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                                {{ rv.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium">{{ rv.name }}</p>
                                                <p class="text-xs text-muted-foreground">{{ rv.email }}</p>
                                            </div>
                                        </div>
                                        <Button v-if="isSubjectOwner" variant="ghost" size="sm" class="h-6 w-6 p-0 text-muted-foreground hover:text-destructive" title="Unassign reviewer" @click="removeMember(team.id, rv.id)">
                                            <UserMinus class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">No reviewers assigned to this team.</p>
                            </div>

                            <!-- Add Member Dialog -->
                            <Dialog v-if="isOwnerOrAdmin" :open="addMemberTeamId === team.id" @update:open="(v) => { if (!v) { addMemberTeamId = null; memberForm.reset(); memberForm.clearErrors(); } else { addMemberTeamId = team.id; } }">
                                <DialogTrigger as-child>
                                    <Button variant="outline" size="sm" class="w-full gap-1.5 text-xs" @click="addMemberTeamId = team.id">
                                        <UserPlus class="h-3.5 w-3.5" />
                                        Add Member
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-md overflow-hidden p-0">
                                    <!-- Success banner -->
                                    <transition
                                        enter-active-class="transition-all duration-300 ease-out"
                                        enter-from-class="-translate-y-full opacity-0"
                                        enter-to-class="translate-y-0 opacity-100"
                                        leave-active-class="transition-all duration-200 ease-in"
                                        leave-from-class="translate-y-0 opacity-100"
                                        leave-to-class="-translate-y-full opacity-0"
                                    >
                                        <div v-if="memberAddSuccess" class="flex items-center gap-2 bg-emerald-500 px-4 py-2.5 text-sm font-medium text-white">
                                            <CheckCircle2 class="h-4 w-4 shrink-0" />
                                            <span>Added — {{ memberAddSuccess.name }}</span>
                                        </div>
                                    </transition>

                                    <div class="p-6 flex flex-col gap-4">
                                    <DialogHeader>
                                        <DialogTitle>Add Member to {{ team.name }}</DialogTitle>
                                        <DialogDescription>
                                            Pick from enrolled students or invite someone new by email. Reviewers request teams from Evaluation Rounds.
                                        </DialogDescription>
                                    </DialogHeader>

                                        <!-- Quick-pick: enrolled students not in any team -->
                                        <div v-if="unassignedStudents.length > 0" class="flex flex-col gap-2">
                                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Available Students</p>
                                            <div class="max-h-40 overflow-y-auto rounded-lg border divide-y">
                                                <button
                                                    v-for="student in unassignedStudents"
                                                    :key="student.id"
                                                    type="button"
                                                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                                                    @click="addExistingMember(team.id, student.id)"
                                                >
                                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                                        {{ student.name.charAt(0).toUpperCase() }}
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="truncate font-medium">{{ student.name }}</p>
                                                        <p class="truncate text-xs text-muted-foreground">{{ student.email }}</p>
                                                    </div>
                                                    <UserPlus class="ml-auto h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                                </button>
                                            </div>
                                        </div>
                                        <div v-else class="rounded-lg bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                            All enrolled students are already assigned to teams.
                                        </div>

                                        <Separator />

                                        <!-- Invite by email -->
                                        <form @submit.prevent="addMemberByEmail(team.id)" class="flex flex-col gap-3">
                                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Or Invite by Email</p>
                                            <p class="text-xs text-muted-foreground">New users will be automatically enrolled as students in this subject.</p>
                                            <div class="flex gap-2">
                                                <Input v-model="memberForm.email" type="email" placeholder="student@email.com" class="h-8 text-sm" required />
                                                <Button type="submit" size="sm" class="h-8 shrink-0 gap-1" :disabled="memberForm.processing">
                                                    <UserPlus class="h-3.5 w-3.5" />
                                                    Add
                                                </Button>
                                            </div>
                                            <p v-if="memberForm.errors.email" class="text-xs text-destructive">{{ memberForm.errors.email }}</p>
                                        </form>
                                    </div>
                                </DialogContent>
                            </Dialog>

                            <div v-if="isStudent" class="flex items-center justify-between border-t pt-2">
                                <span class="text-xs text-muted-foreground">Score</span>
                                <span v-if="getTeamPaper(team.id)?.final_score != null" class="text-sm font-semibold">
                                    {{ getTeamPaper(team.id)?.final_score }} / 100
                                </span>
                                <span v-else class="text-xs text-muted-foreground">Not graded yet</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Members section -->
            <div v-if="activeSection === 'members'" class="flex flex-col gap-4">
                <!-- Section header overview -->
                <Card class="overflow-hidden">
                    <CardHeader class="border-b bg-gradient-to-br from-[#24327a]/5 via-white to-white px-6 py-5 dark:from-[#24327a]/10 dark:via-background dark:to-background">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-[#24327a]/10 p-2 text-[#24327a]">
                                <Users class="h-5 w-5" />
                            </div>
                            <div class="flex-1">
                                <CardTitle class="text-base font-semibold text-foreground">
                                    Members
                                </CardTitle>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ subject.students.length }} student{{ subject.students.length === 1 ? '' : 's' }} enrolled · {{ subject.reviewers.length }} reviewer{{ subject.reviewers.length === 1 ? '' : 's' }} assigned
                                    <span v-if="(subject.pending_members?.length ?? 0) > 0" class="ml-2 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        <Clock class="h-3 w-3" />
                                        {{ subject.pending_members.length }} pending request{{ subject.pending_members.length === 1 ? '' : 's' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <div class="grid gap-4 lg:grid-cols-2">
                <!-- Pending Requests -->
                <Card v-if="isSubjectOwner && (subject.pending_members?.length ?? 0) > 0" class="lg:col-span-2 overflow-hidden border-l-4 border-l-amber-500">
                    <CardHeader class="border-b bg-amber-50/40 pb-3 dark:bg-amber-950/20">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                <Clock class="h-4 w-4 text-amber-600" />
                                Pending Requests ({{ subject.pending_members.length }})
                            </CardTitle>
                            <Badge variant="outline" class="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                                Requires approval
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col divide-y">
                            <div v-for="member in subject.pending_members" :key="member.id" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                        {{ member.user.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">{{ member.user.name }}</p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-2">
                                            <p class="text-xs text-muted-foreground">{{ member.user.email }}</p>
                                            <Badge variant="secondary" class="py-0 text-xs">
                                                {{
                                                    member.role === 'student'
                                                        ? 'Student'
                                                        : member.role_label ?? committeeRoleLabels[member.role] ?? member.role
                                                }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button size="sm" class="gap-1.5" @click="approvePendingMember(member)">
                                        <Check class="h-3.5 w-3.5" />
                                        Approve
                                    </Button>
                                    <Button size="sm" variant="destructive" class="gap-1.5" @click="rejectPendingMember(member)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Reject
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Students -->
                <Card class="border-l-4 border-l-blue-500">
                    <CardHeader class="pb-3 bg-blue-50/50 dark:bg-blue-950/20">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                <Users class="h-4 w-4 text-blue-600" />
                                Students ({{ subject.students.length }})
                            </CardTitle>

                            <!-- Enroll Student Dialog -->
                            <Dialog v-if="isSubjectOwner" v-model:open="showEnrollStudentDialog">
                                <DialogTrigger as-child>
                                    <Button size="sm" variant="outline" class="gap-1.5 text-xs">
                                        <UserPlus class="h-3.5 w-3.5" />
                                        Enroll Student
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>Enroll Student</DialogTitle>
                                        <DialogDescription>
                                            Add a registered user to this subject as a student. They can also self-enroll using the classroom join code.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form @submit.prevent="addStudent" class="flex flex-col gap-4">
                                        <div class="flex flex-col gap-1.5">
                                            <Input v-model="studentForm.email" type="email" placeholder="student@email.com" required />
                                            <p v-if="studentForm.errors.email" class="text-xs text-destructive">{{ studentForm.errors.email }}</p>
                                        </div>
                                        <DialogFooter>
                                            <DialogClose as-child>
                                                <Button variant="outline">Cancel</Button>
                                            </DialogClose>
                                            <Button type="submit" :disabled="studentForm.processing" class="gap-1.5">
                                                <UserPlus class="h-3.5 w-3.5" />
                                                Enroll
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="subject.students.length > 0" class="flex flex-col divide-y">
                            <div v-for="student in subject.students" :key="student.id" class="flex items-center justify-between py-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                        {{ student.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">{{ student.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ student.email }}</p>
                                    </div>
                                </div>
                                <Button v-if="isSubjectOwner" variant="ghost" size="sm" class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive" @click="requestRemoveStudent(student)">
                                    <UserMinus class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center py-6 text-center">
                            <Users class="mb-2 h-7 w-7 text-muted-foreground/40" />
                            <p class="text-sm text-muted-foreground">No students enrolled yet.</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">Share the classroom code or enroll students manually.</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Reviewers -->
                <Card class="border-l-4 border-l-violet-500">
                    <CardHeader class="pb-3 bg-violet-50/50 dark:bg-violet-950/20">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                <ShieldCheck class="h-4 w-4 text-violet-600" />
                                Reviewers ({{ subject.reviewers.length }})
                            </CardTitle>

                            <!-- Invite Reviewer Dialog -->
                            <Dialog v-if="isSubjectOwner" v-model:open="showInviteReviewerDialog">
                                <DialogTrigger as-child>
                                    <Button size="sm" variant="outline" class="gap-1.5 text-xs">
                                        <UserPlus class="h-3.5 w-3.5" />
                                        Invite Reviewer
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>Invite Reviewer</DialogTitle>
                                        <DialogDescription>
                                            Add a committee member to review papers in this subject. If they don't have an account yet, they'll receive an invitation email.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form @submit.prevent="addReviewer" class="flex flex-col gap-4">
                                        <div
                                            v-if="subject.reviewer_code"
                                            class="rounded-xl border border-[#212e70]/15 bg-[#212e70]/[0.04] p-3"
                                        >
                                            <div class="mb-2 flex items-center justify-between gap-3">
                                                <div>
                                                    <p class="text-xs font-semibold uppercase tracking-widest text-[#212e70]/70">Reviewer Join Code</p>
                                                    <p class="text-xs text-muted-foreground">Share this with registered reviewers who will join themselves.</p>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0 hover:bg-[#212e70]/10" type="button" @click="copyReviewerCode">
                                                        <Check v-if="reviewerCodeCopied" class="h-4 w-4 text-emerald-600" />
                                                        <Copy v-else class="h-4 w-4 text-[#212e70]" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 gap-1 px-2 text-xs font-semibold text-[#212e70] hover:bg-[#212e70]/10"
                                                        type="button"
                                                        @click="showInviteReviewerDialog = false; resetReviewerCodeConfirmOpen = true"
                                                    >
                                                        <RefreshCw class="h-3.5 w-3.5" />
                                                        Reset
                                                    </Button>
                                                </div>
                                            </div>
                                            <div class="rounded-lg border border-[#212e70]/15 bg-white px-3 py-2 dark:bg-background">
                                                <p class="font-mono text-lg font-bold tracking-[0.28em] text-[#212e70]">{{ subject.reviewer_code }}</p>
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-1.5">
                                            <label class="text-sm font-semibold text-foreground">Committee Role <span class="text-destructive">*</span></label>
                                            <Select v-model="reviewerForm.committee_role" required>
                                                <SelectTrigger class="h-12 w-full rounded-xl border-[#212e70]/15 bg-background px-4 text-base shadow-sm">
                                                    <SelectValue placeholder="Select a role" />
                                                </SelectTrigger>
                                                <SelectContent class="rounded-xl">
                                                    <SelectItem value="advisor">Advisor</SelectItem>
                                                    <SelectItem value="fyp_instructor">FYP Instructor</SelectItem>
                                                    <SelectItem value="guest_panel">Guest Panel</SelectItem>
                                                    <SelectItem value="custom">Custom</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <p v-if="reviewerForm.errors.committee_role" class="text-xs text-destructive">{{ reviewerForm.errors.committee_role }}</p>
                                        </div>
                                        <div class="flex flex-col gap-1.5">
                                            <label class="text-sm font-semibold text-foreground">Email Address <span class="text-destructive">*</span></label>
                                            <Input v-model="reviewerForm.email" type="email" placeholder="reviewer@email.com" class="h-12 rounded-xl border-[#212e70]/15 px-4" required />
                                            <p v-if="reviewerForm.errors.email" class="text-xs text-destructive">{{ reviewerForm.errors.email }}</p>
                                        </div>
                                        <div v-if="reviewerNeedsCustomLabel" class="flex flex-col gap-1.5">
                                            <label class="text-sm font-semibold text-foreground">Role Label <span class="text-destructive">*</span></label>
                                            <Input v-model="reviewerForm.role_label" type="text" placeholder="e.g. External Examiner" maxlength="100" class="h-12 rounded-xl border-[#212e70]/15 px-4" required />
                                            <p v-if="reviewerForm.errors.role_label" class="text-xs text-destructive">{{ reviewerForm.errors.role_label }}</p>
                                        </div>
                                        <DialogFooter>
                                            <DialogClose as-child>
                                                <Button variant="outline">Cancel</Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                :disabled="reviewerForm.processing || !reviewerForm.committee_role || (reviewerNeedsCustomLabel && !reviewerForm.role_label)"
                                                class="gap-1.5"
                                            >
                                                <UserPlus class="h-3.5 w-3.5" />
                                                Invite
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="subject.reviewers.length > 0 || subject.pending_invitations.length > 0" class="flex flex-col divide-y">
                            <div v-for="reviewer in subject.reviewers" :key="'r-' + reviewer.id" class="flex items-center justify-between py-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-violet-100 text-xs font-medium text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                        {{ reviewer.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">{{ reviewer.name }}</p>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <p class="text-xs text-muted-foreground">{{ reviewer.email }}</p>
                                            <span class="rounded-full border px-2 py-0.5 text-[11px] font-medium" :class="roleBadgeClass(reviewer.pivot.role)">
                                                {{ reviewer.pivot.role_label ?? committeeRoleLabels[reviewer.pivot.role] ?? reviewer.pivot.role }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <Button v-if="isSubjectOwner" variant="ghost" size="sm" class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive" @click="requestRemoveReviewer(reviewer)">
                                    <UserMinus class="h-3.5 w-3.5" />
                                </Button>
                            </div>

                            <!-- Pending invitations -->
                            <div v-for="invite in subject.pending_invitations" :key="'inv-' + invite.id" class="flex items-center justify-between py-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-amber-100 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                        <AlertTriangle class="h-3.5 w-3.5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-muted-foreground">{{ invite.email }}</p>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="text-xs text-muted-foreground">
                                                {{ invite.role_label ?? committeeRoleLabels[invite.committee_role] ?? invite.committee_role }}
                                            </span>
                                            <Badge variant="outline" class="text-xs py-0 border-amber-300 text-amber-600">Pending</Badge>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center py-6 text-center">
                            <ShieldCheck class="mb-2 h-7 w-7 text-muted-foreground/40" />
                            <p class="text-sm text-muted-foreground">No reviewers assigned yet.</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">Invite committee members to review student papers.</p>
                        </div>
                    </CardContent>
                </Card>
                </div>
            </div>

            <!-- Defense Schedule section -->
            <div v-if="activeSection === 'schedule'" class="flex flex-col gap-4">
                <Card class="overflow-hidden">
                    <CardHeader class="border-b bg-gradient-to-br from-[#24327a]/5 via-white to-white px-6 py-5 dark:from-[#24327a]/10 dark:via-background dark:to-background">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-[#24327a]/10 p-2 text-[#24327a]">
                                <Calendar class="h-5 w-5" />
                            </div>
                            <div class="flex-1">
                                <CardTitle class="text-base font-semibold text-foreground">
                                    Defense Schedule
                                </CardTitle>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    <template v-if="subject.teams.length > 0">
                                        {{ subject.teams.filter((t) => !!t.defense_date).length }} of {{ subject.teams.length }} teams scheduled.
                                    </template>
                                    <template v-else>
                                        Schedule each team's defense once teams are created.
                                    </template>
                                    Click a card to edit the date, time, and venue.
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <Card v-if="subject.teams.length === 0" class="overflow-hidden">
                    <CardContent class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <Calendar class="h-6 w-6 text-slate-400" />
                        </div>
                        <p class="mt-3 text-sm font-medium text-foreground">No teams yet</p>
                        <p class="mt-1 text-xs text-muted-foreground">Create teams under the Teams tab first.</p>
                        <Button
                            v-if="isOwnerOrAdmin"
                            size="sm"
                            variant="outline"
                            class="mt-4 gap-1.5"
                            @click="activeSection = 'teams'"
                        >
                            <UsersRound class="h-3.5 w-3.5" />
                            Go to Teams
                        </Button>
                    </CardContent>
                </Card>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="(team, idx) in (isOwnerOrAdmin ? subject.teams : myTeams)"
                        :key="'sch-' + team.id"
                        class="overflow-hidden border-l-4"
                        :class="teamColor(idx).border"
                    >
                        <!-- Card header with tinted background -->
                        <CardHeader class="pb-2" :class="teamColor(idx).header">
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                    <UsersRound class="h-4 w-4" :class="teamColor(idx).icon" />
                                    {{ team.name }}
                                </CardTitle>
                                <Button
                                    v-if="isOwnerOrAdmin"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 gap-1.5 text-xs"
                                    :class="teamColor(idx).icon"
                                    @click="openScheduleDialog(team)"
                                >
                                    <Pencil class="h-3 w-3" />
                                    {{ team.defense_date ? 'Edit' : 'Set Schedule' }}
                                </Button>
                            </div>
                        </CardHeader>

                        <CardContent class="pt-3">
                            <div v-if="team.defense_date" class="flex flex-col gap-2">
                                <!-- Date & Time block -->
                                <div class="flex items-start gap-2.5 rounded-lg border px-3 py-2.5" :class="teamColor(idx).header">
                                    <Calendar class="mt-0.5 h-4 w-4 shrink-0" :class="teamColor(idx).icon" />
                                    <div>
                                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Defense Date & Time</p>
                                        <p class="text-sm font-bold">{{ formatDate(team.defense_date) }}</p>
                                        <p v-if="team.defense_time" class="text-xs text-muted-foreground">
                                            {{ team.defense_time.slice(0, 5) }}
                                            <template v-if="team.defense_duration">
                                                — {{ addMinutes(team.defense_time, team.defense_duration) }}
                                                <span class="ml-1 opacity-70">({{ team.defense_duration }} min)</span>
                                            </template>
                                        </p>
                                    </div>
                                </div>

                                <!-- Info rows -->
                                <div class="flex flex-col gap-1 px-0.5">
                                    <!-- Room -->
                                    <div class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs">
                                        <MapPin class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                        <span class="text-muted-foreground">Room / Venue</span>
                                        <span class="ml-auto font-medium">{{ team.defense_room ?? '—' }}</span>
                                    </div>
                                    <!-- Score deadline -->
                                    <div class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs">
                                        <Clock class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                        <span class="text-muted-foreground">Score deadline</span>
                                        <span
                                            class="ml-auto font-medium"
                                            :class="team.score_deadline_at && new Date(team.score_deadline_at) < new Date() ? 'text-red-600 dark:text-red-400' : ''"
                                        >{{ formatDateTime(team.score_deadline_at) }}</span>
                                    </div>
                                    <!-- Results status -->
                                    <div class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs">
                                        <CheckCircle2 class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                        <span class="text-muted-foreground">Results</span>
                                        <span class="ml-auto">
                                            <Badge
                                                class="text-[11px]"
                                                :class="team.results_released_at
                                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200'
                                                    : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300 border-amber-200'"
                                                variant="outline"
                                            >
                                                {{ team.results_released_at ? 'Released' : 'Not released' }}
                                            </Badge>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state -->
                            <div v-else class="flex flex-col items-center gap-2 py-5 text-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full" :class="teamColor(idx).header">
                                    <Calendar class="h-5 w-5" :class="teamColor(idx).icon" />
                                </div>
                                <p class="text-xs text-muted-foreground">No schedule set yet.</p>
                                <Button v-if="isOwnerOrAdmin" variant="outline" size="sm" class="mt-1 h-7 gap-1.5 text-xs" @click="openScheduleDialog(team)">
                                    <Pencil class="h-3 w-3" />
                                    Set Schedule
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

            </div>

            <!-- Scores section -->
            <div v-if="activeSection === 'scores'" class="flex flex-col gap-4">
                <Card v-if="subject.papers.length === 0" class="overflow-hidden">
                    <CardContent class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <BarChart2 class="h-6 w-6 text-slate-400" />
                        </div>
                        <p class="mt-3 text-sm font-medium text-foreground">No scores yet</p>
                        <p class="mt-1 text-xs text-muted-foreground">Scores appear here once teams submit papers and reviewers grade them.</p>
                    </CardContent>
                </Card>

                <Card v-else class="overflow-hidden">
                    <CardHeader class="border-b bg-gradient-to-br from-[#24327a]/5 via-white to-white px-6 py-5 dark:from-[#24327a]/10 dark:via-background dark:to-background">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-[#24327a]/10 p-2 text-[#24327a]">
                                <BarChart2 class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-semibold text-foreground">
                                    Team Scores Overview
                                </CardTitle>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Track each team's score and release results to students when ready.
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1120px] border-separate border-spacing-0 text-sm">
                                <thead>
                                    <tr class="bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-900/40 dark:text-slate-400">
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Team</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Round</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Reviews</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Score</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Deadline</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Results</th>
                                        <th class="border-b border-slate-200 px-6 py-3 text-right dark:border-slate-800">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="(group, gIdx) in groupedPapers" :key="'score-group-' + group.teamId">
                                        <tr v-if="gIdx > 0" aria-hidden="true">
                                            <td colspan="7" class="h-3 p-0"></td>
                                        </tr>
                                        <tr
                                            v-for="(paper, idx) in group.papers"
                                            :key="'score-row-' + paper.id"
                                            class="align-top transition-colors"
                                            :class="isPaperFollowUpRow(paper, idx)
                                                ? 'bg-[#24327a]/[0.03] hover:bg-[#24327a]/[0.06]'
                                                : 'hover:bg-slate-50/80 dark:hover:bg-slate-900/40'"
                                        >
                                            <td class="relative px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'first')">
                                                <span
                                                    class="pointer-events-none absolute left-0 w-1.5"
                                                    :class="[
                                                        teamColor(gIdx).dot,
                                                        idx === 0 ? 'top-2 rounded-t-full' : 'top-0',
                                                        idx === group.papers.length - 1 ? 'bottom-2 rounded-b-full' : 'bottom-0',
                                                    ]"
                                                />
                                                <CornerDownRight
                                                    v-if="isPaperFollowUpRow(paper, idx)"
                                                    class="absolute left-3 top-[1.05rem] h-4 w-4 text-[#24327a]/70"
                                                />
                                                <div class="flex flex-col gap-1" :class="isPaperFollowUpRow(paper, idx) ? 'pl-8' : 'pl-2'">
                                                    <p class="font-semibold text-foreground">{{ paper.team.name }}</p>
                                                    <p class="text-xs text-muted-foreground">{{ paperMemberNames(paper) }}</p>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                                <div class="flex flex-col gap-1.5">
                                                    <Badge variant="outline" class="w-fit border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900">
                                                        {{ paperRoundName(paper) }}
                                                    </Badge>
                                                    <span class="text-xs text-muted-foreground">{{ paperAttemptLabel(paper) }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                                <div class="flex flex-col gap-1">
                                                    <span class="text-sm font-semibold">{{ submittedReviewCount(paper) }} / {{ paper.reviews.length }}</span>
                                                    <span class="text-xs text-muted-foreground">submitted reviews</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                                <span v-if="paper.final_score !== null" class="font-semibold">
                                                    {{ paper.final_score }} / 100
                                                    <Star v-if="paper.final_score >= subject.passing_score" class="ml-0.5 inline h-3 w-3 text-amber-500" />
                                                </span>
                                                <span v-else class="text-muted-foreground">—</span>
                                            </td>
                                            <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                                <span
                                                    class="text-xs"
                                                    :class="isPaperScoreDeadlineOverdue(paper)
                                                        ? 'font-semibold text-red-600 dark:text-red-400'
                                                        : 'text-muted-foreground'"
                                                >
                                                    {{ formatDateTime(paperScoreDeadline(paper)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                                <Badge v-if="isPaperResultReleased(paper)" variant="default" class="text-xs">Released</Badge>
                                                <Badge v-else-if="paper.visibility_status === 'published'" variant="secondary" class="text-xs">Published</Badge>
                                                <Badge v-else variant="outline" class="text-xs">Pending</Badge>
                                            </td>
                                            <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'last')">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <Button variant="outline" size="sm" class="h-8 gap-1.5" as-child>
                                                        <Link :href="paperShow.url(paper.id)">
                                                            <Eye class="h-3.5 w-3.5" />
                                                            View Paper
                                                        </Link>
                                                    </Button>
                                                    <template v-if="isOwnerOrAdmin && !isPaperResultReleased(paper)">
                                                        <Button
                                                            v-if="releaseConfirmTeamId !== paper.team.id"
                                                            size="sm"
                                                            class="h-8 gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]"
                                                            @click="releaseConfirmTeamId = paper.team.id"
                                                        >
                                                            <Send class="h-3.5 w-3.5" />
                                                            Release
                                                        </Button>
                                                        <div v-else class="flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1">
                                                            <span class="text-xs text-amber-700">
                                                                {{ submittedReviewCount(paper) < paper.reviews.length ? 'Not all submitted. ' : '' }}Confirm?
                                                            </span>
                                                            <Button size="sm" class="h-6 gap-1 text-xs" @click="releaseTeamScores(paper.team.id)">Yes</Button>
                                                            <Button size="sm" variant="ghost" class="h-6 text-xs" @click="releaseConfirmTeamId = null">No</Button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Review lock/unlock panel (instructor only) -->
                <Card v-if="isOwnerOrAdmin" class="overflow-hidden">
                    <CardHeader class="border-b bg-gradient-to-br from-[#24327a]/5 via-white to-white px-6 py-5 dark:from-[#24327a]/10 dark:via-background dark:to-background">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-[#24327a]/10 p-2 text-[#24327a]">
                                <Lock class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle class="text-base font-semibold text-foreground">
                                    Review Lock Status
                                </CardTitle>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Unlock a submitted review to let the judge edit it. All unlocks are logged.
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="subject.papers.every((p) => p.reviews.length === 0)" class="px-6 py-12 text-center">
                            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                <Lock class="h-5 w-5 text-slate-400" />
                            </div>
                            <p class="mt-2 text-sm font-medium text-foreground">No reviews submitted yet</p>
                            <p class="mt-1 text-xs text-muted-foreground">Reviews will appear here once judges start grading.</p>
                        </div>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:border-slate-800 dark:bg-slate-900/40 dark:text-slate-400">
                                    <th class="px-6 py-3">Team</th>
                                    <th class="px-6 py-3">Judge</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">Score</th>
                                    <th class="px-6 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template v-for="paper in subject.papers" :key="'lr-' + paper.id">
                                    <tr
                                        v-for="review in paper.reviews"
                                        :key="'rv-' + review.id"
                                        class="transition-colors hover:bg-muted/70"
                                    >
                                        <td class="px-6 py-3 font-medium">{{ paper.team.name }}</td>
                                        <td class="px-6 py-3 text-muted-foreground">Review #{{ review.id }}</td>
                                        <td class="px-6 py-3">
                                            <Badge
                                                :variant="review.is_submitted ? 'default' : 'outline'"
                                                class="text-xs"
                                            >
                                                {{ review.is_submitted ? 'Submitted' : 'Draft' }}
                                            </Badge>
                                        </td>
                                        <td class="px-6 py-3 text-xs">
                                            <span v-if="review.scores_json?.length">
                                                {{ review.scores_json.length }} criteria scored
                                            </span>
                                            <span v-else class="text-muted-foreground">—</span>
                                        </td>
                                        <td class="px-6 py-3">
                                            <template v-if="review.is_submitted">
                                                <template v-if="unlockReviewId !== review.id">
                                                    <Button size="sm" variant="outline" class="h-7 gap-1 text-xs text-amber-600" @click="unlockReviewId = review.id; unlockForm.reset()">
                                                        <Unlock class="h-3 w-3" />
                                                        Unlock
                                                    </Button>
                                                </template>
                                                <div v-else class="flex flex-col gap-1.5">
                                                    <Input v-model="unlockForm.reason" type="text" placeholder="Reason for unlock…" class="h-7 text-xs" required />
                                                    <div class="flex gap-1">
                                                        <Button size="sm" class="h-6 text-xs gap-1" :disabled="unlockForm.processing || !unlockForm.reason" @click="submitUnlock(review.id)">
                                                            <Check class="h-3 w-3" />
                                                            Confirm
                                                        </Button>
                                                        <Button size="sm" variant="ghost" class="h-6 text-xs" @click="unlockReviewId = null">Cancel</Button>
                                                    </div>
                                                    <p v-if="unlockForm.errors.reason" class="text-xs text-destructive">{{ unlockForm.errors.reason }}</p>
                                                </div>
                                            </template>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <ConfirmDialog
        v-model:open="removeConfirmOpen"
        title="Remove Member"
        :description="removeConfirmDescription"
        cancel-text="Cancel"
        confirm-text="Yes, Remove"
        @confirm="confirmRemoveMember"
    />
    <ConfirmDialog
        v-model:open="resetStudentCodeConfirmOpen"
        title="Reset Student Join Code"
        description="Are you sure? The current student join code will stop working immediately. Students must use the new code to join this subject."
        cancel-text="Cancel"
        confirm-text="Yes, Reset"
        @confirm="resetStudentJoinCode"
    />
    <ConfirmDialog
        v-model:open="resetReviewerCodeConfirmOpen"
        title="Reset Reviewer Join Code"
        description="Are you sure? The current reviewer join code will stop working immediately. Reviewers must use the new code and select their committee role again."
        cancel-text="Cancel"
        confirm-text="Yes, Reset"
        @confirm="resetReviewerJoinCode"
    />
    <ConfirmDialog
        v-model:open="addReDefenseConfirmOpen"
        title="Add Re-defense Round?"
        :description="addReDefenseDescription"
        cancel-text="Cancel"
        confirm-text="Yes, Add Re-defense"
        @confirm="confirmAddReDefense"
    />
    <ConfirmDialog
        v-model:open="removeReDefenseConfirmOpen"
        title="Remove Re-defense Round?"
        :description="removeReDefenseDescription"
        cancel-text="Keep It"
        confirm-text="Yes, Remove"
        @confirm="confirmRemoveReDefense"
    />

    <!-- Schedule Edit Dialog (top-level so it works from any tab) -->
    <Dialog v-model:open="scheduleDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Calendar class="h-4 w-4 text-blue-600" />
                    {{ scheduleWasSet ? 'Edit Defense Schedule' : 'Set Defense Schedule' }}
                </DialogTitle>
                <DialogDescription>
                    Configure the defense date, time, venue, and score deadline for
                    <strong>{{ scheduleDialogTeam?.name }}</strong>
                    <span v-if="scheduleDialogTeam?.round_name"> in {{ scheduleDialogTeam.round_name }}</span>.
                </DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="saveSchedule">
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium">Defense Date</label>
                        <Input v-model="scheduleForm.defense_date" type="date" />
                        <p v-if="scheduleForm.errors.defense_date" class="text-xs text-destructive">{{ scheduleForm.errors.defense_date }}</p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium">Start Time</label>
                        <Input v-model="scheduleForm.defense_time" type="time" />
                        <p v-if="scheduleForm.errors.defense_time" class="text-xs text-destructive">{{ scheduleForm.errors.defense_time }}</p>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium">Duration <span class="font-normal text-muted-foreground">(minutes)</span></label>
                    <Input v-model="scheduleForm.defense_duration" type="number" min="5" max="480" placeholder="e.g. 60" />
                    <p class="text-xs text-muted-foreground">The end time will be calculated automatically from the start time + duration.</p>
                    <p v-if="scheduleForm.errors.defense_duration" class="text-xs text-destructive">{{ scheduleForm.errors.defense_duration }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium">Room / Venue</label>
                    <Input v-model="scheduleForm.defense_room" type="text" placeholder="e.g. Lab 3, Block A" />
                    <p v-if="scheduleForm.errors.defense_room" class="text-xs text-destructive">{{ scheduleForm.errors.defense_room }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium">Paper Upload Deadline</label>
                    <Input v-model="scheduleForm.paper_upload_deadline_at" type="datetime-local" />
                    <p class="text-xs text-muted-foreground">Students can upload or replace the PDF before this deadline.</p>
                    <p v-if="scheduleForm.errors.paper_upload_deadline_at" class="text-xs text-destructive">{{ scheduleForm.errors.paper_upload_deadline_at }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium">Judge Score Deadline</label>
                    <Input v-model="scheduleForm.score_deadline_at" type="datetime-local" />
                    <p class="text-xs text-muted-foreground">Judges must submit their review before this date and time.</p>
                    <p v-if="scheduleForm.errors.score_deadline_at" class="text-xs text-destructive">{{ scheduleForm.errors.score_deadline_at }}</p>
                </div>
                <DialogFooter class="mt-2">
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" :disabled="scheduleForm.processing" class="gap-1.5">
                        <Check class="h-3.5 w-3.5" />
                        {{ scheduleForm.processing ? 'Saving…' : 'Save Schedule' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Schedule Confirmation Dialog (second step) -->
    <Dialog v-model:open="scheduleConfirmOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Send class="h-4 w-4" :class="scheduleWasSet ? 'text-amber-600' : 'text-blue-600'" />
                    {{ scheduleWasSet ? 'Confirm Schedule Change' : 'Confirm & Notify' }}
                </DialogTitle>
                <DialogDescription>
                    Please review the schedule details before saving.
                    An email notification will be sent to all team members.
                </DialogDescription>
            </DialogHeader>

            <div class="rounded-xl border bg-muted/40 p-4 text-sm space-y-2">
                <div class="flex items-center gap-2 font-semibold text-base">
                    <span class="h-2 w-2 rounded-full" :class="scheduleWasSet ? 'bg-amber-500' : 'bg-blue-500'" />
                    {{ scheduleDialogTeam?.name }}
                </div>
                <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm mt-2">
                    <div>
                        <p class="text-xs text-muted-foreground">Date</p>
                        <p class="font-medium">{{ scheduleForm.defense_date ? formatDate(scheduleForm.defense_date) : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Time</p>
                        <p class="font-medium">{{ scheduleForm.defense_time || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Duration</p>
                        <p class="font-medium">{{ scheduleForm.defense_duration ? scheduleForm.defense_duration + ' min' : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Room / Venue</p>
                        <p class="font-medium">{{ scheduleForm.defense_room || '—' }}</p>
                    </div>
                </div>
            </div>

            <div
                class="flex items-start gap-2.5 rounded-lg border px-3 py-2.5 text-xs"
                :class="scheduleWasSet
                    ? 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300'
                    : 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300'"
            >
                <AlertTriangle v-if="scheduleWasSet" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                <Send v-else class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                <span v-if="scheduleWasSet">
                    This is a <strong>schedule change</strong>. All team members and reviewers will
                    receive an email noting that the defense schedule has been updated to the
                    new date and time above.
                </span>
                <span v-else>
                    All team members and reviewers will receive a notification email with the
                    defense schedule details above.
                </span>
            </div>

            <DialogFooter class="mt-1 gap-2">
                <Button variant="outline" @click="scheduleConfirmOpen = false">
                    Go back
                </Button>
                <Button
                    :disabled="scheduleForm.processing"
                    :class="scheduleWasSet ? 'bg-amber-600 hover:bg-amber-700 text-white border-0' : ''"
                    class="gap-1.5"
                    @click="doSaveSchedule"
                >
                    <Send class="h-3.5 w-3.5" />
                    {{ scheduleForm.processing ? 'Saving…' : (scheduleWasSet ? 'Confirm Change & Notify' : 'Confirm & Notify') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
