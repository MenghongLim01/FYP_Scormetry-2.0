<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    ArrowLeft, BookOpen, Users, FileText, ClipboardCheck, Pencil, Upload, UserMinus, UserPlus,
    UsersRound, ShieldCheck, Trash2, LogOut, Copy, Check, BarChart3, Star, Eye, AlertTriangle,
    Clock, Calendar, Lock, Unlock, Send, BarChart2, CheckCircle2, MapPin, RefreshCw, CornerDownRight,
    X, ArrowDownUp, MoreHorizontal, Search,
} from 'lucide-vue-next';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InfoTip from '@/components/InfoTip.vue';
import { addMinutesToClockTime, formatClockTime, formatDateTimeWithAmPm } from '@/lib/utils';
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
    approveAllReviewerRequests as approveAllReviewerRequestsAction,
    destroy as subjectDestroy, leave as subjectLeave,
} from '@/actions/App/Http/Controllers/SubjectController';
import { create as rubricCreate, show as rubricShow, edit as rubricEdit } from '@/actions/App/Http/Controllers/RubricController';
import { create as paperCreate, show as paperShow } from '@/actions/App/Http/Controllers/PaperController';
import {
    store as teamStore, destroy as teamDestroy, leave as teamLeave,
    addMember as addMemberAction, removeMember as removeMemberAction,
    updateSchedule as teamScheduleUpdate, scores as teamScores,
    releaseScores as teamReleaseScores,
    setAdvisor as setAdvisorAction, removeAdvisor as removeAdvisorAction,
    requestAdvisorRemoval as requestAdvisorRemovalAction,
    requestMemberRemoval as requestMemberRemovalAction,
    approveTeamRequest as approveTeamRequestAction, rejectTeamRequest as rejectTeamRequestAction,
    updateTopic as updateTopicAction,
} from '@/actions/App/Http/Controllers/TeamController';
import {
    store as defenseAttemptStore,
    update as defenseAttemptUpdate,
    requestReviewer as requestAttemptReviewer,
    approveReviewer as approveAttemptReviewer,
    rejectReviewer as rejectAttemptReviewer,
    addReviewer as addAttemptReviewer,
    updateReviewerRole as updateAttemptReviewerRole,
    removeReviewer as removeAttemptReviewer,
} from '@/actions/App/Http/Controllers/DefenseAttemptController';
import { unlock as reviewUnlock } from '@/actions/App/Http/Controllers/ReviewController';

type UserData = { id: number; name: string; email: string };

type PaperData = {
    id: number;
    file_path: string;
    final_score: number | string | null;
    final_score_override?: number | string | null;
    visibility_status: string;
    team: { id: number; name: string; topic?: string | null; members: UserData[]; student_members?: UserData[] };
    reviews: Array<{
        id: number;
        is_submitted: boolean;
        auto_submitted_at: string | null;
        defense_attempt_reviewer_id: number | null;
        scores_json: Array<{ criteria: string; score: number }> | null;
        reviewer?: UserData | null;
    }>;
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

type AttemptReviewerRoleDraft = {
    committee_role: string;
    role_label: string;
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
    team: { id: number; name: string; topic?: string | null; members: UserData[]; student_members?: UserData[]; review_panel?: UserData[]; advisor?: UserData | null };
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
    topic?: string | null;
    save_target?: 'team' | 'attempt';
    round_name?: string;
    defense_date?: string | null;
    defense_time?: string | null;
    defense_duration?: number | null;
    defense_room?: string | null;
    paper_upload_deadline_at?: string | null;
    score_deadline_at?: string | null;
    results_released_at?: string | null;
    members?: UserData[];
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
            topic?: string | null;
            defense_date: string | null;
            defense_time: string | null;
            defense_duration: number | null;
            defense_room: string | null;
            score_deadline_at: string | null;
            results_released_at: string | null;
            members: UserData[];
            student_members?: UserData[];
            review_panel?: UserData[];
            advisor?: UserData | null;
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

// Human labels for stored role values. fyp_instructor and guest_panel are no longer
// selectable, but legacy records may still carry them — display them safely
// (guest_panel surfaces as the preferred "Custom role" wording).
const committeeRoleLabels: Record<string, string> = {
    advisor: 'Advisor',
    fyp_instructor: 'FYP Instructor',
    technical_examiner: 'Technical examiner',
    academic_examiner: 'Academic examiner',
    guest_panel: 'Custom role',
    custom: 'Custom role',
};

const attemptCommitteeRoleOptions = [
    { value: 'technical_examiner', label: 'Technical examiner' },
    { value: 'academic_examiner', label: 'Academic examiner' },
    { value: 'custom', label: 'Custom role' },
];

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
// Shared "Add Student" dialog reachable from the Defense Sessions / rounds table
// (the team cards have their own inline Add Student dialog).
const addStudentTeam = ref<{ id: number; name: string } | null>(null);

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

const teamForm = useForm({ name: '', topic: '' });
function createTeam() {
    teamForm.post(teamStore.url(props.subject.id), {
        onSuccess: () => teamForm.reset(),
    });
}

// --- Team topic / project title ---
const topicDialogTeam = ref<{ id: number; name: string; topic?: string | null } | null>(null);
const topicForm = useForm({ topic: '' });
function openTopicDialog(team: { id: number; name: string; topic?: string | null }) {
    topicForm.topic = team.topic ?? '';
    topicForm.clearErrors();
    topicDialogTeam.value = { id: team.id, name: team.name, topic: team.topic ?? null };
}
function submitTeamTopic() {
    if (!topicDialogTeam.value) return;

    topicForm.patch(updateTopicAction.url(topicDialogTeam.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            topicDialogTeam.value = null;
            topicForm.reset();
        },
    });
}

// --- Team advisor (set by email; advisor is listed on the team, NOT a judge) ---
const advisorDialogTeam = ref<{ id: number; name: string } | null>(null);
const advisorForm = useForm({ email: '' });
function openAdvisorDialog(team: { id: number; name: string }) {
    advisorForm.reset();
    advisorForm.clearErrors();
    advisorDialogTeam.value = { id: team.id, name: team.name };
}
function submitAdvisor() {
    if (!advisorDialogTeam.value) return;
    advisorForm.post(setAdvisorAction.url(advisorDialogTeam.value.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { advisorDialogTeam.value = null; advisorForm.reset(); },
    });
}
function removeAdvisor(teamId: number) {
    router.delete(removeAdvisorAction.url(teamId), { preserveScroll: true, preserveState: true });
}
// (advisor removal request is handled via the shared removalRequest confirm below)
// Students can't remove the advisor or a teammate directly — they send a request
// to the subject owner. A confirm dialog makes the action explicit.
type RemovalRequest = { teamId: number; kind: 'advisor' | 'member'; userId: number | null; name: string };
const removalRequest = ref<RemovalRequest | null>(null);
const removalRequestOpen = computed({
    get: () => removalRequest.value !== null,
    set: (v) => { if (!v) removalRequest.value = null; },
});
const removalRequestDescription = computed(() => {
    if (!removalRequest.value) return '';
    const target = removalRequest.value.kind === 'advisor' ? 'advisor ' : '';
    return `Send a request to the subject owner to remove ${target}${removalRequest.value.name} from this team? The subject owner will review and decide.`;
});
function openRemovalRequest(kind: 'advisor' | 'member', teamId: number, name: string, userId: number | null = null) {
    removalRequest.value = { teamId, kind, userId, name };
}
function confirmRemovalRequest() {
    const r = removalRequest.value;
    if (!r) return;
    const opts = {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { removalRequest.value = null; },
    };
    if (r.kind === 'advisor') {
        router.post(requestAdvisorRemovalAction.url(r.teamId), {}, opts);
    } else if (r.userId !== null) {
        router.post(requestMemberRemovalAction.url({ team: r.teamId, user: r.userId }), {}, opts);
    }
}

// Pending removal requests (so we can show "Removal pending" + owner approve/reject)
function advisorRemovalRequest(team: { requests?: Array<{ id: number; role: string; user?: { id: number } | null }> }) {
    return (team.requests ?? []).find((r) => r.role === 'remove_advisor') ?? null;
}
function memberRemovalRequest(team: { requests?: Array<{ id: number; role: string; user?: { id: number } | null }> }, memberId: number) {
    return (team.requests ?? []).find((r) => r.role === 'remove_member' && r.user?.id === memberId) ?? null;
}
function approveTeamRequest(requestId: number) {
    router.post(approveTeamRequestAction.url(requestId), {}, { preserveScroll: true, preserveState: true });
}
function rejectTeamRequest(requestId: number) {
    router.delete(rejectTeamRequestAction.url(requestId), { preserveScroll: true, preserveState: true });
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

// Removing a student from a team requires confirmation.
type TeamMemberRemoval = { teamId: number; userId: number; name: string } | null;
const removeMemberConfirmOpen = ref(false);
const pendingMemberRemoval = ref<TeamMemberRemoval>(null);
const removeMemberConfirmTitle = computed(() =>
    pendingMemberRemoval.value ? 'Remove team member' : '',
);
const removeMemberConfirmDescription = computed(() => {
    if (!pendingMemberRemoval.value) return '';

    return `Remove ${pendingMemberRemoval.value.name} from this team? This action cannot be undone.`;
});

function requestRemoveMember(teamId: number, member: { id: number; name: string }) {
    pendingMemberRemoval.value = { teamId, userId: member.id, name: member.name };
    removeMemberConfirmOpen.value = true;
}

function confirmRemoveTeamMember() {
    if (!pendingMemberRemoval.value) return;

    router.delete(removeMemberAction.url({ team: pendingMemberRemoval.value.teamId, user: pendingMemberRemoval.value.userId }), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            removeMemberConfirmOpen.value = false;
            pendingMemberRemoval.value = null;
        },
    });
}
// Leaving a team requires confirmation.
const leaveTeamConfirm = ref<{ id: number; name: string } | null>(null);
const leaveTeamConfirmOpen = computed({
    get: () => leaveTeamConfirm.value !== null,
    set: (v) => { if (!v) leaveTeamConfirm.value = null; },
});
const leaveTeamDescription = computed(() =>
    leaveTeamConfirm.value
        ? `Leave ${leaveTeamConfirm.value.name}? You'll be removed from this team and lose access to its defense sessions. You can join or create a team again afterwards.`
        : '',
);
function requestLeaveTeam(team: { id: number; name: string }) {
    leaveTeamConfirm.value = { id: team.id, name: team.name };
}
function confirmLeaveTeam() {
    if (!leaveTeamConfirm.value) return;
    router.delete(teamLeave.url(leaveTeamConfirm.value.id), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { leaveTeamConfirm.value = null; },
    });
}

// Deleting a whole team requires confirmation.
const deleteTeamConfirmOpen = ref(false);
const pendingTeamDeletion = ref<{ id: number; name: string } | null>(null);
const deleteTeamConfirmDescription = computed(() =>
    pendingTeamDeletion.value
        ? `Delete ${pendingTeamDeletion.value.name}? This permanently removes the team along with its members, documents, and reviews. This action cannot be undone.`
        : '',
);

function requestDeleteTeam(team: { id: number; name: string }) {
    pendingTeamDeletion.value = { id: team.id, name: team.name };
    deleteTeamConfirmOpen.value = true;
}

function confirmDeleteTeam() {
    if (!pendingTeamDeletion.value) return;

    router.delete(teamDestroy.url(pendingTeamDeletion.value.id), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            deleteTeamConfirmOpen.value = false;
            pendingTeamDeletion.value = null;
        },
    });
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
        preserveState: true,
        onFinish: () => {
            removeConfirmOpen.value = false;
            pendingRemoval.value = null;
        },
    });
}

// Students enrolled in the subject but not yet assigned to any team
const unassignedStudents = computed(() => {
    const assignedIds = new Set(
        props.subject.teams.flatMap((t) => teamStudentMembers(t).map((m) => m.id)),
    );
    return props.subject.students.filter((s) => !assignedIds.has(s.id));
});

// Keep team cards student-focused. Judges/reviewers are assigned per defense
// session in Evaluation Rounds; they are not team members in the UX.
const ownerId = computed(() => props.subject.teacher.id);
const reviewerIdSet = computed(() => new Set(props.subject.reviewers.map((r) => r.id)));

// A team member is a "panel" member if they're a subject reviewer OR the subject
// owner (the FYP Instructor / Organizer, who is auto-added to every defense).
function isPanelMember(id: number): boolean {
    return reviewerIdSet.value.has(id) || id === ownerId.value;
}

// Students only — never the owner/organizer or any reviewer.
function teamStudentMembers(team: { members: UserData[]; student_members?: UserData[] }) {
    if (team.student_members) {
        return team.student_members;
    }

    return team.members.filter((m) => !isPanelMember(m.id));
}

// The full Review Panel for the subject: the Organizer (owner) first, then reviewers.
const reviewPanel = computed(() => {
    const panel: Array<{ id: number; name: string; email: string; roleLabel: string; isOwner: boolean }> = [];
    panel.push({
        id: props.subject.teacher.id,
        name: props.subject.teacher.name,
        email: '',
        roleLabel: 'FYP Instructor · Organizer',
        isOwner: true,
    });
    for (const r of props.subject.reviewers) {
        if (r.id === ownerId.value) continue;
        panel.push({
            id: r.id,
            name: r.name,
            email: r.email,
            roleLabel: r.pivot.role_label ?? committeeRoleLabels[r.pivot.role] ?? r.pivot.role,
            isOwner: false,
        });
    }
    return panel;
});

// --- Members search (case-insensitive: matches name or email regardless of case) ---
const memberSearch = ref('');
function matchesMemberSearch(...fields: Array<string | null | undefined>): boolean {
    const q = memberSearch.value.trim().toLowerCase();
    if (!q) return true;
    return fields.some((f) => (f ?? '').toLowerCase().includes(q));
}
const filteredStudents = computed(() =>
    props.subject.students.filter((s) => matchesMemberSearch(s.name, s.email)),
);
const filteredReviewPanel = computed(() =>
    reviewPanel.value.filter((m) => matchesMemberSearch(m.name, m.email, m.roleLabel)),
);

const rubricStatusColors: Record<string, string> = {
    uploaded: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950 dark:text-blue-300',
    pending_verification: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950 dark:text-amber-300',
    locked: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-950 dark:text-green-300',
};

function roleBadgeClass(role: string): string {
    if (role === 'fyp_instructor') return 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300';
    if (role === 'advisor')        return 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300';
    if (role === 'technical_examiner') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
    if (role === 'academic_examiner')  return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300';
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
    props.subject.teams.filter((team) => teamStudentMembers(team).some((m) => m.id === user.value?.id)),
);

const visibleTeamCards = computed(() => {
    if (isOwnerOrAdmin.value || isStudent.value) {
        return props.subject.teams;
    }

    return myTeams.value;
});

function isCurrentStudentTeam(team: { members: UserData[]; student_members?: UserData[] }): boolean {
    return teamStudentMembers(team).some((member) => member.id === user.value?.id);
}

function canManageStudentTeam(team: { members: UserData[]; student_members?: UserData[] }): boolean {
    return isOwnerOrAdmin.value || isCurrentStudentTeam(team);
}

// Remember which defense period (Midterm / Final / …) is selected so it survives
// navigating away (e.g. viewing the rubric) and coming back, instead of snapping
// back to the first period.
const periodStorageKey = `scormetry:subject:${props.subject.id}:defensePeriod`;
function initialDefensePeriodId(): number | null {
    if (typeof sessionStorage !== 'undefined') {
        const stored = Number(sessionStorage.getItem(periodStorageKey));
        if (stored && props.subject.defense_periods.some((p) => p.id === stored)) {
            return stored;
        }
    }
    return props.subject.defense_periods[0]?.id ?? null;
}
const selectedDefensePeriodId = ref<number | null>(initialDefensePeriodId());
watch(selectedDefensePeriodId, (id) => {
    if (typeof sessionStorage !== 'undefined' && id != null) {
        sessionStorage.setItem(periodStorageKey, String(id));
    }
});
const selectedDefensePeriod = computed(() =>
    props.subject.defense_periods.find((period) => period.id === selectedDefensePeriodId.value)
    ?? props.subject.defense_periods[0]
    ?? null,
);
const visibleRoundAttempts = computed(() => {
    const attempts = selectedDefensePeriod.value?.attempts ?? [];

    return attempts;
});

function canViewAttemptDocument(attempt: DefenseAttemptData): boolean {
    return isOwnerOrAdmin.value
        || currentReviewerAssignment(attempt)?.status === 'active'
        || isCurrentStudentTeam(attempt.team);
}

function attemptForTeam(teamId: number): DefenseAttemptData | null {
    return selectedDefensePeriod.value?.attempts.find((attempt) => attempt.team_id === teamId) ?? null;
}

