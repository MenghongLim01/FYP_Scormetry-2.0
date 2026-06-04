<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Calendar, FileText, Users, BookOpen, Clock, CheckCircle2, AlertCircle,
    Lock, ClipboardCheck, ChevronLeft, ChevronRight, ArrowRight, Pencil,
    Star, Send, LayoutList, CalendarDays, CalendarCheck2, Unlink,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogClose } from '@/components/ui/dialog';
import { computed, ref } from 'vue';
import { show as paperShow } from '@/actions/App/Http/Controllers/PaperController';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { create as reviewCreate } from '@/actions/App/Http/Controllers/ReviewController';
import { addMinutesToClockTime, formatClockTime, formatClockTimeRange, formatDateTimeWithAmPm } from '@/lib/utils';

type AssignedTeam = {
    id: number;
    team_id: number;
    name: string;
    defense_date: string | null;
    defense_time: string | null;
    defense_duration: number | null;
    defense_room: string | null;
    score_deadline_at: string | null;
    results_released_at: string | null;
    subject: { id: number; title: string; passing_score: number };
    criteria: Array<{ criteria: string; max_score: number; weight: number }>;
    members: Array<{ id: number; name: string; email: string }>;
    paper: { id: number; visibility_status: string } | null;
    review: {
        id: number;
        is_submitted: boolean;
        locked_at: string | null;
        unlocked_at: string | null;
        scores_json: Array<{ criteria: string; score: number }> | null;
    } | null;
};

type OwnedTeam = {
    id: number;
    team_id: number;
    name: string;
    defense_date: string | null;
    defense_time: string | null;
    defense_duration: number | null;
    defense_room: string | null;
    score_deadline_at: string | null;
    results_released_at: string | null;
    subject: { id: number; title: string; passing_score: number };
    members: Array<{ id: number; name: string; email: string }>;
    paper: { id: number; final_score: number | null; visibility_status: string } | null;
    review_summary: { submitted: number; total: number; released: boolean };
};

const props = defineProps<{
    teams: AssignedTeam[];
    ownedTeams: OwnedTeam[];
    googleCalendar: { connected: boolean; email: string | null };
}>();

// ── Google Calendar connection ────────────────────────────────────────────────
const page = usePage();
const calendarError = computed(() => (page.props.flash as { error?: string | null } | undefined)?.error ?? null);

function disconnectCalendar() {
    if (!confirm('Disconnect Google Calendar? Your synced defense events will be removed from your calendar.')) return;
    router.delete('/google-calendar/disconnect', { preserveScroll: true });
}

// ── Calendar constants ────────────────────────────────────────────────────────
const HOUR_HEIGHT = 64; // px per hour
const GRID_START  = 7;  // 07:00
const GRID_END    = 24; // 00:00 next day, so evening defenses stay inside the calendar
const HOURS       = Array.from({ length: GRID_END - GRID_START }, (_, i) => GRID_START + i);

