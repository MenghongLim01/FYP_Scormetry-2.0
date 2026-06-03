<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, Plus, Users, KeyRound, FileText, Search, ShieldCheck } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useAuth } from '@/composables/useAuth';
import { create as subjectCreate, show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { computed, ref } from 'vue';

const props = defineProps<{
    subjects: Array<{
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
        created_at: string;
    }>;
}>();

const { user, isTeacherOrAdmin } = useAuth();
const search = ref('');

const filteredSubjects = computed(() => {
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

// Per-subject rotating color palette
// Professional palette — cool/blue tones only, no warm rainbow
const PALETTE = [
    {
        banner: 'bg-gradient-to-br from-blue-600 to-blue-700',
        icon:   'bg-white/20 text-white',
        border: 'border-t-blue-600',
        dot:    'bg-blue-600',
    },
    {
        banner: 'bg-gradient-to-br from-indigo-600 to-indigo-700',
        icon:   'bg-white/20 text-white',
        border: 'border-t-indigo-600',
        dot:    'bg-indigo-600',
    },
    {
        banner: 'bg-gradient-to-br from-violet-600 to-violet-700',
        icon:   'bg-white/20 text-white',
        border: 'border-t-violet-600',
        dot:    'bg-violet-600',
    },
    {
        banner: 'bg-gradient-to-br from-teal-600 to-teal-700',
        icon:   'bg-white/20 text-white',
        border: 'border-t-teal-600',
        dot:    'bg-teal-600',
    },
    {
        banner: 'bg-gradient-to-br from-cyan-600 to-sky-700',
        icon:   'bg-white/20 text-white',
        border: 'border-t-cyan-600',
        dot:    'bg-cyan-600',
    },
    {
        banner: 'bg-gradient-to-br from-slate-600 to-slate-700',
        icon:   'bg-white/20 text-white',
        border: 'border-t-slate-600',
        dot:    'bg-slate-600',
    },
];

function subjectColor(idx: number) {
    return PALETTE[idx % PALETTE.length];
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

        <!-- Search bar -->
        <div v-if="subjects.length > 0" class="relative max-w-sm">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="search" type="text" placeholder="Search subjects..." class="pl-9 bg-card shadow-sm" />
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
            <Link
                v-for="(subject, idx) in filteredSubjects"
                :key="subject.id"
                :href="subjectShow.url(subject.id)"
                class="group block"
            >
                <div class="flex h-full flex-col overflow-hidden rounded-2xl border bg-card shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg">

                    <!-- Colored banner -->
                    <div class="relative flex items-end gap-3 px-4 pb-3 pt-4" :class="subjectColor(idx).banner">
                        <!-- Subject icon -->
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-sm" :class="subjectColor(idx).icon">
                            <BookOpen class="h-5 w-5" />
                        </div>
                        <!-- Badges row -->
                        <div class="flex flex-wrap items-center gap-1.5 pb-0.5">
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
                                {{ subject.require_approval ? 'Approval Required' : 'Open Enroll' }}
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
                            <span v-if="subject.papers_count != null" class="flex items-center gap-1" :title="`${subject.papers_count} papers`">
                                <FileText class="h-3.5 w-3.5 text-emerald-500" />
                                <span class="font-medium text-foreground">{{ subject.papers_count }}</span>
                            </span>
                        </div>
                    </div>
                </div>
            </Link>
        </div>

        </div><!-- /floating content panel -->
    </div>
</template>