const roundStats = computed(() => {
    const attempts = visibleRoundAttempts.value;
    const total = attempts.length;
    const scheduled = attempts.filter((a) => !!a.defense_date).length;
    const pdfsIn = attempts.filter((a) => attemptTurnedInPaper(a) !== null).length;
    const reviewersAssigned = attempts.filter((a) => activeAttemptAssignments(a).length > 0).length;
    const reDefenseCount = attempts.filter((a) => a.attempt_type === 're_defense').length;
    return { total, scheduled, awaiting: total - scheduled, pdfsIn, reviewersAssigned, reDefenseCount };
});

function periodScheduledCount(period: DefensePeriodData): number {
    return period.attempts.filter((a) => !!a.defense_date).length;
}

// --- Round table sorting ---
const roundSortKey = ref<'team' | 'date' | 'document' | 'reviewers'>('team');
const roundSortDir = ref<'asc' | 'desc'>('asc');
function toggleRoundSortDir() {
    roundSortDir.value = roundSortDir.value === 'asc' ? 'desc' : 'asc';
}

// --- Round table quick filters ---
type RoundFilter = 'all' | 'needs_schedule' | 'missing_document' | 'needs_judges';
const roundFilter = ref<RoundFilter>('all');

function attemptNeedsSchedule(a: DefenseAttemptData): boolean {
    return !a.defense_date;
}
function attemptMissingDocument(a: DefenseAttemptData): boolean {
    return attemptTurnedInPaper(a) === null;
}
function attemptNeedsJudges(a: DefenseAttemptData): boolean {
    return activeAttemptAssignments(a).length === 0 || pendingAttemptAssignments(a).length > 0;
}
function groupMatchesFilter(group: { attempts: DefenseAttemptData[] }, filter: RoundFilter): boolean {
    switch (filter) {
        case 'needs_schedule': return group.attempts.some(attemptNeedsSchedule);
        case 'missing_document': return group.attempts.some(attemptMissingDocument);
        case 'needs_judges': return group.attempts.some(attemptNeedsJudges);
        case 'all':
        default: return true;
    }
}

// Grouped + sorted, BEFORE the quick filter is applied (used for filter counts).
const baseRoundGroups = computed(() => {
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

    // Sort the team groups (re-defense rows always stay nested under their team).
    const dir = roundSortDir.value === 'asc' ? 1 : -1;
    const byName = (a: DefenseAttemptData, b: DefenseAttemptData) =>
        a.team.name.localeCompare(b.team.name, undefined, { numeric: true, sensitivity: 'base' });
    groups.sort((ga, gb) => {
        const a = ga.attempts[0];
        const b = gb.attempts[0];
        let cmp = 0;
        switch (roundSortKey.value) {
            case 'date': {
                const da = a.defense_date ? new Date(a.defense_date).getTime() : Number.POSITIVE_INFINITY;
                const db = b.defense_date ? new Date(b.defense_date).getTime() : Number.POSITIVE_INFINITY;
                cmp = da - db;
                break;
            }
            case 'document': {
                // Teams with a submitted document first (ascending).
                cmp = (attemptTurnedInPaper(a) ? 0 : 1) - (attemptTurnedInPaper(b) ? 0 : 1);
                break;
            }
            case 'reviewers': {
                // Fewest approved reviewers first (teams that still need judges).
                cmp = activeAttemptAssignments(a).length - activeAttemptAssignments(b).length;
                break;
            }
            case 'team':
            default:
                cmp = 0;
        }
        if (cmp === 0) cmp = byName(a, b);
        return cmp * dir;
    });

    return groups;
});

const groupedRoundAttempts = computed(() =>
    baseRoundGroups.value.filter((group) => groupMatchesFilter(group, roundFilter.value)),
);

const roundFilterCounts = computed(() => ({
    needs_schedule: baseRoundGroups.value.filter((g) => groupMatchesFilter(g, 'needs_schedule')).length,
    missing_document: baseRoundGroups.value.filter((g) => groupMatchesFilter(g, 'missing_document')).length,
    needs_judges: baseRoundGroups.value.filter((g) => groupMatchesFilter(g, 'needs_judges')).length,
}));

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
    return paperAttemptInfo(paper)?.period.name ?? 'Subject document';
}

function paperAttemptLabel(paper: PaperData): string {
    return defenseSessionLabel(paperAttemptInfo(paper)?.attempt.label ?? 'Latest defense session');
}

