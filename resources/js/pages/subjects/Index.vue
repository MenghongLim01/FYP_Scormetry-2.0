<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Plus, Users, KeyRound, FileText, Search, ShieldCheck, Pin, Archive, ArchiveRestore, GripVertical, ArrowUpDown, Check, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import TipBanner from '@/components/TipBanner.vue';
import { useAuth } from '@/composables/useAuth';
import { create as subjectCreate, show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { computed, ref, watch } from 'vue';

type SubjectRow = {
    id: number;
    title: string;
    description: string | null;
    passing_score: number;
    teacher_id: number;
    join_code: string | null;
    require_approval: boolean;
    teacher?: { id: number; name: string };
    students_count?: number;
    papers_count?: number;
    reviewers_count?: number;
    rubric?: { id: number; status: string } | null;
    is_pinned?: boolean;
    is_archived?: boolean;
    created_at: string;
};

const props = defineProps<{
    subjects: SubjectRow[];
    showingArchived?: boolean;
    archivedCount?: number;
}>();

const { user, isTeacherOrAdmin } = useAuth();
const search = ref('');

function togglePin(subject: SubjectRow) {
    router.post(`/subjects/${subject.id}/pin`, {}, { preserveScroll: true });
}

function canManage(subject: SubjectRow): boolean {
    return user.value?.id === subject.teacher_id || (isTeacherOrAdmin.value && user.value?.role === 'admin');
}

function archiveSubject(subject: SubjectRow) {
    router.post(`/subjects/${subject.id}/archive`, {}, { preserveScroll: true });
}

function restoreSubject(subject: SubjectRow) {
    router.post(`/subjects/${subject.id}/unarchive`, {}, { preserveScroll: true });
}

function toggleArchivedView() {
    router.get('/subjects', props.showingArchived ? {} : { archived: 1 }, { preserveScroll: true, preserveState: false });
}

// ── Drag-to-reorder (per-user custom order) ──────────────────────────────────
const arrangeMode = ref(false);
const localOrder = ref<SubjectRow[]>([...props.subjects]);
const dragIndex = ref<number | null>(null);

watch(() => props.subjects, (s) => { localOrder.value = [...s]; });

function startArrange() {
    localOrder.value = [...props.subjects];
    arrangeMode.value = true;
}

function onDragStart(index: number) {
    dragIndex.value = index;
}

function onDragOver(index: number) {
    if (dragIndex.value === null || dragIndex.value === index) return;
    const list = localOrder.value;
    const [moved] = list.splice(dragIndex.value, 1);
    list.splice(index, 0, moved);
    dragIndex.value = index;
}

function onDragEnd() {
    dragIndex.value = null;
}

function saveArrange() {
    router.post('/subjects/reorder', { order: localOrder.value.map((s) => s.id) }, {
        preserveScroll: true,
        onFinish: () => { arrangeMode.value = false; },
    });
}

function cancelArrange() {
    localOrder.value = [...props.subjects];
    arrangeMode.value = false;
}

const filteredSubjects = computed(() => {
    if (arrangeMode.value) return localOrder.value;
    if (!search.value.trim()) return props.subjects;
    const q = search.value.toLowerCase();
    return props.subjects.filter(
        (s) => s.title.toLowerCase().includes(q) || s.teacher?.name?.toLowerCase().includes(q),
    );
});

function roleLabel(subject: (typeof props.subjects)[number]): string | null {
    if (user.value?.id === subject.teacher_id) return 'Owner';
    return null;
}

// Per-subject color — derived deterministically from the subject's ID so that
// every subject keeps its OWN fixed color. Reordering/arranging never changes a
// subject's color (it's keyed on id, not list position). The golden-angle hue
// step (137.5°) spreads adjacent subjects far apart so colors look very distinct.
function subjectHue(id: number): number {
    return Math.round((id * 137.508) % 360);
}

function bannerStyle(id: number): Record<string, string> {
    const h = subjectHue(id);
    return {
        backgroundImage: `linear-gradient(135deg, hsl(${h} 62% 46%), hsl(${(h + 24) % 360} 64% 36%))`,
    };
}
</script>

<template>
    <Head title="Subjects" />

    <div class="flex flex-col">

        <!-- Page header panel -->
        <div class="bg-gradient-to-br from-primary to-[hsl(228_60%_35%)] px-6 pt-6 pb-20 text-white shadow-md">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Subjects</h1>
                    <p class="text-sm text-white/75">
                        {{ isTeacherOrAdmin ? 'Manage your subjects and enrolled students' : 'Your enrolled subjects' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="isTeacherOrAdmin" as-child class="gap-1.5 bg-white/20 text-white hover:bg-white/30 border-0 shadow-sm backdrop-blur-sm">
                        <Link :href="subjectCreate.url()">
                            <Plus class="h-4 w-4" />
                            New Subject
                        </Link>
                    </Button>
                    <Button v-else variant="outline" as-child class="gap-1.5 bg-white/20 text-white hover:bg-white/30 border-white/30">
                        <Link href="/subjects/join">
                            <KeyRound class="h-4 w-4" />
                            Join by Code
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Floating content panel -->
        <div class="relative z-10 -mt-12 flex flex-col gap-5 px-6 pb-6">

        <!-- First-run guidance — dismissible, role-aware -->
        <TipBanner
            v-if="isTeacherOrAdmin && subjects.length > 0"
            storage-key="subjects-index-teacher"
            title="Everything for a class lives inside its subject"
            text="Open a subject to manage teams, set defense schedules, approve reviewers, and release scores. Share its join code so students and reviewers can enrol themselves."
        />
        <TipBanner
            v-else-if="!isTeacherOrAdmin && subjects.length > 0"
            storage-key="subjects-index-student"
            accent="emerald"
            title="Tap a subject to see your team and schedule"
            text="Your team, defense date, document uploads, and results all live inside each subject. Have a code from your instructor? Use Join to enrol in a new one."
        />

        <!-- Toolbar: search + arrange + archived toggle -->
        <div v-if="subjects.length > 0 || showingArchived" class="flex flex-wrap items-center gap-2">
            <div v-if="!arrangeMode" class="relative max-w-sm flex-1 sm:flex-none sm:w-72">
                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" type="text" placeholder="Search subjects..." class="pl-9 bg-card shadow-sm" />
            </div>

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <!-- Arrange mode controls -->
                <template v-if="arrangeMode">
                    <span class="text-xs font-medium text-muted-foreground">Drag cards to reorder</span>
                    <Button size="sm" variant="ghost" class="gap-1.5" @click="cancelArrange">
                        <X class="h-3.5 w-3.5" /> Cancel
                    </Button>
                    <Button size="sm" class="gap-1.5 bg-[#24327a] text-white hover:bg-[#1b255c]" @click="saveArrange">
                        <Check class="h-3.5 w-3.5" /> Save order
                    </Button>
                </template>
                <template v-else>
                    <Button
                        v-if="!showingArchived && filteredSubjects.length > 1"
                        size="sm"
                        variant="outline"
                        class="gap-1.5"
                        @click="startArrange"
                    >
                        <ArrowUpDown class="h-3.5 w-3.5" /> Arrange
                    </Button>
                    <Button
                        v-if="(archivedCount ?? 0) > 0 || showingArchived"
                        size="sm"
                        :variant="showingArchived ? 'default' : 'outline'"
                        class="gap-1.5"
                        @click="toggleArchivedView"
                    >
                        <Archive class="h-3.5 w-3.5" />
                        {{ showingArchived ? 'Back to active' : `Archived (${archivedCount})` }}
                    </Button>
                </template>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="subjects.length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed bg-card py-20 text-center shadow-md">
            <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-950/40">
                <BookOpen class="h-8 w-8 text-blue-500" />
            </div>
            <p class="font-semibold text-muted-foreground">No subjects yet</p>
            <p v-if="isTeacherOrAdmin" class="mt-1 text-sm text-muted-foreground/70">Create your first subject to get started.</p>
            <p v-else class="mt-1 text-sm text-muted-foreground/70">Join a subject using a code from your teacher.</p>
        </div>

        <!-- No search results -->
        <div v-else-if="filteredSubjects.length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed bg-card py-14 text-center shadow-md">
            <Search class="mb-3 h-8 w-8 text-muted-foreground/40" />
            <p class="font-medium text-muted-foreground">No subjects match "{{ search }}"</p>
        </div>

        <!-- Subject grid -->
        <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <component
                :is="arrangeMode ? 'div' : Link"
                v-for="(subject, idx) in filteredSubjects"
                :key="subject.id"
                :href="arrangeMode ? undefined : subjectShow.url(subject.id)"
                class="group block"
                :class="arrangeMode ? 'cursor-move select-none' : ''"
                :draggable="arrangeMode"
                @dragstart="arrangeMode ? onDragStart(idx) : undefined"
                @dragover.prevent="arrangeMode ? onDragOver(idx) : undefined"
                @dragend="onDragEnd"
            >
                <div
                    class="flex h-full flex-col overflow-hidden rounded-2xl border bg-card shadow-md transition-all duration-200"
                    :class="[
                        arrangeMode
                            ? (dragIndex === idx ? 'opacity-60 ring-2 ring-[#24327a]' : 'ring-1 ring-dashed ring-[#24327a]/30')
                            : 'hover:-translate-y-0.5 hover:shadow-lg',
                        subject.is_pinned && !arrangeMode ? 'ring-2 ring-amber-400/70 ring-offset-1 ring-offset-background' : '',
                        subject.is_archived ? 'opacity-75' : '',
                    ]"
                >

                    <!-- Colored banner -->
                    <div
                        class="relative flex items-end gap-3 px-4 pb-3 pt-4"
                        :class="subject.is_archived ? 'bg-gradient-to-br from-slate-500 to-slate-600' : ''"
                        :style="subject.is_archived ? undefined : bannerStyle(subject.id)"
                    >
                        <!-- Drag handle (arrange mode) -->
                        <div v-if="arrangeMode" class="absolute left-2.5 top-2.5 flex h-7 w-7 items-center justify-center rounded-lg bg-white/25 text-white">
                            <GripVertical class="h-4 w-4" />
                        </div>
                        <!-- Subject icon -->
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-sm"
                            :class="[arrangeMode ? 'ml-8' : '', 'bg-white/20 text-white']"
                        >
                            <Archive v-if="subject.is_archived" class="h-5 w-5" />
                            <BookOpen v-else class="h-5 w-5" />
                        </div>

                        <!-- Action buttons (pin + archive) — hidden in arrange mode -->
                        <div v-if="!arrangeMode" class="absolute right-2.5 top-2.5 flex items-center gap-1">
                            <button
                                v-if="canManage(subject) && !subject.is_archived"
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white/80 transition-colors hover:bg-white/30 hover:text-white"
                                title="Archive subject"
                                aria-label="Archive subject"
                                @click.prevent.stop="archiveSubject(subject)"
                            >
                                <Archive class="h-4 w-4" />
                            </button>
                            <button
                                v-if="canManage(subject) && subject.is_archived"
                                type="button"
                                class="flex h-8 items-center gap-1 rounded-full bg-white/25 px-2.5 text-xs font-semibold text-white transition-colors hover:bg-white/40"
                                title="Restore subject"
                                @click.prevent.stop="restoreSubject(subject)"
                            >
                                <ArchiveRestore class="h-3.5 w-3.5" /> Restore
                            </button>
                            <button
                                v-if="!subject.is_archived"
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-full transition-colors"
                                :class="subject.is_pinned
                                    ? 'bg-amber-400 text-amber-950 shadow-sm hover:bg-amber-300'
                                    : 'bg-white/20 text-white/80 hover:bg-white/30 hover:text-white'"
                                :title="subject.is_pinned ? 'Unpin subject' : 'Pin subject to top'"
                                :aria-label="subject.is_pinned ? 'Unpin subject' : 'Pin subject'"
                                @click.prevent.stop="togglePin(subject)"
                            >
                                <Pin class="h-4 w-4" :class="subject.is_pinned ? 'fill-current' : ''" />
                            </button>
                        </div>
                        <!-- Badges row -->
                        <div class="flex flex-wrap items-center gap-1.5 pb-0.5 pr-9">
                            <span v-if="roleLabel(subject)" class="rounded-full bg-white/25 px-2 py-0.5 text-[11px] font-semibold text-white backdrop-blur-sm">
                                {{ roleLabel(subject) }}
                            </span>
                            <span class="rounded-full bg-white/25 px-2 py-0.5 text-[11px] font-semibold text-white backdrop-blur-sm">
                                Pass: {{ subject.passing_score }}%
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold backdrop-blur-sm"
                                :class="subject.require_approval
                                    ? 'bg-amber-400/80 text-amber-950'
                                    : 'bg-white/25 text-white'"
                            >
                                {{ subject.require_approval ? 'Approval Required' : 'Auto Join' }}
                            </span>
                        </div>
                    </div>

                    <!-- Card body -->
                    <div class="flex flex-1 flex-col gap-1.5 px-4 py-3">
                        <p class="font-bold leading-snug text-foreground transition-colors group-hover:text-primary">
                            {{ subject.title }}
                        </p>
                        <p class="line-clamp-2 text-xs text-muted-foreground">
                            {{ subject.description ?? 'No description provided.' }}
                        </p>
                    </div>

                    <!-- Card footer -->
                    <div class="flex items-center justify-between border-t bg-muted/30 px-4 py-2.5">
                        <!-- Teacher -->
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Users class="h-3.5 w-3.5" />
                            <span>{{ subject.teacher?.name ?? 'You' }}</span>
                        </div>
                        <!-- Stats -->
                        <div class="flex items-center gap-3 text-xs text-muted-foreground">
                            <span v-if="subject.students_count != null" class="flex items-center gap-1" :title="`${subject.students_count} students`">
                                <Users class="h-3.5 w-3.5 text-blue-500" />
                                <span class="font-medium text-foreground">{{ subject.students_count }}</span>
                            </span>
                            <span v-if="(subject.reviewers_count ?? 0) > 0" class="flex items-center gap-1" :title="`${subject.reviewers_count} reviewers`">
                                <ShieldCheck class="h-3.5 w-3.5 text-violet-500" />
                                <span class="font-medium text-foreground">{{ subject.reviewers_count }}</span>
                            </span>
                            <span v-if="subject.papers_count != null" class="flex items-center gap-1" :title="`${subject.papers_count} documents`">
                                <FileText class="h-3.5 w-3.5 text-emerald-500" />
                                <span class="font-medium text-foreground">{{ subject.papers_count }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </component>
        </div>

        </div><!-- /floating content panel -->
    </div>
</template>