// ── Date helpers ──────────────────────────────────────────────────────────────
function parseLocalDate(str: string): Date {
    const [y, m, d] = str.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function getWeekStart(date = new Date()): Date {
    const d = new Date(date);
    const day = d.getDay();
    d.setDate(d.getDate() - (day === 0 ? 6 : day - 1));
    d.setHours(0, 0, 0, 0);
    return d;
}

function toISODateStr(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function formatDateTime(val: string | null): string {
    return formatDateTimeWithAmPm(val);
}

function isToday(date: Date): boolean {
    return toISODateStr(date) === toISODateStr(new Date());
}

function isOverdue(deadline: string | null, submitted: boolean): boolean {
    if (!deadline || submitted) return false;
    return new Date(deadline) < new Date();
}

// ── Week navigation ───────────────────────────────────────────────────────────
function getInitialWeek(): Date {
    const all = [...props.teams, ...props.ownedTeams];
    const upcoming = all
        .filter(t => t.defense_date)
        .map(t => parseLocalDate(t.defense_date!))
        .sort((a, b) => a.getTime() - b.getTime());
    const nearest = upcoming.find(d => d >= new Date()) ?? upcoming[0] ?? new Date();
    return getWeekStart(nearest);
}

const weekStart = ref(getInitialWeek());

const weekDays = computed(() =>
    Array.from({ length: 7 }, (_, i) => {
        const d = new Date(weekStart.value);
        d.setDate(d.getDate() + i);
        return d;
    }),
);

const weekLabel = computed(() => {
    const s = weekDays.value[0];
    const e = weekDays.value[6];
    const start = s.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    const end   = e.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    return `${start} – ${end}`;
});

function prevWeek() {
    const d = new Date(weekStart.value);
    d.setDate(d.getDate() - 7);
    weekStart.value = d;
}
function nextWeek() {
    const d = new Date(weekStart.value);
    d.setDate(d.getDate() + 7);
    weekStart.value = d;
}
function goToday() {
    weekStart.value = getWeekStart();
}

// ── Schedule colors ───────────────────────────────────────────────────────────
const PALETTE = [
    { block: 'bg-orange-500 border-orange-600', text: 'text-white', dot: 'bg-orange-500', label: 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300' },
    { block: 'bg-amber-500 border-amber-600', text: 'text-white', dot: 'bg-amber-500', label: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' },
    { block: 'bg-cyan-500 border-cyan-600', text: 'text-white', dot: 'bg-cyan-500', label: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300' },
    { block: 'bg-orange-600 border-orange-700', text: 'text-white', dot: 'bg-orange-600', label: 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300' },
    { block: 'bg-blue-500 border-blue-600', text: 'text-white', dot: 'bg-blue-500', label: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' },
    { block: 'bg-rose-500 border-rose-600', text: 'text-white', dot: 'bg-rose-500', label: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' },
    { block: 'bg-emerald-500 border-emerald-600', text: 'text-white', dot: 'bg-emerald-500', label: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' },
];

const teamColorIndex = computed(() => {
    const map = new Map<number, number>();
    const all = [...props.teams, ...props.ownedTeams]
        .sort((a, b) => {
            const da = (a.defense_date ?? '9999-12-31') + (a.defense_time ?? '99:99') + a.name;
            const db = (b.defense_date ?? '9999-12-31') + (b.defense_time ?? '99:99') + b.name;
            return da.localeCompare(db, undefined, { numeric: true, sensitivity: 'base' });
        });
    all.forEach((team, index) => {
        const colorIndex = index % PALETTE.length;

        if (!map.has(team.id)) {
            map.set(team.id, colorIndex);
        }

        if (!map.has(team.team_id)) {
            map.set(team.team_id, colorIndex);
        }
    });
    return map;
});

function teamColor(id: number) {
    return PALETTE[teamColorIndex.value.get(id) ?? 0];
}

function formatDefenseTimeRange(team: { defense_time: string | null; defense_duration: number | null }): string {
    return formatClockTimeRange(team.defense_time, team.defense_duration);
}

// ── Review status ─────────────────────────────────────────────────────────────
type ReviewStatus = 'locked' | 'submitted' | 'unlocked' | 'draft' | 'not_started' | 'overdue';

function isAssignedTeam(team: AssignedTeam | OwnedTeam): team is AssignedTeam {
    return 'review' in team;
}

function reviewStatus(team: AssignedTeam | OwnedTeam): ReviewStatus {
    if (isAssignedTeam(team)) {
        const r = team.review;
        if (!r) return isOverdue(team.score_deadline_at, false) ? 'overdue' : 'not_started';
        if (r.is_submitted && r.locked_at)                   return 'locked';
        if (r.is_submitted && r.unlocked_at && !r.locked_at) return 'unlocked';
        if (r.is_submitted)                                  return 'submitted';
        return isOverdue(team.score_deadline_at, false) ? 'overdue' : 'draft';
    }
    // OwnedTeam: derive from review_summary
    const s = team.review_summary;
    if (s.released)                         return 'submitted';
    if (s.submitted > 0 && s.submitted === s.total) return 'submitted';
    if (s.submitted > 0)                    return 'draft';
    return 'not_started';
}

const STATUS_CFG: Record<ReviewStatus, { label: string; dot: string; icon: typeof Lock }> = {
    locked:      { label: 'Locked',      dot: 'bg-emerald-500', icon: Lock },
    submitted:   { label: 'Submitted',   dot: 'bg-emerald-500', icon: CheckCircle2 },
    unlocked:    { label: 'Edit Open',   dot: 'bg-amber-500',   icon: Pencil },
    draft:       { label: 'In Progress', dot: 'bg-blue-500',    icon: ClipboardCheck },
    not_started: { label: 'Not Started', dot: 'bg-slate-400',   icon: ClipboardCheck },
    overdue:     { label: 'Overdue',     dot: 'bg-red-500',     icon: AlertCircle },
};

// Compute side-by-side layout for overlapping blocks in one day column.
// Returns a map of team-id → full CSS position style (top / height / left / right).
function computeDayStyles(day: Date): Map<number, Record<string, string>> {
    const teams  = teamsOnDay(day).filter(t => !!t.defense_time);
    const result = new Map<number, Record<string, string>>();
    if (teams.length === 0) return result;

    function toMin(time: string): number {
        const [h, m] = time.split(':').map(Number);
        return h * 60 + m;
    }

    // Build interval list
    const items = teams.map(t => ({
        id:    t.id,
        start: toMin(t.defense_time!),
        end:   toMin(t.defense_time!) + (t.defense_duration ?? 60),
        col:   -1,
    }));

    // Greedy column assignment — place each item in the first free column
    const colEnds: number[] = [];
    for (const item of items) {
        let placed = false;
        for (let c = 0; c < colEnds.length; c++) {
            if (colEnds[c] <= item.start) {
                item.col   = c;
                colEnds[c] = item.end;
                placed     = true;
                break;
            }
        }
        if (!placed) {
            item.col = colEnds.length;
            colEnds.push(item.end);
        }
    }

    // Generate CSS for each item
    const GAP = 3; // px gap between adjacent columns
    for (const item of items) {
        const team = teams.find(t => t.id === item.id)!;
        const topMin   = item.start - GRID_START * 60;
        const duration = team.defense_duration ?? 60;

        // Count how many columns the overlap group needs
        const group = items.filter(o => o.start < item.end && o.end > item.start);
        const total  = Math.max(...group.map(o => o.col)) + 1;

        const pct = 100 / total;

        result.set(item.id, {
            position: 'absolute',
            top:    `${(topMin / 60) * HOUR_HEIGHT}px`,
            height: `${Math.max((duration / 60) * HOUR_HEIGHT, 62)}px`,
            left:   `calc(${item.col * pct}% + ${item.col === 0 ? 4 : GAP}px)`,
            right:  `calc(${(total - item.col - 1) * pct}% + ${item.col === total - 1 ? 4 : GAP}px)`,
        });
    }

    return result;
}

// Pre-compute styles for every visible day so the template doesn't call the
// function on every render cycle.
const dayLayoutStyles = computed(() => {
    const map = new Map<string, Map<number, Record<string, string>>>();
    for (const day of weekDays.value) {
        map.set(toISODateStr(day), computeDayStyles(day));
    }
    return map;
});

// Show all teams (assigned + owned) in the calendar
const activeTeams = computed<Array<AssignedTeam | OwnedTeam>>(() => [
    ...props.teams,
    ...props.ownedTeams.filter(o => !props.teams.some(t => t.id === o.id)),
]);

// ── Legend overflow ───────────────────────────────────────────────────────────
const LEGEND_MAX     = 5;
const legendExpanded = ref(false);
const legendVisible  = computed(() => activeTeams.value.slice(0, LEGEND_MAX));
const legendHidden   = computed(() => activeTeams.value.slice(LEGEND_MAX));

// ── Display mode (calendar vs list) ──────────────────────────────────────────
type DisplayMode = 'calendar' | 'list';
const displayMode = ref<DisplayMode>('calendar');

// All scheduled teams sorted by date+time for the list view
const allTeamsByDate = computed(() => {
    const sorted = [...activeTeams.value]
        .filter(t => t.defense_date)
        .sort((a, b) => {
            const da = (a.defense_date ?? '') + (a.defense_time ?? '');
            const db = (b.defense_date ?? '') + (b.defense_time ?? '');
            return da.localeCompare(db);
        });

    // Group by date
    const groups = new Map<string, Array<AssignedTeam | OwnedTeam>>();
    for (const t of sorted) {
        const key = t.defense_date!;
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key)!.push(t);
    }
    return groups;
});

function teamsOnDay(date: Date) {
    const key = toISODateStr(date);
    return activeTeams.value.filter(t => t.defense_date === key);
}

// ── Derived lists ─────────────────────────────────────────────────────────────
const scheduledTeams   = computed(() => activeTeams.value.filter(t => t.defense_date && t.defense_time));
const unscheduledTeams = computed(() => activeTeams.value.filter(t => !t.defense_date || !t.defense_time));

// Teams with at least a date (may or may not have a time) — sorted by date for "Jump" navigation
const teamsWithDate = computed(() =>
    [...activeTeams.value]
        .filter(t => !!t.defense_date)
        .sort((a, b) => (a.defense_date ?? '').localeCompare(b.defense_date ?? '')),
);

// Only true when at least one team on this week has BOTH date AND time (i.e. will actually render a block)
const hasAnyOnWeek = computed(() =>
    weekDays.value.some(d => teamsOnDay(d).some(t => !!t.defense_time)),
);

// ── Detail dialogs ────────────────────────────────────────────────────────────
const selected   = ref<AssignedTeam | null>(null);
const dialogOpen = computed({ get: () => selected.value !== null, set: (v) => { if (!v) selected.value = null; } });

const selectedOwned   = ref<OwnedTeam | null>(null);
const ownerDialogOpen = computed({ get: () => selectedOwned.value !== null, set: (v) => { if (!v) selectedOwned.value = null; } });

function openBlock(team: AssignedTeam | OwnedTeam) {
    if (isAssignedTeam(team)) {
        selected.value = team;
    } else {
        selectedOwned.value = team;
    }
}

function scoreLabel(score: number): string {
    return ['', 'Unsatisfactory', 'Satisfactory', 'Very Satisfactory', 'Excellent'][score] ?? '—';
}
function scoreColor(score: number): string {
    return ['', 'text-red-600 dark:text-red-400', 'text-amber-600 dark:text-amber-400',
        'text-blue-600 dark:text-blue-400', 'text-emerald-600 dark:text-emerald-400'][score] ?? '';
}

</script>

<template>
    <Head title="My Assigned Teams" />

    <div class="flex flex-col">

        <!-- Page header -->
        <div class="relative overflow-hidden bg-gradient-to-br from-primary to-[hsl(228_60%_35%)] px-6 pt-6 pb-20 text-white shadow-lg">
            <!-- faint dot grid -->
            <div class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: radial-gradient(circle, #fff 1px, transparent 1px); background-size: 24px 24px;" />
            <!-- glow blobs -->
            <div class="pointer-events-none absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-2xl" />
            <div class="pointer-events-none absolute bottom-0 left-1/3 h-24 w-24 rounded-full bg-blue-200/20 blur-2xl" />

            <div class="relative flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                            <ClipboardCheck class="h-5 w-5 text-white" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">My Assigned Teams</h1>
                    </div>
                    <p class="mt-1.5 text-sm text-white/70">Open your approved defense rooms, view the schedule, and continue reviews.</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1.5 rounded-full bg-white/15 px-3.5 py-1.5 text-sm font-semibold ring-1 ring-white/20">
                        <Users class="h-3.5 w-3.5" />
                        {{ activeTeams.length }} approved rooms
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating content -->
        <div class="relative z-10 -mt-12 flex flex-col gap-5 px-6 pb-6">

        <!-- Google Calendar connection -->
        <div class="rounded-2xl border bg-card p-4 shadow-md">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl"
                        :class="googleCalendar.connected ? 'bg-emerald-50 dark:bg-emerald-950' : 'bg-muted'">
                        <CalendarCheck2 class="h-5 w-5"
                            :class="googleCalendar.connected ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold">
                            <template v-if="googleCalendar.connected">Google Calendar connected</template>
                            <template v-else>Connect Google Calendar</template>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="googleCalendar.connected">
                                Synced to {{ googleCalendar.email }} — approved defense sessions appear in your calendar automatically.
                            </template>
                            <template v-else>
                                Add your approved defense sessions to your personal Google Calendar. Uses the same Google account you sign in with.
                            </template>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a v-if="!googleCalendar.connected" href="/google-calendar/connect">
                        <Button size="sm">
                            <CalendarCheck2 class="mr-1.5 h-4 w-4" /> Connect Google Calendar
                        </Button>
                    </a>
                    <Button v-else size="sm" variant="outline" @click="disconnectCalendar">
                        <Unlink class="mr-1.5 h-4 w-4" /> Disconnect
                    </Button>
                </div>
            </div>
            <p v-if="calendarError" class="mt-3 flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-2 text-xs font-medium text-red-700 dark:bg-red-950/50 dark:text-red-400">
                <AlertCircle class="h-3.5 w-3.5 shrink-0" /> {{ calendarError }}
            </p>
        </div>

        <!-- Empty state — only when there are truly no teams at all -->
        <div v-if="activeTeams.length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed bg-card py-20 text-center shadow-md">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-950">
                <Calendar class="h-7 w-7 text-indigo-400" />
            </div>
            <p class="text-base font-semibold text-muted-foreground">No assigned defense rooms yet</p>
            <p class="mt-1 text-sm text-muted-foreground/70">Defense rooms will appear here after the FYP instructor assigns you.</p>
        </div>

        <!-- Calendar card -->
        <div v-if="activeTeams.length > 0" class="overflow-hidden rounded-2xl border bg-card shadow-md">

            <!-- Gradient accent bar -->
            <div class="h-1 w-full bg-gradient-to-r from-primary to-[hsl(228_60%_35%)]" />

            <!-- Calendar toolbar -->
            <div class="flex items-center justify-between border-b bg-muted/20 px-4 py-3">
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" class="h-8 gap-1" @click="prevWeek">
                        <ChevronLeft class="h-4 w-4" />
                    </Button>
                    <span class="min-w-48 text-center text-sm font-semibold">{{ weekLabel }}</span>
                    <Button variant="outline" size="sm" class="h-8 gap-1" @click="nextWeek">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="sm" class="h-8 text-xs" @click="goToday">Today</Button>
                    <div class="mx-1 h-5 w-px bg-border" />
                    <!-- Calendar / List toggle -->
                    <Button
                        variant="ghost" size="sm"
                        class="h-8 gap-1.5 text-xs"
                        :class="displayMode === 'list' ? 'bg-accent text-accent-foreground' : ''"
                        @click="displayMode = displayMode === 'list' ? 'calendar' : 'list'"
                    >
                        <LayoutList v-if="displayMode === 'calendar'" class="h-3.5 w-3.5" />
                        <CalendarDays v-else class="h-3.5 w-3.5" />
                        {{ displayMode === 'list' ? 'Calendar' : 'List All' }}
                    </Button>
                </div>

                <!-- Legend — shows first 5 inline, overflow behind "+N more" chip -->
                <div class="hidden items-center gap-2 sm:flex">
                    <div
                        v-for="team in legendVisible"
                        :key="'leg-' + team.id"
                        class="flex items-center gap-1.5 text-xs"
                    >
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="teamColor(team.id).dot" />
                        <span class="max-w-[7rem] truncate text-muted-foreground">{{ team.name }}</span>
                    </div>

                    <!-- Overflow chip -->
                    <button
                        v-if="legendHidden.length > 0"
                        class="flex items-center gap-1 rounded-full border bg-muted/60 px-2 py-0.5 text-[11px] font-semibold text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                        @click="legendExpanded = !legendExpanded"
                    >
                        <template v-if="!legendExpanded">
                            +{{ legendHidden.length }} more
                        </template>
                        <template v-else>
                            Show less
                        </template>
                    </button>
                </div>
            </div>

            <!-- Expanded legend panel (shown when +N is clicked) -->
            <div
                v-if="legendExpanded && legendHidden.length > 0"
                class="border-b bg-muted/20 px-4 py-2.5"
            >
                <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                    <div
                        v-for="team in activeTeams"
                        :key="'legx-' + team.id"
                        class="flex items-center gap-1.5 text-xs"
                    >
                        <span class="h-2 w-2 shrink-0 rounded-full" :class="teamColor(team.id).dot" />
                        <span class="text-muted-foreground">{{ team.name }}</span>
                    </div>
                </div>
            </div>

            <!-- ── List view ────────────────────────────────────────────────── -->
            <div v-if="displayMode === 'list'" class="divide-y">
                <div v-if="allTeamsByDate.size === 0" class="px-6 py-10 text-center text-sm text-muted-foreground">
                    No scheduled defenses.
                </div>
                <template v-for="[date, dayTeams] in allTeamsByDate" :key="'day-' + date">
                    <!-- Date group header -->
                    <div class="flex items-center gap-3 bg-gradient-to-r from-indigo-50 to-violet-50/60 px-4 py-2 dark:from-indigo-950/30 dark:to-violet-950/20">
                        <CalendarDays class="h-3.5 w-3.5 text-indigo-400" />
                        <span class="text-xs font-semibold uppercase tracking-wide text-indigo-400 dark:text-white">
                            {{ parseLocalDate(date).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) }}
                        </span>
                        <span class="ml-auto rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                            {{ dayTeams.length }} {{ dayTeams.length === 1 ? 'team' : 'teams' }}
                        </span>
                    </div>
                    <!-- Team rows -->
                    <div
                        v-for="team in dayTeams"
                        :key="'lr-' + team.id"
                        class="flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/30"
                    >
                        <!-- Color strip -->
                        <span class="h-10 w-1 shrink-0 rounded-full" :class="teamColor(team.id).dot" />
                        <!-- Team info -->
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ team.subject.title }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ team.name }}</p>
                        </div>
                        <!-- Time -->
                        <div class="hidden shrink-0 text-right sm:block">
                            <p class="text-sm font-medium tabular-nums">
                                {{ formatDefenseTimeRange(team) }}
                            </p>
                            <p v-if="team.defense_duration" class="text-[11px] text-muted-foreground">{{ team.defense_duration }} min</p>
                        </div>
                        <!-- Room -->
                        <div v-if="team.defense_room" class="hidden shrink-0 text-right md:block">
                            <p class="text-xs text-muted-foreground">Room</p>
                            <p class="text-sm font-medium">{{ team.defense_room }}</p>
                        </div>
                        <!-- Status badge -->
                        <span
                            class="hidden shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold sm:flex"
                            :class="teamColor(team.id).label"
                        >
                            <component :is="STATUS_CFG[reviewStatus(team)].icon" class="h-3 w-3" />
                            {{ STATUS_CFG[reviewStatus(team)].label }}
                        </span>
                        <!-- Detail button -->
                        <Button size="sm" variant="outline" class="h-7 shrink-0 gap-1 text-xs" @click="openBlock(team)">
                            Details
                            <ArrowRight class="h-3 w-3" />
                        </Button>
                    </div>
                </template>
            </div>

            <!-- Calendar grid -->
            <div v-if="displayMode === 'calendar'" class="flex">
                <!-- Time gutter -->
                <div class="w-16 shrink-0 border-r">
                    <!-- Header spacer -->
                    <div class="h-14 border-b" />
                    <!-- Hour labels -->
                    <div
                        v-for="hour in HOURS"
                        :key="hour"
                        class="relative flex items-start justify-end pr-2 text-[10px] font-medium text-muted-foreground"
                        :style="{ height: HOUR_HEIGHT + 'px' }"
                    >
                        <span class="-translate-y-2">
                            {{ hour === 12 ? '12 PM' : hour < 12 ? hour + ' AM' : (hour - 12) + ' PM' }}
                        </span>
                    </div>
                </div>

                <!-- Day columns (scrollable wrapper) -->
                <div class="flex flex-1 overflow-x-auto">
                    <div
                        v-for="day in weekDays"
                        :key="toISODateStr(day)"
                        class="flex min-w-[13.5rem] flex-1 flex-col border-r last:border-r-0"
                        :class="isToday(day) ? 'bg-indigo-50/50 dark:bg-indigo-950/15' : ''"
                    >
                        <!-- Day header -->
                        <div
                            class="flex min-h-14 flex-col items-center border-b px-0.5 pt-1.5 pb-1 text-center"
                            :class="isToday(day) ? 'bg-gradient-to-b from-indigo-100 to-indigo-50 dark:from-indigo-950/40 dark:to-indigo-950/20' : ''"
                        >
                            <p class="text-[10px] font-semibold uppercase tracking-wide"
                                :class="isToday(day) ? 'text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground'">
                                {{ day.toLocaleDateString(undefined, { weekday: 'short' }) }}
                            </p>
                            <p
                                class="mt-0.5 flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                                :class="isToday(day) ? 'bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm' : 'text-foreground'"
                            >
                                {{ day.getDate() }}
                            </p>
                            <!-- All-day chips: teams with a defense_date but no defense_time -->
                            <button
                                v-for="team in teamsOnDay(day).filter(t => !t.defense_time)"
                                :key="'ad-' + team.id"
                                class="mt-0.5 w-full truncate rounded px-1 py-0.5 text-[9px] font-bold leading-tight text-white"
                                :class="teamColor(team.id).dot"
                                @click="openBlock(team)"
                            >
                                {{ team.name }}
                            </button>
                        </div>

                        <!-- Hour grid + events -->
                        <div
                            class="relative"
                            :style="{ height: (GRID_END - GRID_START) * HOUR_HEIGHT + 'px' }"
                        >
                            <!-- Hour lines -->
                            <div
                                v-for="hour in HOURS"
                                :key="'hl-' + hour"
                                class="absolute left-0 right-0 border-b border-border/40"
                                :style="{ top: ((hour - GRID_START) * HOUR_HEIGHT) + 'px', height: HOUR_HEIGHT + 'px' }"
                            />

                            <!-- Defense blocks — overlap-aware side-by-side layout -->
                            <template v-for="team in teamsOnDay(day).filter(t => !!t.defense_time)" :key="'blk-' + team.id">
                                <button
                                    v-if="dayLayoutStyles.get(toISODateStr(day))?.get(team.id)"
                                    :style="dayLayoutStyles.get(toISODateStr(day))?.get(team.id)"
                                    class="flex cursor-pointer flex-col gap-0.5 overflow-hidden rounded-md border px-2 py-1 text-left shadow-sm transition-all hover:brightness-110 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1"
                                    :class="[teamColor(team.id).block, teamColor(team.id).text]"
                                    @click="openBlock(team)"
                                >
                                    <div class="flex items-center justify-between gap-1">
                                        <p class="truncate text-[11px] font-semibold leading-tight">{{ team.subject.title }}</p>
                                        <span
                                            class="h-2 w-2 shrink-0 rounded-full border border-white/40"
                                            :class="STATUS_CFG[reviewStatus(team)].dot"
                                        />
                                    </div>
                                    <p class="truncate text-xs font-semibold leading-tight opacity-90">{{ team.name }}</p>
                                    <p class="mt-auto truncate text-xs font-medium leading-tight opacity-80">
                                        {{ formatDefenseTimeRange(team) }}
                                    </p>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No defenses this week notice -->
            <div v-if="displayMode === 'calendar' && !hasAnyOnWeek && teamsWithDate.length > 0" class="border-t px-4 py-3 text-center text-sm text-muted-foreground">
                No defenses scheduled this week.
                <button class="ml-1 text-primary underline underline-offset-2 hover:no-underline" @click="weekStart = getWeekStart(parseLocalDate(teamsWithDate[0].defense_date!))">
                    Jump to nearest defense
                </button>
            </div>
        </div>

        <!-- Unscheduled teams -->
        <div v-if="unscheduledTeams.length > 0" class="flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <div class="flex h-6 w-6 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-950">
                    <Clock class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                </div>
                <h2 class="text-sm font-semibold text-amber-700 dark:text-amber-400">Awaiting Schedule ({{ unscheduledTeams.length }})</h2>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="team in unscheduledTeams"
                    :key="'us-' + team.id"
                    class="flex items-center justify-between rounded-xl border border-amber-100 bg-amber-50/50 px-4 py-3 dark:border-amber-900/40 dark:bg-amber-950/20"
                >
                    <div class="flex items-center gap-3">
                        <span class="h-3 w-3 rounded-full" :class="teamColor(team.id).dot" />
                        <div>
                            <p class="text-sm font-semibold">{{ team.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ team.subject.title }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                            :class="teamColor(team.id).label"
                        >
                            <component :is="STATUS_CFG[reviewStatus(team)].icon" class="h-3 w-3" />
                            {{ STATUS_CFG[reviewStatus(team)].label }}
                        </span>
                        <Button size="sm" variant="outline" class="h-7 gap-1 text-xs" @click="openBlock(team)">
                            Details
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        </div><!-- /floating content -->
    </div>

    <!-- ── Detail Dialog ──────────────────────────────────────────────────── -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="flex max-h-[90vh] flex-col overflow-hidden sm:max-w-lg">
            <template v-if="selected">
                <!-- Colored header banner -->
                <div
                    class="flex items-start justify-between rounded-t-lg px-5 py-4"
                    :class="[teamColor(selected.id).block, teamColor(selected.id).text]"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <Users class="h-4 w-4 shrink-0 opacity-80" />
                            <p class="truncate text-base font-bold">{{ selected.name }}</p>
                        </div>
                        <Link
                            :href="subjectShow.url(selected.subject.id)"
                            class="mt-0.5 flex items-center gap-1 text-xs opacity-80 hover:opacity-100 hover:underline"
                        >
                            <BookOpen class="h-3 w-3 shrink-0" />
                            {{ selected.subject.title }}
                        </Link>
                    </div>
                    <span class="ml-3 flex shrink-0 items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold">
                        <component :is="STATUS_CFG[reviewStatus(selected)].icon" class="h-3 w-3" />
                        {{ STATUS_CFG[reviewStatus(selected)].label }}
                    </span>
                </div>

                <!-- Scrollable body — soft top/bottom fade so partially-scrolled
                     cards fade out instead of getting hard-clipped at the edges -->
                <div
                    class="flex flex-1 flex-col gap-4 overflow-y-auto px-5 pb-4 pt-5"
                    style="
                        -webkit-mask-image: linear-gradient(to bottom, transparent 0, #000 14px, #000 calc(100% - 14px), transparent 100%);
                        mask-image: linear-gradient(to bottom, transparent 0, #000 14px, #000 calc(100% - 14px), transparent 100%);
                    "
                >

                    <!-- Defense details -->
                    <div class="overflow-hidden rounded-lg border">
                        <div class="flex items-center gap-2 border-b bg-muted/50 px-3.5 py-2">
                            <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                            <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Defense Schedule</span>
                        </div>
                        <div class="grid grid-cols-3 divide-x">
                            <div class="flex flex-col items-center py-3 text-center">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Date</p>
                                <p class="mt-1 text-sm font-bold">
                                    {{ selected.defense_date
                                        ? parseLocalDate(selected.defense_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
                                        : '—' }}
                                </p>
                                <p v-if="selected.defense_date" class="text-[10px] text-muted-foreground">
                                    {{ parseLocalDate(selected.defense_date).getFullYear() }}
                                </p>
                            </div>
                            <div class="flex flex-col items-center py-3 text-center">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Time</p>
                                <template v-if="selected.defense_time">
                                    <p class="mt-1 text-sm font-bold tabular-nums">{{ formatClockTime(selected.defense_time) }}</p>
                                    <template v-if="selected.defense_duration">
                                        <p class="text-[10px] text-muted-foreground leading-none">↓</p>
                                        <p class="text-sm font-bold tabular-nums">{{ formatClockTime(addMinutesToClockTime(selected.defense_time, selected.defense_duration)) }}</p>
                                        <p class="text-[10px] text-muted-foreground">({{ selected.defense_duration }} min)</p>
                                    </template>
                                </template>
                                <p v-else class="mt-1 text-sm font-bold">—</p>
                            </div>
                            <div class="flex flex-col items-center py-3 text-center">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Room</p>
                                <p class="mt-1 px-2 text-sm font-bold">{{ selected.defense_room ?? '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Score deadline -->
                    <div
                        v-if="selected.score_deadline_at"
                        class="flex items-center gap-2 rounded-lg px-3.5 py-2.5"
                        :class="isOverdue(selected.score_deadline_at, !!selected.review?.is_submitted)
                            ? 'border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30'
                            : 'border bg-muted/30'"
                    >
                        <Clock
                            class="h-3.5 w-3.5 shrink-0"
                            :class="isOverdue(selected.score_deadline_at, !!selected.review?.is_submitted) ? 'text-red-600' : 'text-muted-foreground'"
                        />
                        <span class="text-xs text-muted-foreground">Score deadline:</span>
                        <span
                            class="text-xs font-semibold"
                            :class="isOverdue(selected.score_deadline_at, !!selected.review?.is_submitted) ? 'text-red-700 dark:text-red-400' : ''"
                        >
                            {{ formatDateTime(selected.score_deadline_at) }}
                            <span v-if="isOverdue(selected.score_deadline_at, !!selected.review?.is_submitted)" class="font-bold"> (Overdue)</span>
                        </span>
                    </div>

                    <!-- Members -->
                    <div v-if="selected.members.length > 0">
                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Students</p>
                        <div class="flex flex-wrap gap-1.5">
                            <div
                                v-for="m in selected.members"
                                :key="m.id"
                                class="flex items-center gap-1.5 rounded-full border bg-background px-2.5 py-1 text-xs shadow-sm"
                                :title="m.email"
                            >
                                <div class="flex h-4 w-4 items-center justify-center rounded-full bg-blue-100 text-[9px] font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                    {{ m.name.charAt(0).toUpperCase() }}
                                </div>
                                <span class="font-medium">{{ m.name }}</span>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Rubric criteria -->
                    <div v-if="selected.criteria.length > 0">
                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Rubric Criteria</p>
                        <div class="flex max-h-48 flex-col gap-0 overflow-y-auto rounded-lg border divide-y bg-muted/20">
                            <div
                                v-for="(c, i) in selected.criteria"
                                :key="i"
                                class="flex items-center justify-between gap-3 px-3 py-2 text-xs"
                            >
                                <span class="line-clamp-2 min-w-0 text-foreground/80">{{ c.criteria }}</span>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    <span class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">{{ c.weight }}%</span>
                                    <span class="text-[10px] text-muted-foreground">max {{ c.max_score }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submitted scores -->
                    <div v-if="selected.review?.scores_json?.length">
                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-emerald-700 dark:text-emerald-400">Your Submitted Scores</p>
                        <div class="flex max-h-48 flex-col overflow-y-auto rounded-lg border border-emerald-200 divide-y divide-emerald-100 bg-emerald-50/60 dark:border-emerald-900 dark:divide-emerald-900 dark:bg-emerald-950/20">
                            <div
                                v-for="(s, i) in selected.review.scores_json"
                                :key="i"
                                class="flex items-center justify-between gap-3 px-3 py-2 text-xs"
                            >
                                <span class="line-clamp-1 min-w-0 text-foreground/70">{{ s.criteria }}</span>
                                <span class="shrink-0 font-semibold" :class="scoreColor(s.score)">
                                    {{ s.score }}/4 · {{ scoreLabel(s.score) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="flex items-center justify-between border-t px-5 py-3">
                    <DialogClose as-child>
                        <Button variant="ghost" size="sm">Close</Button>
                    </DialogClose>
                    <div class="flex items-center gap-2">
                        <Button v-if="selected.paper" variant="outline" size="sm" class="gap-1.5" as-child>
                            <Link :href="paperShow.url(selected.paper.id)">
                                <FileText class="h-3.5 w-3.5" />
                                View Document
                            </Link>
                        </Button>
                        <Button
                            v-if="selected.paper && !selected.review?.is_submitted"
                            size="sm"
                            class="gap-1.5"
                            as-child
                        >
                            <Link :href="reviewCreate.url(selected.paper.id)">
                                <ClipboardCheck class="h-3.5 w-3.5" />
                                {{ selected.review ? 'Continue Review' : 'Start Review' }}
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </Button>
                        <Button
                            v-else-if="selected.paper && selected.review?.is_submitted && selected.review?.unlocked_at && !selected.review?.locked_at"
                            size="sm"
                            variant="secondary"
                            class="gap-1.5"
                            as-child
                        >
                            <Link :href="reviewCreate.url(selected.paper.id)">
                                <Pencil class="h-3.5 w-3.5" />
                                Edit Review
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </Button>
                        <div
                            v-else-if="selected.review?.is_submitted"
                            class="flex items-center gap-1.5 text-xs font-medium"
                            :class="selected.review.locked_at ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'"
                        >
                            <component :is="selected.review.locked_at ? Lock : CheckCircle2" class="h-3.5 w-3.5" />
                            {{ selected.review.locked_at ? 'Locked — contact instructor to edit' : 'Review submitted' }}
                        </div>
                    </div>
                </div>
            </template>
        </DialogContent>
    </Dialog>

    <!-- ── Owner Team Detail Dialog ──────────────────────────────────────── -->
    <Dialog v-model:open="ownerDialogOpen">
        <DialogContent class="flex max-h-[90vh] flex-col overflow-hidden sm:max-w-lg">
            <template v-if="selectedOwned">
                <!-- Colored header -->
                <div
                    class="flex items-start justify-between rounded-t-lg px-5 py-4"
                    :class="[teamColor(selectedOwned.id).block, teamColor(selectedOwned.id).text]"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <Users class="h-4 w-4 shrink-0 opacity-80" />
                            <p class="truncate text-base font-bold">{{ selectedOwned.name }}</p>
                        </div>
                        <Link
                            :href="subjectShow.url(selectedOwned.subject.id)"
                            class="mt-0.5 flex items-center gap-1 text-xs opacity-80 hover:opacity-100 hover:underline"
                        >
                            <BookOpen class="h-3 w-3 shrink-0" />
                            {{ selectedOwned.subject.title }}
                        </Link>
                    </div>
                    <!-- Review summary pill -->
                    <div class="ml-3 flex shrink-0 flex-col items-end gap-1">
                        <span class="flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold">
                            <CheckCircle2 class="h-3 w-3" />
                            {{ selectedOwned.review_summary.submitted }}/{{ selectedOwned.review_summary.total }} Reviews
                        </span>
                        <span v-if="selectedOwned.review_summary.released" class="flex items-center gap-1 rounded-full bg-white/20 px-2.5 py-0.5 text-[10px] font-semibold">
                            <Send class="h-2.5 w-2.5" />
                            Results Released
                        </span>
                    </div>
                </div>

                <!-- Scrollable body -->
                <div class="flex flex-1 flex-col gap-4 overflow-y-auto px-5 py-4">

                    <!-- Defense schedule -->
                    <div class="overflow-hidden rounded-lg border">
                        <div class="flex items-center gap-2 border-b bg-muted/50 px-3.5 py-2">
                            <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                            <span class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Defense Schedule</span>
                        </div>
                        <div class="grid grid-cols-3 divide-x">
                            <div class="flex flex-col items-center py-3 text-center">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Date</p>
                                <p class="mt-1 text-sm font-bold">
                                    {{ selectedOwned.defense_date
                                        ? parseLocalDate(selectedOwned.defense_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
                                        : '—' }}
                                </p>
                                <p v-if="selectedOwned.defense_date" class="text-[10px] text-muted-foreground">
                                    {{ parseLocalDate(selectedOwned.defense_date).getFullYear() }}
                                </p>
                            </div>
                            <div class="flex flex-col items-center py-3 text-center">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Time</p>
                                <template v-if="selectedOwned.defense_time">
                                    <p class="mt-1 text-sm font-bold tabular-nums">{{ formatClockTime(selectedOwned.defense_time) }}</p>
                                    <template v-if="selectedOwned.defense_duration">
                                        <p class="text-[10px] leading-none text-muted-foreground">↓</p>
                                        <p class="text-sm font-bold tabular-nums">{{ formatClockTime(addMinutesToClockTime(selectedOwned.defense_time, selectedOwned.defense_duration)) }}</p>
                                        <p class="text-[10px] text-muted-foreground">({{ selectedOwned.defense_duration }} min)</p>
                                    </template>
                                </template>
                                <p v-else class="mt-1 text-sm font-bold">—</p>
                            </div>
                            <div class="flex flex-col items-center py-3 text-center">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Room</p>
                                <p class="mt-1 px-2 text-sm font-bold">{{ selectedOwned.defense_room ?? '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Score deadline -->
                    <div
                        v-if="selectedOwned.score_deadline_at"
                        class="flex items-center gap-2 rounded-lg px-3.5 py-2.5"
                        :class="isOverdue(selectedOwned.score_deadline_at, selectedOwned.review_summary.submitted === selectedOwned.review_summary.total && selectedOwned.review_summary.total > 0)
                            ? 'border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30'
                            : 'border bg-muted/30'"
                    >
                        <Clock class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                        <span class="text-xs text-muted-foreground">Score deadline:</span>
                        <span class="text-xs font-semibold">{{ formatDateTime(selectedOwned.score_deadline_at) }}</span>
                    </div>

                    <!-- Document score -->
                    <div v-if="selectedOwned.paper" class="flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <FileText class="h-4 w-4 text-muted-foreground" />
                            <span class="text-sm font-medium">Document Score</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="selectedOwned.paper.final_score !== null" class="text-lg font-bold">
                                {{ selectedOwned.paper.final_score }}<span class="text-sm font-normal text-muted-foreground">/100</span>
                            </span>
                            <span v-else class="text-sm text-muted-foreground">Not scored yet</span>
                            <Star v-if="selectedOwned.paper.final_score !== null && selectedOwned.paper.final_score >= selectedOwned.subject.passing_score" class="h-4 w-4 text-amber-500" />
                        </div>
                    </div>

                    <!-- Review progress -->
                    <div class="flex flex-col gap-2">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Review Progress</p>
                        <div class="flex items-center gap-3">
                            <div class="progress-track h-2 flex-1">
                                <div
                                    class="progress-fill progress-fill--emerald"
                                    :style="{ width: selectedOwned.review_summary.total > 0 ? (selectedOwned.review_summary.submitted / selectedOwned.review_summary.total * 100) + '%' : '0%' }"
                                />
                            </div>
                            <span class="text-xs font-semibold tabular-nums">
                                {{ selectedOwned.review_summary.submitted }}/{{ selectedOwned.review_summary.total }}
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ selectedOwned.review_summary.submitted }} of {{ selectedOwned.review_summary.total }} judges have submitted their review.
                        </p>
                    </div>

                    <Separator />

                    <!-- Members -->
                    <div v-if="selectedOwned.members.length > 0">
                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Team Members</p>
                        <div class="flex flex-wrap gap-1.5">
                            <div
                                v-for="m in selectedOwned.members"
                                :key="m.id"
                                class="flex items-center gap-1.5 rounded-full border bg-background px-2.5 py-1 text-xs shadow-sm"
                                :title="m.email"
                            >
                                <div class="flex h-4 w-4 items-center justify-center rounded-full bg-blue-100 text-[9px] font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                    {{ m.name.charAt(0).toUpperCase() }}
                                </div>
                                <span class="font-medium">{{ m.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t px-5 py-3">
                    <DialogClose as-child>
                        <Button variant="ghost" size="sm">Close</Button>
                    </DialogClose>
                    <Button v-if="selectedOwned.paper" variant="outline" size="sm" class="gap-1.5" as-child>
                        <Link :href="paperShow.url(selectedOwned.paper.id)">
                            <FileText class="h-3.5 w-3.5" />
                            View Document
                        </Link>
                    </Button>
                </div>
            </template>
        </DialogContent>
    </Dialog>


</template>