function defenseSessionLabel(label: string): string {
    return label.replace(/\bAttempt\b/gi, 'Defense Session');
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

    // Only turned-in documents appear in Documents/Scores — attached drafts stay
    // private to the team until the student turns them in.
    for (const paper of props.subject.papers.filter((p) => paperIsTurnedIn(p))) {
        const existing = groups.get(paper.team.id);

        if (existing) {
            existing.papers.push(paper);
        } else {
            groups.set(paper.team.id, {
                teamId: paper.team.id,
                teamName: paper.team.name,
                members: teamStudentMembers(paper.team),
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

// --- Sort for the Documents and Scores tables (both list teams from groupedPapers) ---
function groupScore(group: PaperGroup): number | null {
    const latest = group.papers[group.papers.length - 1];
    return paperScore(latest);
}
function sortPaperGroups(groups: PaperGroup[], key: 'team' | 'score' | 'status', dir: 'asc' | 'desc'): PaperGroup[] {
    const d = dir === 'asc' ? 1 : -1;
    const byName = (a: PaperGroup, b: PaperGroup) =>
        a.teamName.localeCompare(b.teamName, undefined, { numeric: true, sensitivity: 'base' });
    return [...groups].sort((a, b) => {
        let cmp = 0;
        if (key === 'score') {
            cmp = (groupScore(a) ?? Number.NEGATIVE_INFINITY) - (groupScore(b) ?? Number.NEGATIVE_INFINITY);
        } else if (key === 'status') {
            // Graded teams first (ascending).
            cmp = (groupScore(a) !== null ? 0 : 1) - (groupScore(b) !== null ? 0 : 1);
        }
        if (cmp === 0) cmp = byName(a, b);
        return cmp * d;
    });
}

const docSortKey = ref<'team' | 'score'>('team');
const docSortDir = ref<'asc' | 'desc'>('asc');
function toggleDocSortDir() { docSortDir.value = docSortDir.value === 'asc' ? 'desc' : 'asc'; }
const documentGroups = computed(() => sortPaperGroups(groupedPapers.value, docSortKey.value, docSortDir.value));

const scoreSortKey = ref<'team' | 'score' | 'status'>('team');
const scoreSortDir = ref<'asc' | 'desc'>('asc');
function toggleScoreSortDir() { scoreSortDir.value = scoreSortDir.value === 'asc' ? 'desc' : 'asc'; }
const scoreGroups = computed(() => sortPaperGroups(groupedPapers.value, scoreSortKey.value, scoreSortDir.value));

function paperMemberNames(paper: PaperData): string {
    return teamStudentMembers(paper.team).map((member) => member.name).join(', ') || 'No student members';
}

function paperScore(paper: PaperData | null | undefined): number | null {
    const rawScore = paper?.final_score_override ?? paper?.final_score;

    if (rawScore === null || rawScore === undefined) {
        return null;
    }

    const score = Number(rawScore);

    return Number.isFinite(score) ? score : null;
}

function paperScoreLabel(paper: PaperData): string {
    const score = paperScore(paper);

    return score === null ? '—' : `${score.toFixed(score % 1 === 0 ? 0 : 2)} / 100`;
}

function submittedReviewCount(paper: PaperData): number {
    return paper.reviews.filter((review) => review.is_submitted).length;
}

function reviewStatusLabel(review: { is_submitted: boolean; auto_submitted_at: string | null }): string {
    if (review.auto_submitted_at) return 'Auto-submitted';
    return review.is_submitted ? 'Submitted' : 'Draft';
}

function reviewStatusVariant(review: { is_submitted: boolean; auto_submitted_at: string | null }): 'default' | 'outline' | 'warning' {
    if (review.auto_submitted_at) return 'warning';
    return review.is_submitted ? 'default' : 'outline';
}

function submittedCorrectionReviews(paper: PaperData) {
    return paper.reviews.filter((review) => review.is_submitted);
}

function hasSubmittedCorrectionReviews(): boolean {
    return props.subject.papers.some((paper) => submittedCorrectionReviews(paper).length > 0);
}

function reviewJudgeName(review: { reviewer?: UserData | null }): string {
    return review.reviewer?.name ?? 'Unknown judge';
}

function reviewScoreStatus(review: { scores_json: Array<{ criteria: string; score: number }> | null }): string {
    const count = review.scores_json?.length ?? 0;

    return count === 0 ? 'No scores recorded' : `${count} criteria scored`;
}

// How many reviewers are actually assigned to score each paper (active assignments
// on its defense attempt). Used as the "/ N" denominator so unsubmitted DRAFT
// reviews never inflate the "X / Y submitted" count or the release-readiness check.
const assignedReviewerCountByPaper = computed(() => {
    const map = new Map<number, number>();
    for (const period of props.subject.defense_periods) {
        for (const attempt of period.attempts) {
            const count = (attempt.active_reviewer_assignments ?? []).length;
            for (const paper of attempt.papers ?? []) {
                map.set(paper.id, count);
            }
        }
    }
    return map;
});

function reviewTotalCount(paper: PaperData): number {
    const assigned = assignedReviewerCountByPaper.value.get(paper.id) ?? 0;
    // Never show a total smaller than the number already submitted.
    return Math.max(assigned, submittedReviewCount(paper));
}

function allReviewsSubmitted(paper: PaperData): boolean {
    const total = reviewTotalCount(paper);
    return total > 0 && submittedReviewCount(paper) === total;
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

function hasPaperReleaseMarker(paper: PaperData): boolean {
    const attempt = paperAttemptInfo(paper)?.attempt;
    const team = subjectTeamForPaper(paper);

    return paper.visibility_status === 'published'
        || (attempt?.results_released_at !== null && attempt?.results_released_at !== undefined)
        || (team?.results_released_at !== null && team?.results_released_at !== undefined);
}

function isPaperResultReleased(paper: PaperData): boolean {
    return hasPaperReleaseMarker(paper) && paperScore(paper) !== null;
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

// A document only counts as "submitted" once the student turns it in (drafts that
// are merely attached stay private to the team).
function paperIsTurnedIn(paper: PaperData | null | undefined): boolean {
    return !!paper && paper.visibility_status !== 'draft';
}
function attemptTurnedInPaper(attempt: DefenseAttemptData | null): PaperData | null {
    const paper = attemptPaper(attempt);
    return paperIsTurnedIn(paper) ? paper : null;
}
// True when this team has only an attached draft (visible to the team, not judges).
function attemptHasDraftOnly(attempt: DefenseAttemptData | null): boolean {
    const paper = attemptPaper(attempt);
    return !!paper && ! paperIsTurnedIn(paper);
}

function activeAttemptAssignments(attempt: DefenseAttemptData | null): ReviewerAssignmentData[] {
    return attempt?.reviewer_assignments?.filter((assignment) => assignment.status === 'active') ?? [];
}

function sortedActiveAttemptAssignments(attempt: DefenseAttemptData | null): ReviewerAssignmentData[] {
    return [...activeAttemptAssignments(attempt)].sort((a, b) => {
        const aRank = assignmentRoleSortRank(a);
        const bRank = assignmentRoleSortRank(b);

        if (aRank !== bRank) return aRank - bRank;

        if (aRank === 4) {
            const roleCompare = attemptRoleDisplayLabel(a).localeCompare(attemptRoleDisplayLabel(b), undefined, {
                numeric: true,
                sensitivity: 'base',
            });

            if (roleCompare !== 0) return roleCompare;
        }

        return a.reviewer.name.localeCompare(b.reviewer.name);
    });
}

function assignmentRoleSortRank(assignment: ReviewerAssignmentData): number {
    if (isSubjectOwnerAssignment(assignment)) return 0;
    if (isAdvisorAssignment(assignment)) return 1;

    const normalized = attemptRoleDisplayLabel(assignment).trim().toLowerCase().replaceAll('_', ' ');

    if (normalized === 'academic examiner') return 2;
    if (normalized === 'technical examiner') return 3;

    return 4;
}

function pendingAttemptAssignments(attempt: DefenseAttemptData | null): ReviewerAssignmentData[] {
    return attempt?.reviewer_assignments?.filter((assignment) => assignment.status === 'pending') ?? [];
}

function reviewerHasSubmittedAttemptReview(attempt: DefenseAttemptData, reviewerId: number): boolean {
    return attempt.papers.some((paper) =>
        paper.reviews.some((review) => review.reviewer?.id === reviewerId && review.is_submitted),
    );
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

    const assignment = attempt.reviewer_assignments.find((item) => item.reviewer_id === reviewerId);
    router.patch(approveAttemptReviewer.url({ defenseAttempt: attempt.id, user: reviewerId }), assignment ? assignmentRoleDraft(attempt, assignment) : {}, { preserveScroll: true });
}

function rejectReviewerAssignment(attempt: DefenseAttemptData | null, reviewerId: number) {
    if (!attempt) return;
    router.patch(rejectAttemptReviewer.url({ defenseAttempt: attempt.id, user: reviewerId }), {}, { preserveScroll: true });
}

const judgeDialogAttemptId = ref<number | null>(null);
const judgeDialogOpen = computed({
    get: () => judgeDialogAttemptId.value !== null,
    set: (open) => {
        if (!open) {
            judgeDialogAttemptId.value = null;
            attemptReviewerForm.reset();
            attemptReviewerForm.clearErrors();
            confirmingRemoveAssignmentId.value = null;
        }
    },
});
const attemptReviewerForm = useForm({
    reviewer_id: '',
    committee_role: 'technical_examiner',
    role_label: '',
});
const attemptReviewerRoleDrafts = ref<Record<string, AttemptReviewerRoleDraft>>({});
const ownerExaminerRoleEditorOpen = ref<Record<string, boolean>>({});
const attemptReviewerNeedsCustomRole = computed(() => attemptReviewerForm.committee_role === 'custom');
const selectedAttemptReviewerIsAdvisor = computed(() => {
    const reviewer = props.subject.reviewers.find((item) => String(item.id) === String(attemptReviewerForm.reviewer_id));

    return reviewer ? reviewerRoleLabel(reviewer).trim().toLowerCase() === 'advisor' : false;
});

const judgeDialogAttempt = computed(() => {
    if (judgeDialogAttemptId.value === null) return null;

    for (const period of props.subject.defense_periods) {
        const attempt = period.attempts.find((item) => item.id === judgeDialogAttemptId.value);
        if (attempt) return attempt;
    }

    return null;
});

const judgeDialogPeriod = computed(() => {
    if (judgeDialogAttemptId.value === null) return null;

    return props.subject.defense_periods.find((period) =>
        period.attempts.some((attempt) => attempt.id === judgeDialogAttemptId.value),
    ) ?? null;
});

const judgeDialogTitle = computed(() => {
    const attempt = judgeDialogAttempt.value;
    const period = judgeDialogPeriod.value;

    if (!attempt || !period) return 'Manage Judges';

    return `${attempt.team.name} - ${period.name}`;
});

const judgeDialogSubtitle = computed(() => {
    const attempt = judgeDialogAttempt.value;
    if (!attempt) return '';

    return `${attempt.label}. Assign judges only for this defense session. Other rounds keep their own judge list and feedback history.`;
});

const assignableAttemptReviewers = computed(() => {
    const attempt = judgeDialogAttempt.value;
    if (!attempt) return [];

    // A reviewer may hold several scoring roles in one session, so an already-assigned
    // reviewer stays selectable to add a *different* role. The backend rejects an exact
    // duplicate role with a clear message.
    return props.subject.reviewers;
});

function reviewerRoleLabel(reviewer: { pivot?: { role: string; role_label: string | null } }): string {
    const role = reviewer.pivot?.role ?? '';

    return reviewer.pivot?.role_label || committeeRoleLabels[role] || role || 'Reviewer';
}

// Keyed by assignment id (not reviewer id) so a judge holding two roles in the
// same session gets an independent role draft + editor state per responsibility.
function assignmentRoleKey(attemptId: number, assignmentId: number): string {
    return `${attemptId}:${assignmentId}`;
}

function roleDraftFromLabel(label: string | null): AttemptReviewerRoleDraft {
    const normalized = (label ?? '').trim().toLowerCase().replaceAll('_', ' ');

    if (normalized === 'technical examiner') {
        return { committee_role: 'technical_examiner', role_label: '' };
    }

    if (normalized === 'academic examiner') {
        return { committee_role: 'academic_examiner', role_label: '' };
    }

    if (normalized === 'advisor') {
        return { committee_role: 'advisor', role_label: '' };
    }

    if (normalized === 'fyp instructor') {
        return { committee_role: 'technical_examiner', role_label: '' };
    }

    if (normalized === 'guest panel' || normalized === 'guestpanel' || normalized === 'reviewer') {
        return { committee_role: 'technical_examiner', role_label: '' };
    }

    if (!label) {
        return { committee_role: 'technical_examiner', role_label: '' };
    }

    return { committee_role: 'custom', role_label: label };
}

function defaultRoleDraftForReviewer(reviewerId: string | number | null): AttemptReviewerRoleDraft {
    const reviewer = props.subject.reviewers.find((item) => String(item.id) === String(reviewerId));

    if (reviewer && reviewerRoleLabel(reviewer).trim().toLowerCase() === 'advisor') {
        return { committee_role: 'advisor', role_label: '' };
    }

    return { committee_role: 'technical_examiner', role_label: '' };
}

function seedAssignmentRoleDraft(attempt: DefenseAttemptData, assignment: ReviewerAssignmentData): void {
    const key = assignmentRoleKey(attempt.id, assignment.id);

    attemptReviewerRoleDrafts.value[key] = roleDraftFromLabel(assignment.committee_role);
}

function seedJudgeRoleDrafts(attempt: DefenseAttemptData): void {
    for (const assignment of attempt.reviewer_assignments.filter((item) => ['active', 'pending'].includes(item.status))) {
        seedAssignmentRoleDraft(attempt, assignment);
    }
}

function assignmentRoleDraft(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData): AttemptReviewerRoleDraft {
    if (!attempt) {
        return { committee_role: 'technical_examiner', role_label: '' };
    }

    const key = assignmentRoleKey(attempt.id, assignment.id);

    if (!attemptReviewerRoleDrafts.value[key]) {
        seedAssignmentRoleDraft(attempt, assignment);
    }

    return attemptReviewerRoleDrafts.value[key];
}

function setAssignmentRole(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData, role: string): void {
    if (!attempt) return;

    const draft = assignmentRoleDraft(attempt, assignment);
    draft.committee_role = role;

    if (role !== 'custom') {
        draft.role_label = '';
    }
}

function setAssignmentCustomRole(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData, roleLabel: string): void {
    if (!attempt) return;

    assignmentRoleDraft(attempt, assignment).role_label = roleLabel;
}

function ownerExaminerRoleLabel(assignment: ReviewerAssignmentData): string | null {
    if (!isSubjectOwnerAssignment(assignment)) return null;

    const normalized = (assignment.committee_role ?? '').trim().toLowerCase().replaceAll('_', ' ');

    if (normalized === 'technical examiner') return 'Technical examiner';
    if (normalized === 'academic examiner') return 'Academic examiner';

    if (['', 'fyp instructor', 'advisor', 'guest panel', 'guestpanel', 'reviewer'].includes(normalized)) {
        return null;
    }

    return assignment.committee_role ?? null;
}

function ownerExaminerRoleEditorVisible(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData): boolean {
    if (!attempt) return false;

    // An EXTRA owner row (the owner's "other role", not the core FYP Instructor row)
    // is always editable so the instructor can set/change that role directly.
    if (isSubjectOwnerAssignment(assignment)
        && assignment.committee_role !== 'fyp_instructor'
        && ownerActiveAssignmentCount(attempt) > 1) {
        return true;
    }

    return ownerExaminerRoleLabel(assignment) !== null
        || ownerExaminerRoleEditorOpen.value[assignmentRoleKey(attempt.id, assignment.id)] === true;
}

function startOwnerExaminerRole(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData): void {
    if (!attempt) return;

    const key = assignmentRoleKey(attempt.id, assignment.id);
    ownerExaminerRoleEditorOpen.value[key] = true;
    assignmentRoleDraft(attempt, assignment).committee_role = 'technical_examiner';
    assignmentRoleDraft(attempt, assignment).role_label = '';
}

function clearOwnerExaminerRole(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData): void {
    if (!attempt) return;

    router.patch(updateAttemptReviewerRole.url({
        defenseAttempt: attempt.id,
        user: assignment.reviewer_id,
    }), {
        committee_role: 'fyp_instructor',
        role_label: '',
        assignment_id: assignment.id,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            const key = assignmentRoleKey(attempt.id, assignment.id);
            ownerExaminerRoleEditorOpen.value[key] = false;
            assignmentRoleDraft(attempt, assignment).committee_role = 'technical_examiner';
            assignmentRoleDraft(attempt, assignment).role_label = '';
        },
    });
}

function assignmentRoleCanSave(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData): boolean {
    if (!attempt) return false;

    const draft = assignmentRoleDraft(attempt, assignment);

    return draft.committee_role !== 'custom' || draft.role_label.trim().length > 0;
}

// The picked role's display label, used to detect whether the draft differs from
// the saved committee_role so "Save role" only appears when something changed.
function assignmentDraftLabel(draft: AttemptReviewerRoleDraft): string {
    if (draft.committee_role === 'custom') return draft.role_label.trim();

    return attemptCommitteeRoleOptions.find((option) => option.value === draft.committee_role)?.label
        ?? draft.committee_role;
}

function assignmentRoleChanged(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData): boolean {
    if (!attempt) return false;

    return assignmentDraftLabel(assignmentRoleDraft(attempt, assignment)) !== (assignment.committee_role ?? '');
}

// True once this specific scoring responsibility has a submitted/locked score —
// it then becomes an academic record and can no longer be removed here.
function assignmentHasSubmittedScore(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData): boolean {
    if (!attempt) return false;

    return attempt.papers.some((paper) =>
        paper.reviews.some((review) => review.defense_attempt_reviewer_id === assignment.id && review.is_submitted),
    );
}

function saveAssignmentRole(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData): void {
    if (!attempt) return;

    if (!assignmentRoleCanSave(attempt, assignment)) return;

    router.patch(updateAttemptReviewerRole.url({
        defenseAttempt: attempt.id,
        user: assignment.reviewer_id,
    }), { ...assignmentRoleDraft(attempt, assignment), assignment_id: assignment.id }, { preserveScroll: true });
}

function attemptRoleDisplayLabel(assignment: ReviewerAssignmentData): string {
    if (isSubjectOwnerAssignment(assignment)) {
        const examinerRole = ownerExaminerRoleLabel(assignment);

        return examinerRole ? `FYP Instructor + ${examinerRole}` : 'FYP Instructor';
    }
    if (isAdvisorAssignment(assignment)) return 'Advisor';

    const normalized = (assignment.committee_role ?? '').trim().toLowerCase().replaceAll('_', ' ');

    if (normalized === 'fyp instructor') return 'Technical examiner';
    if (normalized === 'technical examiner') return 'Technical examiner';
    if (normalized === 'academic examiner') return 'Academic examiner';
    if (normalized === 'guest panel' || normalized === 'reviewer' || normalized === '') return 'Technical examiner';

    return assignment.committee_role ?? 'Technical examiner';
}

function isFixedAttemptRole(assignment: ReviewerAssignmentData): boolean {
    return !isSubjectOwnerAssignment(assignment) && isAdvisorAssignment(assignment);
}

function openJudgeDialog(attempt: DefenseAttemptData): void {
    judgeDialogAttemptId.value = attempt.id;
    attemptReviewerForm.reset();
    attemptReviewerForm.committee_role = 'technical_examiner';
    attemptReviewerForm.role_label = '';
    seedJudgeRoleDrafts(attempt);
    attemptReviewerForm.clearErrors();
}

watch(() => attemptReviewerForm.reviewer_id, (reviewerId) => {
    const draft = defaultRoleDraftForReviewer(reviewerId);

    attemptReviewerForm.committee_role = draft.committee_role;
    attemptReviewerForm.role_label = draft.role_label;
});

function addJudgeToAttempt(): void {
    const attempt = judgeDialogAttempt.value;
    if (!attempt || !attemptReviewerForm.reviewer_id) return;

    attemptReviewerForm.post(addAttemptReviewer.url(attempt.id), {
        preserveScroll: true,
        onSuccess: () => {
            attemptReviewerForm.reset();
            attemptReviewerForm.committee_role = 'technical_examiner';
            attemptReviewerForm.role_label = '';
        },
    });
}

// Removal is confirmed inline inside the Manage Judges dialog (a second modal
// would open behind this one and be unreachable). Each scoring responsibility is
// removed individually via its assignment id, so a judge holding two roles can
// lose one role while keeping the other.
const confirmingRemoveAssignmentId = ref<number | null>(null);
const removeAttemptReviewerProcessingId = ref<number | null>(null);

function requestRemoveAttemptReviewer(assignment: ReviewerAssignmentData): void {
    confirmingRemoveAssignmentId.value = assignment.id;
}

function cancelRemoveAttemptReviewer(): void {
    confirmingRemoveAssignmentId.value = null;
}

function confirmRemoveAttemptReviewer(attempt: DefenseAttemptData | null, assignment: ReviewerAssignmentData): void {
    if (!attempt) return;

    removeAttemptReviewerProcessingId.value = assignment.id;
    router.delete(removeAttemptReviewer.url({
        defenseAttempt: attempt.id,
        user: assignment.reviewer_id,
    }), {
        data: { assignment_id: assignment.id },
        preserveScroll: true,
        onFinish: () => {
            removeAttemptReviewerProcessingId.value = null;
            confirmingRemoveAssignmentId.value = null;
        },
    });
}

function isSubjectOwnerAssignment(assignment: ReviewerAssignmentData): boolean {
    return assignment.reviewer_id === props.subject.teacher.id;
}

// How many active assignments the owner holds in this session — used to allow
// cleaning up a duplicate owner row (keeping at least one).
function ownerActiveAssignmentCount(attempt: DefenseAttemptData | null): number {
    return activeAttemptAssignments(attempt).filter((a) => a.reviewer_id === props.subject.teacher.id).length;
}

function isAdvisorAssignment(assignment: ReviewerAssignmentData): boolean {
    if ((assignment.committee_role ?? '').trim().toLowerCase().replaceAll('_', ' ') === 'advisor') {
        return true;
    }

    const reviewer = props.subject.reviewers.find((item) => item.id === assignment.reviewer_id);

    return reviewerRoleLabel(reviewer ?? {}).trim().toLowerCase() === 'advisor';
}

// Flattened list of every pending reviewer request across all rounds.
const allPendingReviewerRequests = computed(() => {
    const out: Array<{ attempt: DefenseAttemptData; assignment: ReviewerAssignmentData; periodName: string }> = [];
    for (const period of props.subject.defense_periods) {
        for (const attempt of period.attempts) {
            for (const a of attempt.reviewer_assignments ?? []) {
                if (a.status === 'pending') {
                    out.push({ attempt, assignment: a, periodName: period.name });
                }
            }
        }
    }
    return out;
});

function approveAllReviewerRequests() {
    router.post(approveAllReviewerRequestsAction.url(props.subject.id), {}, { preserveScroll: true });
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
    return `Add a re-defense session for ${pendingReDefense.value.teamName} under ${pendingReDefense.value.periodName}? The same reviewers will be carried over and a new schedule will need to be set.`;
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
    return `Remove ${pendingReDefenseRemoval.value.label} for ${pendingReDefenseRemoval.value.teamName}? This deletes the empty re-defense session. It cannot be removed if a document has been submitted or a review recorded.`;
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

// --- Late-upload extension ---
const extendUploadOpen = ref(false);
const extendUploadAttemptId = ref<number | null>(null);
const extendUploadHours = ref(24);

function openExtendUpload(attempt: DefenseAttemptData) {
    extendUploadAttemptId.value = attempt.id;
    extendUploadHours.value = 24;
    extendUploadOpen.value = true;
}

function confirmExtendUpload() {
    if (!extendUploadAttemptId.value) return;
    router.post(`/defense-attempts/${extendUploadAttemptId.value}/extend-upload`, {
        hours: extendUploadHours.value,
    }, {
        preserveScroll: true,
        onFinish: () => {
            extendUploadOpen.value = false;
            extendUploadAttemptId.value = null;
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
        items.push({ key: 'nojudge', label: 'Defense sessions without approved reviewers', count: attemptsNoJudge, section: 'rounds', color: 'orange' });
    }

    // Documents with no submitted review
    const papersNoScore = props.subject.papers.filter((p) => !p.reviews.some((r) => r.is_submitted));
    if (papersNoScore.length) {
        items.push({ key: 'noscore', label: 'Documents with no scores yet', count: papersNoScore.length, section: 'scores', color: 'red' });
    }

    // Ready to release: all reviews submitted, not yet released
    const readyToRelease = props.subject.papers.filter((p) => {
        if (isPaperResultReleased(p)) return false;

        return paperScore(p) !== null && allReviewsSubmitted(p);
    });
    if (readyToRelease.length) {
        items.push({ key: 'ready', label: 'Teams ready to release', count: readyToRelease.length, section: 'scores', color: 'green' });
    }

    // Overdue judges are counted from defense-attempt assignments, not team members.
    const now = new Date();
    const overdueCount = props.subject.papers.reduce((n, paper) => {
        const attempt = paperAttemptInfo(paper)?.attempt;
        const deadline = attempt?.score_deadline_at ?? subjectTeamForPaper(paper)?.score_deadline_at;

        if (!deadline || new Date(deadline) > now) return n;

        const submittedReviewerIds = new Set(
            paper.reviews
                .filter((review) => review.is_submitted && review.reviewer)
                .map((review) => review.reviewer!.id),
        );
        const missing = activeAttemptAssignments(attempt ?? null)
            .filter((assignment) => !submittedReviewerIds.has(assignment.reviewer_id));

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
    return [...props.subject.papers]
        .filter((p) => p.team?.id === teamId)
        .sort((a, b) => paperSortKey(b).localeCompare(paperSortKey(a)))[0] ?? null;
}

function teamPaperScore(teamId: number): number | null {
    return paperScore(getTeamPaper(teamId));
}

function teamPaperScoreLabel(teamId: number): string {
    const paper = getTeamPaper(teamId);

    return paper ? paperScoreLabel(paper) : '—';
}

type SectionKey = 'rounds' | 'papers' | 'teams' | 'members' | 'schedule' | 'scores';

// Remember the active tab per subject so returning from a document/rubric page
// lands back on the tab they came from (e.g. Scores), not the default.
const sectionStorageKey = `scormetry:subject:${props.subject.id}:section`;
function initialSection(): SectionKey {
    const valid: SectionKey[] = ['rounds', 'papers', 'teams', 'members', 'schedule', 'scores'];
    if (typeof sessionStorage !== 'undefined') {
        const stored = sessionStorage.getItem(sectionStorageKey) as SectionKey | null;
        if (stored && valid.includes(stored)) {
            return stored;
        }
    }
    return 'rounds';
}
const activeSection = ref<SectionKey>(initialSection());
watch(activeSection, (s) => {
    if (typeof sessionStorage !== 'undefined') {
        sessionStorage.setItem(sectionStorageKey, s);
    }
});
const sections: Array<{ key: SectionKey; label: string; icon: typeof FileText }> = [
    { key: 'rounds', label: 'Evaluation Rounds', icon: ClipboardCheck },
    { key: 'teams', label: 'Team', icon: UsersRound },
    { key: 'papers', label: 'Documents', icon: FileText },
    { key: 'scores', label: 'Scores', icon: BarChart2 },
    { key: 'members', label: 'Members', icon: Users },
];

// --- Defense Schedule ---
const TEAM_COLORS = [
    { border: 'border-l-blue-500',    header: 'bg-blue-50/60 dark:bg-blue-950/20',    icon: 'text-blue-600 dark:text-blue-400',    dot: 'bg-blue-500' },
    { border: 'border-l-rose-500',    header: 'bg-rose-50/60 dark:bg-rose-950/20',    icon: 'text-rose-600 dark:text-rose-400',    dot: 'bg-rose-500' },
    { border: 'border-l-emerald-500', header: 'bg-emerald-50/60 dark:bg-emerald-950/20', icon: 'text-emerald-600 dark:text-emerald-400', dot: 'bg-emerald-500' },
    { border: 'border-l-violet-500',  header: 'bg-violet-50/60 dark:bg-violet-950/20', icon: 'text-violet-600 dark:text-violet-400', dot: 'bg-violet-500' },
    { border: 'border-l-amber-500',   header: 'bg-amber-50/60 dark:bg-amber-950/20',  icon: 'text-amber-600 dark:text-amber-400',  dot: 'bg-amber-500' },
    { border: 'border-l-cyan-500',    header: 'bg-cyan-50/60 dark:bg-cyan-950/20',    icon: 'text-cyan-600 dark:text-cyan-400',    dot: 'bg-cyan-500' },
    { border: 'border-l-orange-500',  header: 'bg-orange-50/60 dark:bg-orange-950/20', icon: 'text-orange-600 dark:text-orange-400', dot: 'bg-orange-500' },
];

function teamColorKey(team: number | { id?: number; name?: string | null }, fallbackName?: string | null): number {
    const name = typeof team === 'number' ? fallbackName : team.name;
    const match = name?.match(/\d+/);

    if (match) {
        return Math.max(1, Number(match[0]));
    }

    return Math.max(1, typeof team === 'number' ? team : (team.id ?? 1));
}

function teamColor(team: number | { id?: number; name?: string | null }, fallbackName?: string | null) {
    const key = teamColorKey(team, fallbackName);

    return TEAM_COLORS[(key - 1) % TEAM_COLORS.length];
}

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
const hydratingScheduleForm = ref(false);
const paperDeadlineCustomized = ref(false);
const scoreDeadlineCustomized = ref(false);

// Second-step confirmation dialog state
const scheduleConfirmOpen = ref(false);

function padDatePart(value: number): string {
    return String(value).padStart(2, '0');
}

function toDateTimeLocalValue(date: Date): string {
    return [
        date.getFullYear(),
        padDatePart(date.getMonth() + 1),
        padDatePart(date.getDate()),
    ].join('-') + `T${padDatePart(date.getHours())}:${padDatePart(date.getMinutes())}`;
}

function automaticPaperDeadline(): string {
    if (!scheduleForm.defense_date) {
        return '';
    }

    const [year, month, day] = scheduleForm.defense_date.split('-').map(Number);
    const deadline = new Date(year, month - 1, day, 12, 0, 0, 0);
    deadline.setDate(deadline.getDate() - 1);

    return toDateTimeLocalValue(deadline);
}

function automaticScoreDeadline(): string {
    if (!scheduleForm.defense_date) {
        return '';
    }

    const [year, month, day] = scheduleForm.defense_date.split('-').map(Number);
    const deadline = new Date(year, month - 1, day, 12, 0, 0, 0);
    deadline.setDate(deadline.getDate() + 1);

    return toDateTimeLocalValue(deadline);
}

const automaticPaperDeadlineLabel = computed(() => {
    const deadline = automaticPaperDeadline();

    return deadline ? formatDateTime(deadline) : 'Choose a defense date first';
});

const automaticScoreDeadlineLabel = computed(() => {
    const deadline = automaticScoreDeadline();

    return deadline ? formatDateTime(deadline) : 'Choose a defense date first';
});

function syncAutomaticDeadlines(force = false): void {
    const paperDeadline = automaticPaperDeadline();
    const scoreDeadline = automaticScoreDeadline();

    if (paperDeadline && (force || !paperDeadlineCustomized.value || !scheduleForm.paper_upload_deadline_at)) {
        scheduleForm.paper_upload_deadline_at = paperDeadline;
        paperDeadlineCustomized.value = false;
    }

    if (scoreDeadline && (force || !scoreDeadlineCustomized.value || !scheduleForm.score_deadline_at)) {
        scheduleForm.score_deadline_at = scoreDeadline;
        scoreDeadlineCustomized.value = false;
    }
}

function markPaperDeadlineCustomized(): void {
    paperDeadlineCustomized.value = scheduleForm.paper_upload_deadline_at !== automaticPaperDeadline();
}

function markScoreDeadlineCustomized(): void {
    scoreDeadlineCustomized.value = scheduleForm.score_deadline_at !== automaticScoreDeadline();
}

function resetPaperDeadlineToAuto(): void {
    paperDeadlineCustomized.value = false;
    syncAutomaticDeadlines(true);
}

function resetScoreDeadlineToAuto(): void {
    scoreDeadlineCustomized.value = false;
    syncAutomaticDeadlines(true);
}

function hydrateDeadlineCustomizationState(): void {
    const paperDeadline = automaticPaperDeadline();
    const scoreDeadline = automaticScoreDeadline();

    paperDeadlineCustomized.value = Boolean(
        scheduleForm.paper_upload_deadline_at
        && paperDeadline
        && scheduleForm.paper_upload_deadline_at !== paperDeadline,
    );
    scoreDeadlineCustomized.value = Boolean(
        scheduleForm.score_deadline_at
        && scoreDeadline
        && scheduleForm.score_deadline_at !== scoreDeadline,
    );

    syncAutomaticDeadlines(false);
}

watch(
    () => [scheduleForm.defense_date, scheduleForm.defense_time, scheduleForm.defense_duration],
    () => {
        if (!hydratingScheduleForm.value) {
            syncAutomaticDeadlines(false);
        }
    },
);

function openScheduleDialog(team: ScheduleTeamData) {
    hydratingScheduleForm.value = true;
    scheduleDialogTeam.value  = { ...team, save_target: 'team' };
    scheduleWasSet.value      = !!team.defense_date;
    scheduleForm.defense_date = team.defense_date ?? '';
    scheduleForm.defense_time = team.defense_time ? team.defense_time.slice(0, 5) : '';
    scheduleForm.defense_duration = team.defense_duration ?? '';
    scheduleForm.defense_room = team.defense_room ?? '';
    scheduleForm.paper_upload_deadline_at = team.paper_upload_deadline_at
        ? team.paper_upload_deadline_at.slice(0, 16)
        : '';
    scheduleForm.score_deadline_at = team.score_deadline_at
        ? team.score_deadline_at.slice(0, 16)
        : '';
    hydratingScheduleForm.value = false;
    hydrateDeadlineCustomizationState();
}

function openRoundScheduleDialog(attempt: DefenseAttemptData | null, teamName: string) {
    if (!attempt || !selectedDefensePeriod.value) return;

    hydratingScheduleForm.value = true;
    scheduleDialogTeam.value = {
        id: attempt.id,
        name: teamName,
        save_target: 'attempt',
        round_name: `${selectedDefensePeriod.value.name} • Defense Session ${attempt.attempt_number}`,
        defense_date: attempt.defense_date,
        defense_time: attempt.defense_time,
        defense_duration: attempt.defense_duration,
        defense_room: attempt.defense_room,
        paper_upload_deadline_at: attempt.paper_upload_deadline_at,
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
    hydratingScheduleForm.value = false;
    hydrateDeadlineCustomizationState();
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
    if (!val) return formatDateTimeWithAmPm(val);
    // Deadlines are entered & stored as naive wall-clock (the inputs are timezone-less
    // and the backend enforces them in the app timezone). Strip any timezone suffix so
    // the time shown matches exactly what the teacher set — e.g. 12:00 PM stays 12:00 PM,
    // instead of being shifted to the viewer's local timezone.
    const naive = val.slice(0, 16).replace(' ', 'T');
    return formatDateTimeWithAmPm(naive);
}

function addMinutes(time: string, minutes: number): string {
    return formatClockTime(addMinutesToClockTime(time, minutes));
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
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#3157f4] via-[#3345e5] to-[#4631cf] px-6 py-5 text-white shadow-sm">
                <div class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 rounded-full bg-white/10" />
                <div class="pointer-events-none absolute -bottom-10 right-28 h-28 w-28 rounded-full bg-white/10" />
                <div class="pointer-events-none absolute bottom-0 right-0 h-32 w-44 rounded-tl-full bg-white/5" />

                <div class="relative flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/20 shadow-inner ring-2 ring-white/25">
                            <BookOpen class="h-6 w-6 text-white" />
                        </div>
                        <div class="min-w-0">
                            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ subject.title }}</h1>
                            <p v-if="subject.description" class="mt-1 max-w-3xl text-sm text-white/75">{{ subject.description }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-white/80">
                                <span>Taught by <strong class="text-white">{{ subject.teacher.name }}</strong></span>
                                <span class="h-1 w-1 rounded-full bg-white/40" />
                                <span class="rounded-full bg-white/20 px-2.5 py-0.5 text-xs font-semibold text-white">Pass: {{ subject.passing_score }}%</span>
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
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
                                        This will remove all teams, documents, and reviews permanently.
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
                                        You will lose access to all documents and teams in this subject.
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
                class="mt-3 grid gap-2.5"
                :class="hasVisibleInviteCodes ? 'lg:grid-cols-[minmax(0,1fr)_minmax(340px,0.85fr)]' : 'lg:grid-cols-1'"
            >
                <!-- Stats cards -->
                <div
                    class="grid grid-cols-2 gap-2"
                    :class="hasVisibleInviteCodes ? '' : 'lg:grid-cols-4'"
                >
                    <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 border-l-4 border-l-blue-500 bg-white px-3 py-2 shadow-sm dark:border-slate-800 dark:border-l-blue-500 dark:bg-background">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950/40">
                            <Users class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p class="stat-num text-lg font-bold leading-none text-blue-600 dark:text-blue-400">{{ stats.students }}</p>
                            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Students</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 border-l-4 border-l-violet-500 bg-white px-3 py-2 shadow-sm dark:border-slate-800 dark:border-l-violet-500 dark:bg-background">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-950/40">
                            <ShieldCheck class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                        </div>
                        <div>
                            <p class="stat-num text-lg font-bold leading-none text-violet-600 dark:text-violet-400">{{ stats.reviewers }}</p>
                            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Scoring roles</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 border-l-4 border-l-indigo-500 bg-white px-3 py-2 shadow-sm dark:border-slate-800 dark:border-l-indigo-500 dark:bg-background">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/40">
                            <FileText class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div>
                            <p class="stat-num text-lg font-bold leading-none text-indigo-600 dark:text-indigo-400">{{ stats.papers }}</p>
                            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Documents</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 border-l-4 border-l-emerald-500 bg-white px-3 py-2 shadow-sm dark:border-slate-800 dark:border-l-emerald-500 dark:bg-background">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40">
                            <BarChart3 class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <p class="stat-num text-lg font-bold leading-none text-emerald-600 dark:text-emerald-400">{{ stats.reviewed }}%</p>
                            <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Reviewed</p>
                        </div>
                    </div>
                </div>

                <!-- Invite Codes -->
                <div v-if="hasVisibleInviteCodes" class="flex flex-col gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm dark:border-slate-800 dark:bg-background">
                    <div class="flex items-center gap-2">
                        <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40">
                            <Copy class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <p class="text-sm font-semibold">Invite Codes</p>
                        <InfoTip
                            text="Two separate codes: the Student code enrols students into the class, the Reviewer code lets committee members join to judge. Share each with the right group — they can't be swapped."
                            class="ml-0.5"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <div v-if="subject.join_code" class="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50/60 px-2.5 py-1.5 dark:border-blue-800 dark:bg-blue-950/20">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-blue-500">Student</p>
                                <p class="font-mono text-sm font-bold tracking-[0.24em] text-blue-700 dark:text-blue-300">{{ subject.join_code }}</p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button variant="ghost" size="sm" class="h-6 w-6 p-0 hover:bg-blue-100 dark:hover:bg-blue-900" title="Copy student code" @click="copyJoinCode">
                                    <Check v-if="codeCopied" class="h-3.5 w-3.5 text-emerald-600" />
                                    <Copy v-else class="h-3.5 w-3.5 text-blue-600" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-6 gap-1 px-2 text-[11px] font-semibold text-blue-700 hover:bg-blue-100 dark:text-blue-300 dark:hover:bg-blue-900"
                                    type="button"
                                    @click="resetStudentCodeConfirmOpen = true"
                                >
                                    <RefreshCw class="h-3 w-3" />
                                    Reset
                                </Button>
                            </div>
                        </div>
                        <div v-if="subject.reviewer_code" class="flex items-center justify-between rounded-lg border border-violet-200 bg-violet-50/60 px-2.5 py-1.5 dark:border-violet-800 dark:bg-violet-950/20">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-violet-500">Reviewer</p>
                                <p class="font-mono text-sm font-bold tracking-[0.24em] text-violet-700 dark:text-violet-300">{{ subject.reviewer_code }}</p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button variant="ghost" size="sm" class="h-6 w-6 p-0 hover:bg-violet-100 dark:hover:bg-violet-900" title="Copy reviewer code" @click="copyReviewerCode">
                                    <Check v-if="reviewerCodeCopied" class="h-3.5 w-3.5 text-emerald-600" />
                                    <Copy v-else class="h-3.5 w-3.5 text-violet-600" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-6 gap-1 px-2 text-[11px] font-semibold text-violet-700 hover:bg-violet-100 dark:text-violet-300 dark:hover:bg-violet-900"
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
                            <div class="rounded-lg bg-[#24327a]/10 p-2 text-[#24327a] dark:bg-blue-400/10 dark:text-blue-200">
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
                        <div v-if="isOwnerOrAdmin" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                            <Button size="sm" variant="outline" class="gap-1.5" as-child>
                                <Link :href="rubricCreate.url(subject.id)">
                                    <Upload class="h-3.5 w-3.5" />
                                    Upload Defense Rubric
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
                                ? 'border-[#24327a] bg-[#24327a] text-white shadow-sm dark:border-blue-400/50 dark:bg-[#24327a] dark:text-white'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-[#24327a]/40 hover:bg-[#24327a]/5 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300 dark:hover:border-blue-400/40 dark:hover:bg-blue-400/10 dark:hover:text-blue-100'"
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
                                    : 'bg-slate-100 text-slate-600 group-hover:bg-[#24327a]/10 group-hover:text-[#24327a] dark:bg-slate-800 dark:text-slate-300 dark:group-hover:bg-blue-400/10 dark:group-hover:text-blue-200'"
                            >
                                {{ periodScheduledCount(period) }}/{{ period.attempts.length }}
                            </span>
                        </button>
                        <InfoTip
                            text="Each tab is a defense round. The icon shows its rubric status — lock = ready to grade, triangle = uploaded but not locked, arrow = not uploaded. The count is scheduled / total teams."
                            side="bottom"
                            class="ml-1"
                        />
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
                    <!-- Full page scroll; no pinned headers while scanning teams. -->
                    <div v-else class="flex flex-col">
                        <!-- Stats summary bar -->
                        <div class="grid grid-cols-2 gap-px border-b bg-slate-100 sm:grid-cols-4 dark:bg-slate-800">
                            <div class="flex flex-col gap-1 bg-white px-5 py-3 dark:bg-slate-950">
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Defense sessions</span>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xl font-bold text-foreground">{{ roundStats.total }}</span>
                                    <span v-if="roundStats.reDefenseCount > 0" class="text-xs font-medium text-[#24327a] dark:text-blue-200">
                                        incl. {{ roundStats.reDefenseCount }} re-defense
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1 bg-white px-5 py-3 dark:bg-slate-950">
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Scheduled</span>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xl font-bold" :class="roundStats.scheduled === roundStats.total ? 'text-emerald-600' : 'text-foreground'">
                                        {{ roundStats.scheduled }}
                                    </span>
                                    <span v-if="roundStats.awaiting > 0" class="text-xs font-medium text-amber-600 dark:text-amber-300">
                                        {{ roundStats.awaiting }} pending
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1 bg-white px-5 py-3 dark:bg-slate-950">
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Documents submitted</span>
                                <span class="text-xl font-bold text-foreground">{{ roundStats.pdfsIn }}<span class="text-sm font-normal text-muted-foreground">/{{ roundStats.total }}</span></span>
                            </div>
                            <div class="flex flex-col gap-1 bg-white px-5 py-3 dark:bg-slate-950">
                                <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Scoring roles assigned</span>
                                <span class="text-xl font-bold text-foreground">{{ roundStats.reviewersAssigned }}<span class="text-sm font-normal text-muted-foreground">/{{ roundStats.total }}</span></span>
                            </div>
                        </div>

                        <!-- Round meta strip -->
                        <div class="flex flex-col gap-3 border-b bg-slate-50/60 px-6 py-3 md:flex-row md:items-center md:justify-between dark:bg-slate-900/30">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-sm font-semibold text-foreground">{{ selectedDefensePeriod.name }}</h2>
                                <Badge variant="outline" :class="roundRubricClass(selectedDefensePeriod)">
                                    Defense Rubric: {{ roundRubricLabel(selectedDefensePeriod) }}
                                </Badge>
                                <Badge variant="outline" class="border-slate-200 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-200">
                                    Pass {{ Number(selectedDefensePeriod.passing_score).toFixed(0) }}
                                </Badge>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Sort teams -->
                                <div v-if="visibleRoundAttempts.length > 1" class="flex items-center gap-1.5">
                                    <span class="hidden text-xs font-medium uppercase tracking-wide text-muted-foreground sm:inline">Filter</span>
                                    <Select v-model="roundSortKey">
                                        <SelectTrigger class="h-9 w-[160px] rounded-xl bg-white text-sm dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="team">Team name</SelectItem>
                                            <SelectItem value="date">Defense date</SelectItem>
                                            <SelectItem value="document">Document status</SelectItem>
                                            <SelectItem value="reviewers">Scoring roles assigned</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="h-9 w-9 rounded-xl bg-white p-0 dark:border-slate-700 dark:bg-slate-900/80 dark:hover:bg-slate-800"
                                        :title="roundSortDir === 'asc' ? 'Ascending — click for descending' : 'Descending — click for ascending'"
                                        @click="toggleRoundSortDir"
                                    >
                                        <ArrowDownUp class="h-4 w-4" :class="roundSortDir === 'desc' ? 'text-[#24327a] dark:text-blue-200' : 'text-muted-foreground'" />
                                    </Button>
                                </div>
                                <Button
                                    v-if="selectedDefensePeriod.rubric"
                                    size="sm"
                                    variant="outline"
                                    class="h-9 gap-1.5 rounded-xl border-[#24327a]/20 bg-white px-3 text-sm font-semibold text-[#24327a] shadow-sm hover:bg-[#24327a]/5 dark:border-blue-400/30 dark:bg-slate-900/80 dark:text-blue-200 dark:hover:bg-blue-400/10"
                                    as-child
                                >
                                    <Link :href="rubricShow.url(selectedDefensePeriod.rubric.id)">
                                        <Eye class="h-3 w-3" />
                                        View Defense Rubric
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <!-- Quick filters -->
                        <div v-if="isOwnerOrAdmin && visibleRoundAttempts.length > 0" class="flex flex-wrap items-center gap-2 border-b bg-white px-6 py-2.5 dark:bg-background">
                            <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Filter</span>
                            <button
                                type="button"
                                class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                :class="roundFilter === 'all' ? 'border-[#24327a] bg-[#24327a] text-white dark:border-blue-400 dark:bg-[#24327a] dark:text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-300 dark:hover:bg-slate-800'"
                                @click="roundFilter = 'all'"
                            >
                                All teams ({{ baseRoundGroups.length }})
                            </button>
                            <button
                                v-if="roundFilterCounts.needs_schedule > 0"
                                type="button"
                                class="flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                :class="roundFilter === 'needs_schedule' ? 'border-amber-500 bg-amber-500 text-white dark:border-amber-400 dark:bg-amber-500 dark:text-slate-950' : 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-200 dark:hover:bg-amber-500/25'"
                                @click="roundFilter = roundFilter === 'needs_schedule' ? 'all' : 'needs_schedule'"
                            >
                                <Calendar class="h-3 w-3" />
                                Needs scheduling ({{ roundFilterCounts.needs_schedule }})
                            </button>
                            <button
                                v-if="roundFilterCounts.missing_document > 0"
                                type="button"
                                class="flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                :class="roundFilter === 'missing_document' ? 'border-slate-500 bg-slate-600 text-white dark:border-slate-400 dark:bg-slate-500 dark:text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-200 dark:hover:bg-slate-800'"
                                @click="roundFilter = roundFilter === 'missing_document' ? 'all' : 'missing_document'"
                            >
                                <FileText class="h-3 w-3" />
                                Missing document ({{ roundFilterCounts.missing_document }})
                            </button>
                            <button
                                v-if="roundFilterCounts.needs_judges > 0"
                                type="button"
                                class="flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                :class="roundFilter === 'needs_judges' ? 'border-violet-600 bg-violet-600 text-white dark:border-violet-400 dark:bg-violet-500 dark:text-white' : 'border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100 dark:border-violet-500/40 dark:bg-violet-500/15 dark:text-violet-200 dark:hover:bg-violet-500/25'"
                                @click="roundFilter = roundFilter === 'needs_judges' ? 'all' : 'needs_judges'"
                            >
                                <ShieldCheck class="h-3 w-3" />
                                Needs judges ({{ roundFilterCounts.needs_judges }})
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                        <table v-if="visibleRoundAttempts.length > 0" class="w-full min-w-[1050px] border-separate border-spacing-0 text-sm">
                            <thead>
                                <tr class="bg-slate-100 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-900 dark:text-slate-400">
                                    <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Team</th>
                                    <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Schedule</th>
                                    <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Document</th>
                                    <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Scoring roles</th>
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
                                            ? 'bg-[#24327a]/[0.03] hover:bg-[#24327a]/[0.06] dark:bg-[#24327a]/10 dark:hover:bg-[#24327a]/15'
                                            : 'hover:bg-slate-50/80 dark:hover:bg-slate-900/40'"
                                    >
                                    <td class="relative px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'first')">
                                        <!-- Team accent bar — different color per team group, continuous across the team's rows -->
                                        <span
                                            class="pointer-events-none absolute left-0 w-1.5"
                                            :class="[
                                                teamColor(group.teamId, attempt.team.name).dot,
                                                idx === 0 ? 'top-2 rounded-t-full' : 'top-0',
                                                idx === group.attempts.length - 1 ? 'bottom-2 rounded-b-full' : 'bottom-0',
                                            ]"
                                        />
                                        <!-- Branch arm (the L-shape) — re-defense rows only -->
                                        <CornerDownRight
                                            v-if="attempt.attempt_type === 're_defense'"
                                            class="absolute left-3 top-[1.05rem] h-4 w-4 text-[#24327a]/70 dark:text-blue-300/80"
                                        />
                                        <div class="flex flex-col gap-1" :class="attempt.attempt_type === 're_defense' ? 'pl-8' : 'pl-2'">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-foreground">{{ attempt.team.name }}</p>
                                                <span class="text-slate-300 dark:text-slate-600">·</span>
                                                <Badge
                                                    v-if="attempt.attempt_type === 're_defense'"
                                                    variant="outline"
                                                    class="gap-1 border-[#24327a]/30 bg-[#24327a]/10 font-semibold text-[#24327a] dark:border-blue-400/30 dark:bg-blue-400/10 dark:text-blue-200"
                                                >
                                                    <RefreshCw class="h-3 w-3" />
                                                    Defense Session {{ attempt.attempt_number }}
                                                </Badge>
                                                <Badge v-else variant="outline" class="border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300">
                                                    Defense Session {{ attempt.attempt_number }}
                                                </Badge>
                                            </div>
                                            <p class="text-xs text-muted-foreground">
                                                {{ teamStudentMembers(attempt.team).map((member) => member.name).join(', ') || 'No student members' }}
                                            </p>
                                            <p
                                                class="max-w-sm text-xs leading-snug"
                                                :class="attempt.team.topic ? 'text-slate-600 dark:text-slate-300' : 'text-muted-foreground'"
                                            >
                                                {{ attempt.team.topic || 'Project topic not set yet' }}
                                            </p>
                                            <p v-if="attempt.team.advisor" class="flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400">
                                                <BookOpen class="h-3 w-3" />
                                                Advisor: {{ attempt.team.advisor.name }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'middle')">
                                        <div v-if="attempt.defense_date" class="flex flex-col gap-1">
                                            <Badge variant="outline" class="w-fit gap-1 border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-200">
                                                <CheckCircle2 class="h-3 w-3" />
                                                Scheduled
                                            </Badge>
                                            <p class="font-medium">{{ formatDate(attempt.defense_date) }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ formatClockTime(attempt.defense_time, 'Time not set') }}
                                                <template v-if="attempt.defense_time && attempt.defense_duration">
                                                    - {{ addMinutes(attempt.defense_time, attempt.defense_duration) }}
                                                </template>
                                            </p>
                                            <p class="flex items-center gap-1 text-xs text-muted-foreground">
                                                <MapPin class="h-3 w-3" />
                                                {{ attempt.defense_room || 'Room not set' }}
                                            </p>
                                        </div>
                                        <Badge v-else variant="outline" class="gap-1 border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-200">
                                            <Clock class="h-3 w-3" />
                                            Needs scheduling
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'middle')">
                                        <div v-if="attemptTurnedInPaper(attempt)" class="flex flex-col gap-2">
                                            <Badge variant="outline" class="w-fit border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-200">
                                                Document submitted
                                            </Badge>
                                            <Button v-if="canViewAttemptDocument(attempt)" size="sm" variant="ghost" class="h-7 w-fit gap-1 px-2 text-xs" as-child>
                                                <Link :href="paperShow.url(attemptTurnedInPaper(attempt)!.id)">
                                                    <Eye class="h-3 w-3" />
                                                    Open
                                                </Link>
                                            </Button>
                                        </div>
                                        <!-- Student's attached-but-not-turned-in draft (hidden from judges/teacher) -->
                                        <div v-else-if="isStudent && !isOwnerOrAdmin && attemptHasDraftOnly(attempt)" class="flex flex-col gap-1.5">
                                            <Badge variant="outline" class="w-fit gap-1 border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-200">
                                                <Clock class="h-3 w-3" />
                                                Attached — not turned in
                                            </Badge>
                                            <Button size="sm" variant="ghost" class="h-7 w-fit gap-1 px-2 text-xs text-[#24327a]" as-child>
                                                <Link :href="paperShow.url(attemptPaper(attempt)!.id)">
                                                    <Upload class="h-3 w-3" />
                                                    Review & turn in
                                                </Link>
                                            </Button>
                                        </div>
                                        <div v-else class="flex flex-col gap-1.5">
                                            <Badge variant="outline" class="w-fit border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-200">
                                                Waiting for document
                                            </Badge>
                                            <!-- Upload deadline — shown to everyone, including students -->
                                            <p
                                                v-if="attempt.paper_upload_deadline_at"
                                                class="flex items-center gap-1 text-[11px] font-medium"
                                                :class="new Date(attempt.paper_upload_deadline_at) < new Date() ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'"
                                            >
                                                <Clock class="h-3 w-3" />
                                                <span>
                                                    {{ new Date(attempt.paper_upload_deadline_at) < new Date() ? 'Upload closed' : 'Upload due' }}:
                                                    {{ formatDateTime(attempt.paper_upload_deadline_at) }}
                                                </span>
                                            </p>
                                            <p v-else class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                                <Clock class="h-3 w-3" />
                                                No upload deadline set
                                            </p>
                                            <button
                                                v-if="isOwnerOrAdmin"
                                                type="button"
                                                class="flex w-fit items-center gap-1 text-[11px] font-medium text-amber-700 hover:text-amber-800 dark:text-amber-400"
                                                @click="openExtendUpload(attempt)"
                                            >
                                                <Clock class="h-3 w-3" />
                                                Extend upload
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'middle')">
                                        <div class="flex flex-col gap-2">
                                            <div class="flex flex-wrap gap-1.5">
                                                <Badge variant="outline" class="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-200">
                                                    {{ activeAttemptAssignments(attempt).length }} approved
                                                </Badge>
                                                <Badge v-if="pendingAttemptAssignments(attempt).length" variant="outline" class="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-200">
                                                    {{ pendingAttemptAssignments(attempt).length }} pending
                                                </Badge>
                                            </div>
                                            <div v-if="activeAttemptAssignments(attempt).length" class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="assignment in sortedActiveAttemptAssignments(attempt)"
                                                    :key="'active-' + assignment.id"
                                                    class="rounded-full bg-[#24327a]/10 px-2 py-0.5 text-xs font-medium text-[#24327a] dark:bg-blue-400/10 dark:text-blue-200 dark:ring-1 dark:ring-blue-400/20"
                                                >
                                                    {{ assignment.reviewer.name }} · {{ attemptRoleDisplayLabel(assignment) }}
                                                </span>
                                            </div>
                                            <div v-if="isOwnerOrAdmin && pendingAttemptAssignments(attempt).length" class="flex flex-col gap-1.5">
                                                <div
                                                    v-for="assignment in pendingAttemptAssignments(attempt)"
                                                    :key="'pending-' + assignment.id"
                                                    class="flex items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs dark:border-amber-500/40 dark:bg-amber-500/15"
                                                >
                                                    <span class="font-medium text-amber-900 dark:text-amber-100">{{ assignment.reviewer.name }}</span>
                                                    <span class="flex items-center gap-1">
                                                        <Button size="sm" class="h-6 px-2 text-xs" @click="approveReviewerAssignment(attempt, assignment.reviewer_id)">
                                                            Approve
                                                        </Button>
                                                    <Button size="sm" variant="ghost" class="h-6 px-2 text-xs text-destructive dark:text-red-300 dark:hover:text-red-200" @click="rejectReviewerAssignment(attempt, assignment.reviewer_id)">
                                                            Reject
                                                        </Button>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.attempts.length, 'last')">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <!-- Primary action: schedule the session -->
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
                                            <!-- Secondary actions tucked into an overflow menu -->
                                            <DropdownMenu v-if="isOwnerOrAdmin">
                                                <DropdownMenuTrigger as-child>
                                                    <Button size="sm" variant="outline" class="h-8 w-8 p-0" title="More actions">
                                                        <MoreHorizontal class="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="w-48">
                                                    <DropdownMenuItem @click="openJudgeDialog(attempt)">
                                                        <ShieldCheck class="mr-2 h-3.5 w-3.5" />
                                                        Manage Judges
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        v-if="selectedDefensePeriod"
                                                        class="text-amber-700"
                                                        @click="requestAddReDefense(selectedDefensePeriod.id, attempt.team_id, attempt.team.name, selectedDefensePeriod.name)"
                                                    >
                                                        <RefreshCw class="mr-2 h-3.5 w-3.5" />
                                                        Add Re-defense
                                                    </DropdownMenuItem>
                                                    <template v-if="attempt.attempt_type === 're_defense'">
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            class="text-red-600"
                                                            @click="requestRemoveReDefense(attempt.id, attempt.team.name, `Defense Session ${attempt.attempt_number}`)"
                                                        >
                                                            <Trash2 class="mr-2 h-3.5 w-3.5" />
                                                            Remove re-defense
                                                        </DropdownMenuItem>
                                                    </template>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                            <template v-if="!isOwnerOrAdmin && isSubjectReviewer">
                                                <Button
                                                    v-if="!currentReviewerAssignment(attempt)"
                                                    size="sm"
                                                    class="h-8 gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]"
                                                    @click="requestReviewerAssignment(attempt)"
                                                >
                                                    <Send class="h-3.5 w-3.5" />
                                                    Join the Defense
                                                </Button>
                                                <Badge
                                                    v-else-if="currentReviewerAssignment(attempt)?.status === 'pending'"
                                                    variant="outline"
                                                    class="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/40 dark:bg-amber-500/15 dark:text-amber-200"
                                                >
                                                    Waiting approval
                                                </Badge>
                                                <Badge
                                                    v-else-if="currentReviewerAssignment(attempt)?.status === 'rejected'"
                                                    variant="outline"
                                                    class="border-red-200 bg-red-50 text-red-700 dark:border-red-500/40 dark:bg-red-500/15 dark:text-red-200"
                                                >
                                                    Request rejected
                                                </Badge>
                                                <Button
                                                    v-else-if="currentReviewerAssignment(attempt)?.status === 'active' && attemptPaper(attempt)"
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-8 gap-1.5 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-100 dark:hover:bg-slate-800"
                                                    as-child
                                                >
                                                    <Link :href="paperShow.url(attemptPaper(attempt)!.id)">
                                                        <Eye class="h-3.5 w-3.5" />
                                                        View Defense Info
                                                    </Link>
                                                </Button>
                                                <Badge v-else variant="outline" class="border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-200">
                                                    Waiting for document
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
                                                    Upload Document
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                    </tr>
                                </template>
                                <tr v-if="groupedRoundAttempts.length === 0">
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-muted-foreground">
                                        No teams match this filter.
                                        <button type="button" class="font-medium text-[#24327a] hover:underline dark:text-blue-300" @click="roundFilter = 'all'">Clear filter</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-else class="px-6 py-16 text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                <UsersRound class="h-6 w-6 text-slate-400" />
                            </div>
                            <p class="mt-3 text-sm font-medium text-foreground">No teams in this round</p>
                            <p class="mt-1 text-xs text-muted-foreground">Create teams in the Team tab, then return here to schedule their defense sessions.</p>
                        </div>
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
                                    Submitted Documents
                                </CardTitle>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ subject.papers.length }} document{{ subject.papers.length === 1 ? '' : 's' }} submitted across all teams.
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <div v-if="subject.papers.length > 1" class="flex items-center gap-1.5">
                                <span class="hidden text-xs font-medium uppercase tracking-wide text-muted-foreground sm:inline">Filter</span>
                                <Select v-model="docSortKey">
                                    <SelectTrigger class="h-9 w-[150px] rounded-xl bg-white text-sm"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="team">Team name</SelectItem>
                                        <SelectItem value="score">Score</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button size="sm" variant="outline" class="h-9 w-9 rounded-xl bg-white p-0" :title="docSortDir === 'asc' ? 'Ascending' : 'Descending'" @click="toggleDocSortDir">
                                    <ArrowDownUp class="h-4 w-4" :class="docSortDir === 'desc' ? 'text-[#24327a]' : 'text-muted-foreground'" />
                                </Button>
                            </div>
                            <Button v-if="isStudent" size="sm" class="gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]" as-child>
                                <Link :href="paperCreate.url(subject.id)">
                                    <Upload class="h-3.5 w-3.5" />
                                    Submit Document
                                </Link>
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="subject.papers.length === 0" class="px-6 py-16 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                            <FileText class="h-6 w-6 text-slate-400" />
                        </div>
                        <p class="mt-3 text-sm font-medium text-foreground">No documents submitted yet</p>
                        <p v-if="isStudent" class="mt-1 text-xs text-muted-foreground">Submit a document from your team to get started.</p>
                        <p v-else class="mt-1 text-xs text-muted-foreground">Documents will appear here once teams start submitting.</p>
                    </div>
                    <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1120px] border-separate border-spacing-0 text-sm">
                        <thead>
                            <tr class="bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-900/40 dark:text-slate-400">
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Team</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Defense Session</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Document</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Reviews</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Score</th>
                                <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Status</th>
                                <th class="border-b border-slate-200 px-6 py-3 text-right dark:border-slate-800">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(group, gIdx) in documentGroups" :key="'paper-group-' + group.teamId">
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
                                                teamColor(group.teamId, paper.team.name).dot,
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
                                                Document submitted
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
                                            :class="allReviewsSubmitted(paper)
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-amber-200 bg-amber-50 text-amber-700'"
                                        >
                                            {{ submittedReviewCount(paper) }} / {{ reviewTotalCount(paper) }} submitted
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                        <span v-if="paperScore(paper) !== null" class="font-semibold">
                                            {{ paperScoreLabel(paper) }}
                                            <Star v-if="(paperScore(paper) ?? 0) >= subject.passing_score" class="ml-0.5 inline h-3 w-3 text-amber-500" />
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
                                                View Document
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
                                <div class="flex flex-col gap-2 sm:w-[28rem]">
                                    <div class="flex gap-2">
                                        <Input v-model="teamForm.name" placeholder="New team name" class="h-9 max-w-xs" required />
                                        <Button type="submit" size="sm" class="h-9 shrink-0 gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]" :disabled="teamForm.processing">
                                            <UserPlus class="h-3.5 w-3.5" />
                                            Create Team
                                        </Button>
                                    </div>
                                    <Input v-model="teamForm.topic" placeholder="Project topic" class="h-9" />
                                </div>
                                <p v-if="teamForm.errors.name" class="text-xs text-destructive">{{ teamForm.errors.name }}</p>
                                <p v-if="teamForm.errors.topic" class="text-xs text-destructive">{{ teamForm.errors.topic }}</p>
                            </form>
                        </div>
                    </CardHeader>
                </Card>

                <div v-if="subject.teams.length === 0 && isOwnerOrAdmin" class="rounded-xl border bg-card px-6 py-16 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                        <UsersRound class="h-6 w-6 text-slate-400" />
                    </div>
                    <p class="mt-3 text-sm font-medium text-foreground">No teams yet</p>
                    <p class="mt-1 text-xs text-muted-foreground">Use the form above to create the first team.</p>
                </div>

                <!-- Student not in a team yet: prominent create-your-team card -->
                <div v-if="!isOwnerOrAdmin && isStudent && myTeams.length === 0" class="rounded-xl border-2 border-dashed border-[#24327a]/30 bg-[#24327a]/[0.03] px-6 py-8 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#24327a]/10 text-[#24327a]">
                        <UsersRound class="h-6 w-6" />
                    </div>
                    <p class="mt-3 text-sm font-semibold text-foreground">You're not in a team yet</p>
                    <p class="mt-1 text-xs text-muted-foreground">Create your own team for this subject, then add your teammates and advisor.</p>
                    <form @submit.prevent="createTeam" class="mx-auto mt-4 flex max-w-md flex-col gap-2">
                        <div class="flex gap-2">
                            <Input v-model="teamForm.name" placeholder="Team name, e.g. Team 3" class="h-9" required />
                            <Button type="submit" size="sm" class="h-9 shrink-0 gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]" :disabled="teamForm.processing">
                                <UserPlus class="h-3.5 w-3.5" />
                                Create My Team
                            </Button>
                        </div>
                        <Input v-model="teamForm.topic" placeholder="Project topic (optional)" class="h-9" />
                        <p v-if="teamForm.errors.name" class="text-left text-xs text-destructive">{{ teamForm.errors.name }}</p>
                    </form>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="team in visibleTeamCards"
                        :key="team.id"
                        class="overflow-hidden border-l-4"
                        :class="teamColor(team).border"
                    >
                        <CardHeader class="pb-3" :class="teamColor(team).header">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <CardTitle class="flex items-center gap-2 text-base font-bold">
                                        <UsersRound class="h-4 w-4 shrink-0" :class="teamColor(team).icon" />
                                        <span class="truncate">{{ team.name }}</span>
                                        <span class="rounded-full px-1.5 py-0.5 text-[11px] font-semibold" :class="teamColor(team).header">{{ teamStudentMembers(team).length }}</span>
                                    </CardTitle>
                                    <div class="mt-2 rounded-lg border border-white/60 bg-white/70 px-3 py-2 shadow-sm dark:border-slate-800/60 dark:bg-background/70">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Project topic</p>
                                                <p
                                                    class="mt-0.5 line-clamp-2 text-xs font-medium leading-snug"
                                                    :class="team.topic ? 'text-foreground' : 'text-muted-foreground'"
                                                >
                                                    {{ team.topic || 'No topic set yet' }}
                                                </p>
                                            </div>
                                            <Button
                                                v-if="canManageStudentTeam(team)"
                                                variant="ghost"
                                                size="sm"
                                                class="h-7 shrink-0 gap-1 px-2 text-[11px] text-[#24327a] hover:bg-[#24327a]/10"
                                                @click="openTopicDialog(team)"
                                            >
                                                <Pencil class="h-3 w-3" />
                                                {{ team.topic ? 'Edit' : 'Set' }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button
                                        v-if="!isOwnerOrAdmin && isStudent && isCurrentStudentTeam(team)"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 w-7 p-0 text-muted-foreground hover:text-amber-600"
                                        title="Leave team"
                                        @click="requestLeaveTeam(team)"
                                    >
                                        <LogOut class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button v-if="isOwnerOrAdmin" variant="ghost" size="sm" class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive" title="Delete team" @click="requestDeleteTeam(team)">
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
                                            :class="teamColor(team).header + ' ' + teamColor(team).icon"
                                        >
                                            {{ member.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">{{ member.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ member.email }}</p>
                                        </div>
                                    </div>
                                    <!-- Owner: pending removal -> approve/reject; otherwise remove directly -->
                                    <template v-if="isOwnerOrAdmin">
                                        <div v-if="memberRemovalRequest(team, member.id)" class="flex shrink-0 items-center gap-1">
                                            <Button size="sm" class="h-6 px-2 text-xs" @click="approveTeamRequest(memberRemovalRequest(team, member.id)!.id)">Approve removal</Button>
                                            <Button size="sm" variant="ghost" class="h-6 px-2 text-xs" @click="rejectTeamRequest(memberRemovalRequest(team, member.id)!.id)">Keep</Button>
                                        </div>
                                        <Button v-else variant="ghost" size="sm" class="h-6 w-6 p-0 text-muted-foreground hover:text-destructive" title="Remove from team" @click="requestRemoveMember(team.id, member)">
                                            <UserMinus class="h-3 w-3" />
                                        </Button>
                                    </template>
                                    <!-- Student member: pending state or a request button (for teammates, not self) -->
                                    <template v-else-if="member.id !== user?.id && team.members.some((m) => m.id === user?.id)">
                                        <span v-if="memberRemovalRequest(team, member.id)" class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                            Removal pending
                                        </span>
                                        <Button
                                            v-else
                                            variant="ghost"
                                            size="sm"
                                            class="h-6 shrink-0 gap-1 px-2 text-xs text-muted-foreground hover:text-destructive"
                                            title="Ask the subject owner to remove this member"
                                            @click="openRemovalRequest('member', team.id, member.name, member.id)"
                                        >
                                            Request removal
                                        </Button>
                                    </template>
                                </div>
                                <p v-if="teamStudentMembers(team).length === 0" class="py-2 text-xs text-muted-foreground">No student members yet.</p>
                                <!-- Pending member invites (awaiting subject-owner approval) -->
                                <div
                                    v-for="req in (team.requests ?? []).filter((r) => r.role === 'member')"
                                    :key="'mem-req-' + req.id"
                                    class="flex items-center justify-between gap-2 py-1.5"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-medium text-slate-500 dark:bg-slate-800">
                                            {{ (req.user?.name ?? req.email).charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-muted-foreground">{{ req.user?.name ?? req.email }}</p>
                                            <p class="text-[11px] text-amber-600">Pending approval</p>
                                        </div>
                                    </div>
                                    <div v-if="isOwnerOrAdmin" class="flex shrink-0 items-center gap-1">
                                        <Button size="sm" class="h-6 px-2 text-xs" @click="approveTeamRequest(req.id)">Approve</Button>
                                        <Button size="sm" variant="ghost" class="h-6 px-2 text-xs text-destructive" @click="rejectTeamRequest(req.id)">Reject</Button>
                                    </div>
                                </div>
                            </div>

                            <!-- Advisor (set by the team or the instructor; not a judge) -->
                            <div class="flex flex-col gap-1.5 rounded-md border bg-amber-50/40 p-2 dark:bg-amber-950/20">
                                <div class="flex items-center justify-between gap-1.5">
                                    <p class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                        <BookOpen class="h-3.5 w-3.5" />
                                        Advisor
                                    </p>
                                    <Button
                                        v-if="canManageStudentTeam(team) && !team.advisor"
                                        variant="ghost"
                                        size="sm"
                                        class="h-6 gap-1 px-2 text-xs text-amber-700 hover:bg-amber-100 dark:text-amber-300"
                                        @click="openAdvisorDialog(team)"
                                    >
                                        <UserPlus class="h-3.5 w-3.5" />
                                        Add advisor
                                    </Button>
                                </div>
                                <div v-if="team.advisor" class="flex items-center justify-between gap-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                            {{ team.advisor.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium">{{ team.advisor.name }}</p>
                                            <p class="truncate text-xs text-muted-foreground">{{ team.advisor.email }}</p>
                                        </div>
                                    </div>
                                    <!-- Owner: pending removal -> approve/reject; otherwise remove directly -->
                                    <template v-if="isOwnerOrAdmin">
                                        <div v-if="advisorRemovalRequest(team)" class="flex shrink-0 items-center gap-1">
                                            <Button size="sm" class="h-6 px-2 text-xs" @click="approveTeamRequest(advisorRemovalRequest(team)!.id)">Approve removal</Button>
                                            <Button size="sm" variant="ghost" class="h-6 px-2 text-xs" @click="rejectTeamRequest(advisorRemovalRequest(team)!.id)">Keep</Button>
                                        </div>
                                        <Button
                                            v-else
                                            variant="ghost"
                                            size="sm"
                                            class="h-6 w-6 shrink-0 p-0 text-muted-foreground hover:text-destructive"
                                            title="Remove advisor"
                                            @click="removeAdvisor(team.id)"
                                        >
                                            <UserMinus class="h-3 w-3" />
                                        </Button>
                                    </template>
                                    <!-- Student member: show pending state or a request button -->
                                    <template v-else-if="team.members.some((m) => m.id === user?.id)">
                                        <span v-if="advisorRemovalRequest(team)" class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                            Removal pending
                                        </span>
                                        <Button
                                            v-else
                                            variant="ghost"
                                            size="sm"
                                            class="h-6 shrink-0 gap-1 px-2 text-xs text-muted-foreground hover:text-destructive"
                                            title="Ask the subject owner to remove this advisor"
                                            @click="openRemovalRequest('advisor', team.id, team.advisor.name)"
                                        >
                                            Request removal
                                        </Button>
                                    </template>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">No advisor yet.</p>

                                <!-- Pending advisor invite (awaiting subject-owner approval) -->
                                <div
                                    v-for="req in (team.requests ?? []).filter((r) => r.role === 'advisor')"
                                    :key="'adv-req-' + req.id"
                                    class="flex items-center justify-between gap-2 rounded border border-dashed border-amber-300 px-2 py-1.5"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-medium text-amber-800 dark:text-amber-300">{{ req.user?.name ?? req.email }}</p>
                                        <p class="text-[11px] text-muted-foreground">Advisor invite · pending approval</p>
                                    </div>
                                    <div v-if="isOwnerOrAdmin" class="flex shrink-0 items-center gap-1">
                                        <Button size="sm" class="h-6 px-2 text-xs" @click="approveTeamRequest(req.id)">Approve</Button>
                                        <Button size="sm" variant="ghost" class="h-6 px-2 text-xs text-destructive" @click="rejectTeamRequest(req.id)">Reject</Button>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Student Dialog -->
                            <Dialog v-if="canManageStudentTeam(team)" :open="addMemberTeamId === team.id" @update:open="(v) => { if (!v) { addMemberTeamId = null; memberForm.reset(); memberForm.clearErrors(); } else { addMemberTeamId = team.id; } }">
                                <DialogTrigger as-child>
                                    <Button variant="outline" size="sm" class="w-full gap-1.5 text-xs" @click="addMemberTeamId = team.id">
                                        <UserPlus class="h-3.5 w-3.5" />
                                        Add Student
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
                                            <DialogTitle>Add student to {{ team.name }}</DialogTitle>
                                            <DialogDescription>
                                                Pick from enrolled students or invite a student by email. Judges are assigned in Evaluation Rounds.
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
	                                            <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Or add student by email</p>
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

                            <div v-if="isStudent && isCurrentStudentTeam(team)" class="flex items-center justify-between border-t pt-2">
                                <span class="text-xs text-muted-foreground">Score</span>
                                <span v-if="teamPaperScore(team.id) !== null" class="text-sm font-semibold">
                                    {{ teamPaperScoreLabel(team.id) }}
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
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
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
                            <!-- Case-insensitive search by name or email -->
                            <div class="relative w-full md:w-72">
                                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    v-model="memberSearch"
                                    type="text"
                                    placeholder="Search members by name or email…"
                                    class="h-9 bg-white pl-9 dark:bg-background"
                                />
                                <button
                                    v-if="memberSearch"
                                    type="button"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    @click="memberSearch = ''"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <!-- Pending Reviewer Requests — one place, one-click approval -->
                <Card v-if="isOwnerOrAdmin && allPendingReviewerRequests.length > 0" class="overflow-hidden border-l-4 border-l-violet-500">
                    <CardHeader class="border-b bg-violet-50/50 pb-3 dark:bg-violet-950/20">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                <ShieldCheck class="h-4 w-4 text-violet-600" />
                                Pending Reviewer Requests ({{ allPendingReviewerRequests.length }})
                            </CardTitle>
                            <Button size="sm" class="h-8 gap-1.5 bg-violet-600 text-white hover:bg-violet-700" @click="approveAllReviewerRequests">
                                <Check class="h-3.5 w-3.5" />
                                Approve all
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="flex flex-col divide-y">
                            <div
                                v-for="req in allPendingReviewerRequests"
                                :key="'preq-' + req.attempt.id + '-' + req.assignment.reviewer_id"
                                class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-xs font-semibold text-violet-700 dark:bg-violet-950 dark:text-violet-300">
                                        {{ req.assignment.reviewer.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">{{ req.assignment.reviewer.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            wants to review <span class="font-medium text-foreground">{{ req.attempt.team.name }}</span> · {{ req.periodName }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <Button size="sm" class="h-7 gap-1 px-2.5 text-xs" @click="approveReviewerAssignment(req.attempt, req.assignment.reviewer_id)">
                                        <Check class="h-3.5 w-3.5" />
                                        Approve
                                    </Button>
                                    <Button size="sm" variant="ghost" class="h-7 px-2.5 text-xs text-destructive hover:bg-destructive/10" @click="rejectReviewerAssignment(req.attempt, req.assignment.reviewer_id)">
                                        Reject
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-4 lg:grid-cols-2">
                <!-- Pending Member Requests -->
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
                <Card class="border-l-4 border-l-emerald-500">
                    <CardHeader class="pb-3 bg-emerald-50/50 dark:bg-emerald-950/20">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                <Users class="h-4 w-4 text-emerald-600" />
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
                        <div v-if="filteredStudents.length > 0" class="flex max-h-[24rem] flex-col divide-y overflow-y-auto pr-1">
                            <div v-for="student in filteredStudents" :key="student.id" class="flex items-center justify-between py-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
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
                        <div v-else-if="memberSearch" class="py-6 text-center text-sm text-muted-foreground">
                            No students match “{{ memberSearch }}”.
                        </div>
                        <div v-else class="flex flex-col items-center py-6 text-center">
                            <Users class="mb-2 h-7 w-7 text-muted-foreground/40" />
                            <p class="text-sm text-muted-foreground">No students enrolled yet.</p>
                            <p class="mt-0.5 text-xs text-muted-foreground">Share the classroom code or enroll students manually.</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Review Panel -->
                <Card class="border-l-4 border-l-blue-500">
                    <CardHeader class="pb-3 bg-blue-50/50 dark:bg-blue-950/20">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                <ShieldCheck class="h-4 w-4 text-blue-600" />
                                Review Panel ({{ reviewPanel.length }})
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
                                            Add a committee member to review documents in this subject. If they don't have an account yet, they'll receive an invitation email.
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
                                            <label class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                                                Committee Role <span class="text-destructive">*</span>
                                                <InfoTip
                                                    text="Advisor — the team's project supervisor.&#10;Custom role — any review-panel member; you'll name it yourself.&#10;Scoring roles (Technical / Academic examiner) are assigned later per defense session.&#10;The FYP Instructor is the subject owner and is added automatically."
                                                />
                                            </label>
                                            <Select v-model="reviewerForm.committee_role" required>
                                                <SelectTrigger class="h-12 w-full rounded-xl border-[#212e70]/15 bg-background px-4 text-base shadow-sm">
                                                    <SelectValue placeholder="Select a role" />
                                                </SelectTrigger>
                                                <SelectContent class="rounded-xl">
                                                    <SelectItem value="advisor">Advisor</SelectItem>
                                                    <SelectItem value="technical_examiner">Technical examiner</SelectItem>
                                                    <SelectItem value="academic_examiner">Academic examiner</SelectItem>
                                                    <SelectItem value="custom">Custom role</SelectItem>
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
                        <div v-if="filteredReviewPanel.length === 0 && memberSearch" class="py-6 text-center text-sm text-muted-foreground">
                            No reviewers match “{{ memberSearch }}”.
                        </div>
                        <div v-else class="flex max-h-[24rem] flex-col divide-y overflow-y-auto pr-1">
                            <!-- Organizer first, then each reviewer -->
                            <div v-for="member in filteredReviewPanel" :key="'panel-' + member.id" class="flex items-center justify-between py-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                        {{ member.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ member.name }}
                                            <span v-if="member.isOwner" class="text-xs font-normal text-muted-foreground">(you)</span>
                                        </p>
                                        <div class="mt-0.5 flex items-center gap-1.5">
                                            <p v-if="member.email" class="text-xs text-muted-foreground">{{ member.email }}</p>
                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                                :class="member.isOwner ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : roleBadgeClass(subject.reviewers.find((r) => r.id === member.id)?.pivot.role ?? '')"
                                            >
                                                {{ member.roleLabel }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <Button
                                    v-if="isSubjectOwner && !member.isOwner"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive"
                                    @click="requestRemoveReviewer(subject.reviewers.find((r) => r.id === member.id)!)"
                                >
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
                        <p v-if="reviewPanel.length === 1 && subject.pending_invitations.length === 0" class="mt-3 text-center text-xs text-muted-foreground">
                            You're the only panel member so far. Invite committee members or share the reviewer code.
                        </p>
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
                        v-for="team in visibleTeamCards"
                        :key="'sch-' + team.id"
                        class="overflow-hidden border-l-4"
                        :class="teamColor(team).border"
                    >
                        <!-- Card header with tinted background -->
                        <CardHeader class="pb-2" :class="teamColor(team).header">
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                                    <UsersRound class="h-4 w-4" :class="teamColor(team).icon" />
                                    {{ team.name }}
                                </CardTitle>
                                <Button
                                    v-if="isOwnerOrAdmin"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 gap-1.5 text-xs"
                                    :class="teamColor(team).icon"
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
                                <div class="flex items-start gap-2.5 rounded-lg border px-3 py-2.5" :class="teamColor(team).header">
                                    <Calendar class="mt-0.5 h-4 w-4 shrink-0" :class="teamColor(team).icon" />
                                    <div>
                                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Defense Date & Time</p>
                                        <p class="text-sm font-bold">{{ formatDate(team.defense_date) }}</p>
                                        <p v-if="team.defense_time" class="text-xs text-muted-foreground">
                                            {{ formatClockTime(team.defense_time) }}
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
                                    <!-- Results status — never claims a result before a score exists. -->
                                    <div class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs">
                                        <CheckCircle2 class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                        <span class="text-muted-foreground">Results</span>
                                        <span class="ml-auto">
                                            <Badge
                                                v-if="team.results_released_at && teamPaperScore(team.id) !== null"
                                                class="border-emerald-200 bg-emerald-100 text-[11px] text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                                variant="outline"
                                            >
                                                Released
                                            </Badge>
                                            <Badge
                                                v-else-if="team.results_released_at"
                                                class="border-amber-200 bg-amber-50 text-[11px] text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                                variant="outline"
                                            >
                                                Release pending score
                                            </Badge>
                                            <Badge
                                                v-else-if="teamPaperScore(team.id) !== null"
                                                class="border-blue-200 bg-blue-50 text-[11px] text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                                                variant="outline"
                                            >
                                                Ready to release
                                            </Badge>
                                            <Badge
                                                v-else
                                                class="border-slate-200 bg-slate-50 text-[11px] text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                                                variant="outline"
                                            >
                                                Awaiting scores
                                            </Badge>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state -->
                            <div v-else class="flex flex-col items-center gap-2 py-5 text-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full" :class="teamColor(team).header">
                                    <Calendar class="h-5 w-5" :class="teamColor(team).icon" />
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
                        <p class="mt-1 text-xs text-muted-foreground">Scores appear here once teams submit documents and reviewers grade them.</p>
                    </CardContent>
                </Card>

                <Card v-else class="overflow-hidden">
                    <CardHeader class="border-b bg-gradient-to-br from-[#24327a]/5 via-white to-white px-6 py-5 dark:from-[#24327a]/10 dark:via-background dark:to-background">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
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
                            <div v-if="subject.papers.length > 1" class="flex items-center gap-1.5">
                                <span class="hidden text-xs font-medium uppercase tracking-wide text-muted-foreground sm:inline">Filter</span>
                                <Select v-model="scoreSortKey">
                                    <SelectTrigger class="h-9 w-[150px] rounded-xl bg-white text-sm"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="team">Team name</SelectItem>
                                        <SelectItem value="score">Score</SelectItem>
                                        <SelectItem value="status">Graded status</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button size="sm" variant="outline" class="h-9 w-9 rounded-xl bg-white p-0" :title="scoreSortDir === 'asc' ? 'Ascending' : 'Descending'" @click="toggleScoreSortDir">
                                    <ArrowDownUp class="h-4 w-4" :class="scoreSortDir === 'desc' ? 'text-[#24327a]' : 'text-muted-foreground'" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1120px] border-separate border-spacing-0 text-sm">
                                <thead>
                                    <tr class="bg-slate-50/80 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:bg-slate-900/40 dark:text-slate-400">
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Team</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Defense Session</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Reviews</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Score</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Deadline</th>
                                        <th class="border-b border-slate-200 px-6 py-3 dark:border-slate-800">Results</th>
                                        <th class="border-b border-slate-200 px-6 py-3 text-right dark:border-slate-800">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="(group, gIdx) in scoreGroups" :key="'score-group-' + group.teamId">
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
                                                        teamColor(group.teamId, paper.team.name).dot,
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
                                                    <span class="text-sm font-semibold">{{ submittedReviewCount(paper) }} / {{ reviewTotalCount(paper) }}</span>
                                                    <span class="text-xs text-muted-foreground">submitted reviews</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'middle')">
                                                <span v-if="paperScore(paper) !== null" class="font-semibold">
                                                    {{ paperScoreLabel(paper) }}
                                                    <Star v-if="(paperScore(paper) ?? 0) >= subject.passing_score" class="ml-0.5 inline h-3 w-3 text-amber-500" />
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
                                                <Badge v-else-if="hasPaperReleaseMarker(paper)" variant="secondary" class="text-xs">Release pending score</Badge>
                                                <Badge v-else variant="outline" class="text-xs">Pending</Badge>
                                            </td>
                                            <td class="px-6 py-4" :class="groupCellClasses(idx, group.papers.length, 'last')">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <Button variant="outline" size="sm" class="h-8 gap-1.5" as-child>
                                                        <Link :href="paperShow.url(paper.id)">
                                                            <Eye class="h-3.5 w-3.5" />
                                                            View Document
                                                        </Link>
                                                    </Button>
                                                    <template v-if="isOwnerOrAdmin && !isPaperResultReleased(paper)">
                                                        <!-- A team's result can only be released once a score has actually been calculated. -->
                                                        <Badge
                                                            v-if="paperScore(paper) === null"
                                                            variant="outline"
                                                            class="h-8 gap-1.5 border-slate-200 bg-slate-50 px-3 text-slate-500"
                                                            :title="'No score yet — at least one judge must submit a review before results can be released.'"
                                                        >
                                                            <Clock class="h-3.5 w-3.5" />
                                                            Awaiting scores
                                                        </Badge>
                                                        <template v-else>
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
                                                                    {{ !allReviewsSubmitted(paper) ? 'Not all submitted. ' : '' }}Confirm?
                                                                </span>
                                                                <Button size="sm" class="h-6 gap-1 text-xs" @click="releaseTeamScores(paper.team.id)">Yes</Button>
                                                                <Button size="sm" variant="ghost" class="h-6 text-xs" @click="releaseConfirmTeamId = null">No</Button>
                                                            </div>
                                                        </template>
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

            </div>
        </div>
    </div>

    <!-- Set / change the team project topic -->
    <Dialog
        :open="topicDialogTeam !== null"
        @update:open="(v) => { if (!v) { topicDialogTeam = null; topicForm.reset(); topicForm.clearErrors(); } }"
    >
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Project topic for {{ topicDialogTeam?.name }}</DialogTitle>
                <DialogDescription>
                    Add the project title students will present, for example: Scormetry 2.0: AI Rubric Generation and Secure Document Management for Academic Evaluations.
                </DialogDescription>
            </DialogHeader>
            <form v-if="topicDialogTeam" @submit.prevent="submitTeamTopic" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1.5">
                    <textarea
                        v-model="topicForm.topic"
                        maxlength="255"
                        rows="3"
                        placeholder="Enter the project topic or title"
                        class="min-h-24 rounded-xl border border-input bg-background px-3 py-2 text-sm shadow-sm outline-none transition focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <div class="flex items-center justify-between gap-3">
                        <p v-if="topicForm.errors.topic" class="text-xs text-destructive">{{ topicForm.errors.topic }}</p>
                        <p class="ml-auto text-xs text-muted-foreground">{{ topicForm.topic.length }}/255</p>
                    </div>
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" class="gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]" :disabled="topicForm.processing">
                        <Check class="h-3.5 w-3.5" />
                        Save Topic
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Set / change the team advisor by email -->
    <Dialog
        :open="advisorDialogTeam !== null"
        @update:open="(v) => { if (!v) { advisorDialogTeam = null; advisorForm.reset(); advisorForm.clearErrors(); } }"
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Add advisor to {{ advisorDialogTeam?.name }}</DialogTitle>
                <DialogDescription>
                    Enter your advisor's email. They'll be listed as this team's advisor. This does not make them a judge — the FYP instructor invites judges separately.
                </DialogDescription>
            </DialogHeader>
            <form v-if="advisorDialogTeam" @submit.prevent="submitAdvisor" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1.5">
                    <Input v-model="advisorForm.email" type="email" placeholder="advisor@email.com" required />
                    <p v-if="advisorForm.errors.email" class="text-xs text-destructive">{{ advisorForm.errors.email }}</p>
                </div>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button type="submit" class="gap-1.5" :disabled="advisorForm.processing">
                        <BookOpen class="h-3.5 w-3.5" />
                        Set Advisor
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Confirm sending a removal request to the subject owner -->
    <ConfirmDialog
        v-model:open="removalRequestOpen"
        title="Request removal?"
        :description="removalRequestDescription"
        cancel-text="Cancel"
        confirm-text="Send request"
        @confirm="confirmRemovalRequest"
    />

    <!-- Confirm leaving a team -->
    <ConfirmDialog
        v-model:open="leaveTeamConfirmOpen"
        title="Leave this team?"
        :description="leaveTeamDescription"
        cancel-text="Stay"
        confirm-text="Yes, Leave"
        @confirm="confirmLeaveTeam"
    />

    <!-- Shared Add Student dialog (opened from the Defense Sessions / rounds table) -->
    <Dialog
        v-if="isOwnerOrAdmin"
        :open="addStudentTeam !== null"
        @update:open="(v) => { if (!v) { addStudentTeam = null; memberForm.reset(); memberForm.clearErrors(); } }"
    >
        <DialogContent class="sm:max-w-md overflow-hidden p-0">
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

            <div v-if="addStudentTeam" class="p-6 flex flex-col gap-4">
                <DialogHeader>
                    <DialogTitle>Add Student to {{ addStudentTeam.name }}</DialogTitle>
                    <DialogDescription>
                        Pick from enrolled students or invite someone new by email. New users are enrolled as students in this subject.
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
                            @click="addExistingMember(addStudentTeam.id, student.id)"
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
                <form @submit.prevent="addMemberByEmail(addStudentTeam.id)" class="flex flex-col gap-3">
                    <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Or Invite by Email</p>
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

    <ConfirmDialog
        v-model:open="removeConfirmOpen"
        title="Remove Member"
        :description="removeConfirmDescription"
        cancel-text="Cancel"
        confirm-text="Yes, Remove"
        @confirm="confirmRemoveMember"
    />
    <ConfirmDialog
        v-model:open="removeMemberConfirmOpen"
        :title="removeMemberConfirmTitle"
        :description="removeMemberConfirmDescription"
        cancel-text="Cancel"
        confirm-text="Yes, Remove"
        @confirm="confirmRemoveTeamMember"
    />
    <ConfirmDialog
        v-model:open="deleteTeamConfirmOpen"
        title="Delete Team?"
        :description="deleteTeamConfirmDescription"
        cancel-text="Cancel"
        confirm-text="Yes, Delete Team"
        @confirm="confirmDeleteTeam"
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
        title="Add Re-defense Session?"
        :description="addReDefenseDescription"
        cancel-text="Cancel"
        confirm-text="Yes, Add Re-defense"
        @confirm="confirmAddReDefense"
    />
    <ConfirmDialog
        v-model:open="removeReDefenseConfirmOpen"
        title="Remove Re-defense Session?"
        :description="removeReDefenseDescription"
        cancel-text="Keep It"
        confirm-text="Yes, Remove"
        @confirm="confirmRemoveReDefense"
    />
    <!-- Per-round judge management dialog -->
    <Dialog v-model:open="judgeDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <ShieldCheck class="h-4 w-4 text-[#24327a]" />
                    Manage Judges
                </DialogTitle>
                <DialogDescription>
                    <strong>{{ judgeDialogTitle }}</strong>
                    <span v-if="judgeDialogSubtitle"> — {{ judgeDialogSubtitle }}</span>
                </DialogDescription>
            </DialogHeader>

            <div v-if="judgeDialogAttempt" class="space-y-5">
                <div class="rounded-xl border bg-slate-50/70 p-3 dark:bg-slate-900/30">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-foreground">Current judges for this round</p>
                            <p class="text-xs text-muted-foreground">Each row is one scoring role. The same judge can hold more than one role.</p>
                        </div>
                        <Badge variant="outline" class="bg-white dark:bg-background">
                            {{ activeAttemptAssignments(judgeDialogAttempt).length }} active
                        </Badge>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Removing a scoring role only removes this defense-session responsibility. Scores and feedback from other rounds stay saved.
                    </p>

                    <div v-if="activeAttemptAssignments(judgeDialogAttempt).length" class="mt-3 space-y-2">
                        <div
                            v-for="assignment in sortedActiveAttemptAssignments(judgeDialogAttempt)"
                            :key="'judge-active-' + assignment.id"
                            class="flex items-center justify-between gap-3 rounded-lg border bg-white px-3 py-2 dark:bg-background"
                        >
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-foreground">{{ assignment.reviewer.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            Current role: {{ attemptRoleDisplayLabel(assignment) }}
                                            <span v-if="isSubjectOwnerAssignment(assignment)"> · FYP instructor access</span>
                                        </p>
                                    </div>
                                    <Badge v-if="isSubjectOwnerAssignment(assignment)" variant="outline" class="shrink-0 border-slate-200 bg-slate-50 text-slate-600">
                                        Owner
                                    </Badge>
                                </div>

                                <!-- Inline remove confirm: replaces the row controls so the card keeps its size -->
                                <div
                                    v-if="confirmingRemoveAssignmentId === assignment.id"
                                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 dark:border-red-900/50 dark:bg-red-950/30"
                                >
                                    <p class="text-sm font-medium text-red-800 dark:text-red-200">Remove this scoring role only?</p>
                                    <div class="flex shrink-0 gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="h-9 text-xs"
                                            :disabled="removeAttemptReviewerProcessingId === assignment.id"
                                            @click="cancelRemoveAttemptReviewer"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            class="h-9 bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700"
                                            :disabled="removeAttemptReviewerProcessingId === assignment.id"
                                            @click="confirmRemoveAttemptReviewer(judgeDialogAttempt, assignment)"
                                        >
                                            {{ removeAttemptReviewerProcessingId === assignment.id ? 'Removing…' : 'Confirm remove' }}
                                        </Button>
                                    </div>
                                </div>

                                <div v-else class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <div v-if="isSubjectOwnerAssignment(assignment)" class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <Badge variant="outline" class="h-9 rounded-xl border-[#24327a]/20 bg-[#24327a]/10 px-3 text-sm font-semibold text-[#24327a]">
                                                FYP Instructor
                                            </Badge>
                                            <div v-if="ownerExaminerRoleLabel(assignment)" class="relative inline-flex">
                                                <Badge
                                                    variant="outline"
                                                    class="h-9 rounded-xl border-emerald-200 bg-emerald-50 px-3 pr-7 text-sm font-semibold text-emerald-700"
                                                >
                                                    {{ ownerExaminerRoleLabel(assignment) }}
                                                </Badge>
                                                <button
                                                    type="button"
                                                    class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full border border-emerald-200 bg-white text-emerald-700 shadow-sm transition hover:bg-red-50 hover:text-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                                                    title="Remove self-assigned judge role"
                                                    aria-label="Remove self-assigned judge role"
                                                    @click="clearOwnerExaminerRole(judgeDialogAttempt, assignment)"
                                                >
                                                    <X class="h-3 w-3" />
                                                </button>
                                            </div>
                                            <Button
                                                v-else-if="!ownerExaminerRoleEditorVisible(judgeDialogAttempt, assignment)"
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                class="h-9 rounded-xl border-[#24327a]/20 px-3 text-xs font-semibold text-[#24327a]"
                                                @click="startOwnerExaminerRole(judgeDialogAttempt, assignment)"
                                            >
                                                Assign self as judge
                                            </Button>
                                            <span v-else class="text-xs font-medium text-muted-foreground">· set this role</span>
                                        </div>
                                        <p v-if="!ownerExaminerRoleLabel(assignment) && !ownerExaminerRoleEditorVisible(judgeDialogAttempt, assignment)" class="text-xs text-muted-foreground">
                                            Use only when no Technical or Academic examiner is available for this session.
                                        </p>
                                        <div v-if="ownerExaminerRoleEditorVisible(judgeDialogAttempt, assignment)" class="grid gap-2 sm:grid-cols-2">
                                            <Select
                                                :model-value="assignmentRoleDraft(judgeDialogAttempt, assignment).committee_role"
                                                @update:model-value="(value) => setAssignmentRole(judgeDialogAttempt, assignment, String(value))"
                                            >
                                                <SelectTrigger class="h-9">
                                                    <SelectValue placeholder="Examiner role" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="role in attemptCommitteeRoleOptions"
                                                        :key="'owner-role-' + assignment.id + '-' + role.value"
                                                        :value="role.value"
                                                    >
                                                        {{ role.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <Input
                                                v-if="assignmentRoleDraft(judgeDialogAttempt, assignment).committee_role === 'custom'"
                                                :model-value="assignmentRoleDraft(judgeDialogAttempt, assignment).role_label"
                                                placeholder="Custom role"
                                                class="h-9"
                                                maxlength="100"
                                                @update:model-value="(value) => setAssignmentCustomRole(judgeDialogAttempt, assignment, String(value))"
                                            />
                                        </div>
                                    </div>
                                    <div v-else-if="isFixedAttemptRole(assignment)" class="flex items-center">
                                        <Badge variant="outline" class="h-9 rounded-xl border-[#24327a]/20 bg-[#24327a]/10 px-3 text-sm font-semibold text-[#24327a]">
                                            {{ attemptRoleDisplayLabel(assignment) }}
                                        </Badge>
                                    </div>
                                    <div v-else class="grid gap-2 sm:grid-cols-2">
                                        <Select
                                            :model-value="assignmentRoleDraft(judgeDialogAttempt, assignment).committee_role"
                                            @update:model-value="(value) => setAssignmentRole(judgeDialogAttempt, assignment, String(value))"
                                        >
                                            <SelectTrigger class="h-9">
                                                <SelectValue placeholder="Judge role" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="role in attemptCommitteeRoleOptions"
                                                    :key="'active-role-' + assignment.id + '-' + role.value"
                                                    :value="role.value"
                                                >
                                                    {{ role.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            v-if="assignmentRoleDraft(judgeDialogAttempt, assignment).committee_role === 'custom'"
                                            :model-value="assignmentRoleDraft(judgeDialogAttempt, assignment).role_label"
                                            placeholder="Custom role"
                                            class="h-9"
                                            maxlength="100"
                                            @update:model-value="(value) => setAssignmentCustomRole(judgeDialogAttempt, assignment, String(value))"
                                        />
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="(isSubjectOwnerAssignment(assignment) ? ownerExaminerRoleEditorVisible(judgeDialogAttempt, assignment) : !isFixedAttemptRole(assignment)) && assignmentRoleChanged(judgeDialogAttempt, assignment)"
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="h-9 shrink-0 text-xs"
                                            :disabled="!assignmentRoleCanSave(judgeDialogAttempt, assignment)"
                                            @click="saveAssignmentRole(judgeDialogAttempt, assignment)"
                                        >
                                            {{ isSubjectOwnerAssignment(assignment) ? 'Save judge role' : 'Save role' }}
                                        </Button>
                                        <span
                                            v-if="!isSubjectOwnerAssignment(assignment) && assignmentHasSubmittedScore(judgeDialogAttempt, assignment)"
                                            class="inline-flex h-9 shrink-0 items-center rounded-md border border-slate-200 bg-slate-50 px-3 text-xs font-medium text-slate-500 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-400"
                                            title="A submitted score is an academic record. Unlock the review first if a correction is needed."
                                        >
                                            Score submitted · locked
                                        </span>
                                        <Button
                                            v-else-if="!isSubjectOwnerAssignment(assignment)"
                                            type="button"
                                            size="sm"
                                            class="h-9 shrink-0 bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700"
                                            @click="requestRemoveAttemptReviewer(assignment)"
                                        >
                                            Remove
                                        </Button>
                                        <!-- Owner duplicate: allow removing an extra owner row (the FYP Instructor row always stays) -->
                                        <Button
                                            v-else-if="isSubjectOwnerAssignment(assignment) && ownerActiveAssignmentCount(judgeDialogAttempt) > 1 && assignment.committee_role !== 'fyp_instructor'"
                                            type="button"
                                            size="sm"
                                            class="h-9 shrink-0 bg-red-600 px-3 text-xs font-semibold text-white hover:bg-red-700"
                                            title="Remove this duplicate owner role"
                                            @click="requestRemoveAttemptReviewer(assignment)"
                                        >
                                            Remove duplicate
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        No judge has been assigned to this round yet.
                    </p>
                </div>

                <div v-if="pendingAttemptAssignments(judgeDialogAttempt).length" class="rounded-xl border border-amber-200 bg-amber-50/70 p-3">
                    <p class="text-sm font-semibold text-amber-950">Waiting for instructor approval</p>
                    <div class="mt-2 space-y-2">
                        <div
                            v-for="assignment in pendingAttemptAssignments(judgeDialogAttempt)"
                            :key="'judge-pending-' + assignment.id"
                            class="space-y-2 rounded-lg bg-white px-3 py-2 text-sm"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-amber-950">{{ assignment.reviewer.name }}</span>
                                <span class="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="h-7 px-2 text-xs"
                                        :disabled="!assignmentRoleCanSave(judgeDialogAttempt, assignment)"
                                        @click="approveReviewerAssignment(judgeDialogAttempt, assignment.reviewer_id)"
                                    >
                                    Approve
                                    </Button>
                                    <Button type="button" size="sm" variant="ghost" class="h-7 px-2 text-xs text-destructive" @click="rejectReviewerAssignment(judgeDialogAttempt, assignment.reviewer_id)">
                                    Reject
                                    </Button>
                                </span>
                            </div>
                            <div v-if="isFixedAttemptRole(assignment)" class="flex items-center">
                                <Badge variant="outline" class="h-9 rounded-xl border-[#24327a]/20 bg-[#24327a]/10 px-3 text-sm font-semibold text-[#24327a]">
                                    {{ attemptRoleDisplayLabel(assignment) }}
                                </Badge>
                            </div>
                            <div v-else class="grid gap-2 sm:grid-cols-2">
                                <Select
                                    :model-value="assignmentRoleDraft(judgeDialogAttempt, assignment).committee_role"
                                    @update:model-value="(value) => setAssignmentRole(judgeDialogAttempt, assignment, String(value))"
                                >
                                    <SelectTrigger class="h-9 bg-white">
                                        <SelectValue placeholder="Judge role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in attemptCommitteeRoleOptions"
                                            :key="'pending-role-' + assignment.id + '-' + role.value"
                                            :value="role.value"
                                        >
                                            {{ role.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    v-if="assignmentRoleDraft(judgeDialogAttempt, assignment).committee_role === 'custom'"
                                    :model-value="assignmentRoleDraft(judgeDialogAttempt, assignment).role_label"
                                    placeholder="Custom role"
                                    class="h-9 bg-white"
                                    maxlength="100"
                                    @update:model-value="(value) => setAssignmentCustomRole(judgeDialogAttempt, assignment, String(value))"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <form class="space-y-3 rounded-xl border p-3" @submit.prevent="addJudgeToAttempt">
                    <div>
                        <p class="text-sm font-semibold text-foreground">Assign a judge for this round</p>
                        <p class="text-xs text-muted-foreground">Use this when a judge cannot self-assign from the available team list.</p>
                    </div>
                    <div class="grid gap-2">
                        <Select v-model="attemptReviewerForm.reviewer_id">
                            <SelectTrigger class="h-10 flex-1">
                                <SelectValue placeholder="Choose an approved reviewer" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="reviewer in assignableAttemptReviewers"
                                    :key="'assignable-reviewer-' + reviewer.id"
                                    :value="String(reviewer.id)"
                                >
                                    {{ reviewer.name }} · {{ reviewerRoleLabel(reviewer) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                            <div v-if="selectedAttemptReviewerIsAdvisor" class="flex items-center">
                                <Badge variant="outline" class="h-10 rounded-xl border-[#24327a]/20 bg-[#24327a]/10 px-3 text-sm font-semibold text-[#24327a]">
                                    Advisor
                                </Badge>
                            </div>
                            <div v-else class="grid gap-2 sm:grid-cols-2">
                                <Select v-model="attemptReviewerForm.committee_role">
                                    <SelectTrigger class="h-10">
                                        <SelectValue placeholder="Judge role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in attemptCommitteeRoleOptions"
                                            :key="'assign-role-' + role.value"
                                            :value="role.value"
                                        >
                                            {{ role.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    v-if="attemptReviewerNeedsCustomRole"
                                    v-model="attemptReviewerForm.role_label"
                                    type="text"
                                    placeholder="Custom role"
                                    maxlength="100"
                                    class="h-10"
                                    required
                                />
                            </div>
                            <Button
                                type="submit"
                                class="gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]"
                                :disabled="attemptReviewerForm.processing || !attemptReviewerForm.reviewer_id || assignableAttemptReviewers.length === 0 || (attemptReviewerNeedsCustomRole && !attemptReviewerForm.role_label.trim())"
                            >
                                <UserPlus class="h-3.5 w-3.5" />
                                Assign
                            </Button>
                        </div>
                    </div>
                    <p v-if="attemptReviewerForm.errors.reviewer_id" class="text-xs text-destructive">{{ attemptReviewerForm.errors.reviewer_id }}</p>
                    <p v-if="attemptReviewerForm.errors.committee_role" class="text-xs text-destructive">{{ attemptReviewerForm.errors.committee_role }}</p>
                    <p v-if="attemptReviewerForm.errors.role_label" class="text-xs text-destructive">{{ attemptReviewerForm.errors.role_label }}</p>
                    <p v-else-if="assignableAttemptReviewers.length === 0" class="text-xs text-muted-foreground">
                        All approved reviewers are already active or pending for this round. Add a new reviewer in Members first.
                    </p>
                </form>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Done</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Late-upload extension dialog -->
    <Dialog v-model:open="extendUploadOpen">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Clock class="h-4 w-4 text-amber-600" />
                    Extend upload window
                </DialogTitle>
                <DialogDescription>
                    Reopen document upload for this defense session for a set time — even if the deadline already passed. The team is notified.
                </DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-2 py-1">
                <label class="text-xs font-medium text-muted-foreground">Reopen for</label>
                <div class="grid grid-cols-5 gap-1.5">
                    <button
                        v-for="h in [6, 12, 24, 48, 72]"
                        :key="h"
                        type="button"
                        class="rounded-lg border px-2 py-2 text-sm font-semibold transition-colors"
                        :class="extendUploadHours === h
                            ? 'border-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                            : 'border-border bg-background text-muted-foreground hover:bg-accent'"
                        @click="extendUploadHours = h"
                    >
                        {{ h }}h
                    </button>
                </div>
            </div>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancel</Button>
                </DialogClose>
                <Button class="gap-1.5 bg-amber-600 text-white hover:bg-amber-700" @click="confirmExtendUpload">
                    <Clock class="h-3.5 w-3.5" />
                    Reopen for {{ extendUploadHours }}h
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

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
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-medium">Document Upload Deadline</label>
                        <button
                            v-if="paperDeadlineCustomized"
                            type="button"
                            class="text-xs font-semibold text-[#24327a] underline-offset-4 hover:underline dark:text-blue-300"
                            @click="resetPaperDeadlineToAuto"
                        >
                            Use auto
                        </button>
                    </div>
                    <Input v-model="scheduleForm.paper_upload_deadline_at" type="datetime-local" @input="markPaperDeadlineCustomized" />
                    <p class="text-xs text-muted-foreground">
                        <span v-if="paperDeadlineCustomized" class="font-medium text-amber-700 dark:text-amber-300">Custom deadline.</span>
                        <span v-else class="font-medium text-emerald-700 dark:text-emerald-300">Auto:</span>
                        12:00 PM, 1 day before defense, {{ automaticPaperDeadlineLabel }}. Students can replace the PDF before this time.
                    </p>
                    <p v-if="scheduleForm.errors.paper_upload_deadline_at" class="text-xs text-destructive">{{ scheduleForm.errors.paper_upload_deadline_at }}</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-medium">Judge Score Deadline</label>
                        <button
                            v-if="scoreDeadlineCustomized"
                            type="button"
                            class="text-xs font-semibold text-[#24327a] underline-offset-4 hover:underline dark:text-blue-300"
                            @click="resetScoreDeadlineToAuto"
                        >
                            Use auto
                        </button>
                    </div>
                    <Input v-model="scheduleForm.score_deadline_at" type="datetime-local" @input="markScoreDeadlineCustomized" />
                    <p class="text-xs text-muted-foreground">
                        <span v-if="scoreDeadlineCustomized" class="font-medium text-amber-700 dark:text-amber-300">Custom deadline.</span>
                        <span v-else class="font-medium text-emerald-700 dark:text-emerald-300">Auto:</span>
                        12:00 PM, 1 day after defense, {{ automaticScoreDeadlineLabel }}. Completed drafts auto-submit at this deadline.
                    </p>
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
                        <p class="font-medium">{{ formatClockTime(scheduleForm.defense_time) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Duration</p>
                        <p class="font-medium">{{ scheduleForm.defense_duration ? scheduleForm.defense_duration + ' min' : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Room / Venue</p>
                        <p class="font-medium">{{ scheduleForm.defense_room || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Document deadline</p>
                        <p class="font-medium">{{ scheduleForm.paper_upload_deadline_at ? formatDateTime(scheduleForm.paper_upload_deadline_at) : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Score deadline</p>
                        <p class="font-medium">{{ scheduleForm.score_deadline_at ? formatDateTime(scheduleForm.score_deadline_at) : '—' }}</p>
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
