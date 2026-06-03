<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, FileText, Clock, CheckCircle, ChevronRight, GraduationCap, Users, Plus, Star, TrendingUp, ShieldCheck } from 'lucide-vue-next';
import { useAuth } from '@/composables/useAuth';
import { index as papersIndex } from '@/actions/App/Http/Controllers/PaperController';
import { index as subjectsIndex } from '@/actions/App/Http/Controllers/SubjectController';
import { form as reviewerJoinForm } from '@/routes/subjects/join-as-reviewer';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

defineProps<{
    stats: Array<{ label: string; value: number; color: 'blue' | 'indigo' | 'amber' | 'green' }>;
    recentPapers: Array<{
        id: number;
        final_score: number | null;
        visibility_status: string;
        created_at: string;
        team?: { id: number; name: string };
        subject: { id: number; title: string };
    }>;
}>();

const { user, effectiveRole, isStudent, isTeacher, isAdmin } = useAuth();

const statConfig = {
    blue:   { icon: BookOpen,     border: 'border-t-indigo-600',  bg: 'bg-indigo-50 dark:bg-indigo-950/40',  text: 'text-[#212e70] dark:text-white',  label: 'text-indigo-700 dark:text-white/70' },
    indigo: { icon: FileText,     border: 'border-t-indigo-600',  bg: 'bg-indigo-50 dark:bg-indigo-950/40',  text: 'text-[#212e70] dark:text-white',  label: 'text-indigo-700 dark:text-white/70' },
    amber:  { icon: Clock,        border: 'border-t-amber-400',   bg: 'bg-amber-50 dark:bg-amber-950/40',    text: 'text-[#212e70] dark:text-white',  label: 'text-amber-700 dark:text-white/70' },
    green:  { icon: CheckCircle,  border: 'border-t-teal-500',    bg: 'bg-teal-50 dark:bg-teal-950/40',      text: 'text-[#212e70] dark:text-white',  label: 'text-teal-700 dark:text-white/70' },
};

const statusCfg: Record<string, { label: string; class: string }> = {
    published: { label: 'Review Completed', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' },
    submitted:  { label: 'Submitted',        class: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' },
    draft:      { label: 'Draft',            class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' },
};

function getStatus(status: string) {
    return statusCfg[status] ?? { label: status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()), class: 'bg-gray-100 text-gray-700' };
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
}

const roleGradient = {
    teacher: 'from-indigo-600 to-indigo-700',
    student: 'from-teal-500 to-teal-600',
    admin:   'from-indigo-700 to-indigo-800',
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col">

        <!-- ── Welcome banner ─────────────────────────────────────────────── -->
        <div
            class="relative overflow-hidden bg-gradient-to-br px-6 pt-6 pb-20 text-white shadow-md"
            :class="roleGradient[effectiveRole as keyof typeof roleGradient] ?? 'from-blue-600 to-indigo-600'"
        >
            <!-- Decorative circles -->
            <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/10" />
            <div class="pointer-events-none absolute -bottom-10 right-20 h-28 w-28 rounded-full bg-white/10" />

            <div class="relative flex items-center gap-4">
                <!-- Avatar with initial -->
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 text-2xl font-bold shadow-inner ring-2 ring-white/30">
                    {{ user.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Welcome back, {{ user.name }}</h1>
                    <p class="mt-0.5 text-sm capitalize text-white/75">{{ effectiveRole }} Dashboard</p>
                </div>
                <div class="ml-auto hidden items-center gap-1.5 rounded-full bg-white/20 px-3 py-1.5 text-xs font-semibold capitalize text-white/90 sm:flex">
                    <TrendingUp class="h-3.5 w-3.5" />
                    {{ effectiveRole }}
                </div>
            </div>
        </div>

        <!-- ── Stat cards ──────────────────────────────────────────────────── -->
        <div class="relative z-10 -mt-12 grid grid-cols-2 gap-4 px-6 lg:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="flex flex-col gap-3 overflow-hidden rounded-2xl border border-t-4 bg-card p-5 shadow-md transition-shadow hover:shadow-lg"
                :class="statConfig[stat.color].border"
            >
                <div class="flex items-start justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ stat.label }}</p>
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="statConfig[stat.color].bg">
                        <component :is="statConfig[stat.color].icon" class="h-4.5 w-4.5" :class="statConfig[stat.color].text" />
                    </div>
                </div>
                <p class="text-4xl font-extrabold tracking-tight" :class="statConfig[stat.color].text">
                    {{ stat.value }}
                </p>
            </div>
        </div>

        <!-- ── Quick actions ───────────────────────────────────────────────── -->
        <div class="mt-2 px-6">
            <div class="rounded-2xl border border-border/40 bg-muted/40 p-5">
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Quick Actions</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link :href="subjectsIndex.url()"
                    class="group flex items-center justify-between rounded-xl bg-gradient-to-br from-teal-500 to-teal-600 px-5 py-4 text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/20 ring-1 ring-white/20">
                            <BookOpen class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">{{ isStudent ? 'My Subjects' : 'Manage Subjects' }}</p>
                            <p class="text-xs text-white/75">View all subjects</p>
                        </div>
                    </div>
                    <ChevronRight class="h-4 w-4 text-white/60 transition group-hover:translate-x-0.5 group-hover:text-white" />
                </Link>

                <Link :href="papersIndex.url()"
                    class="group flex items-center justify-between rounded-xl bg-gradient-to-br from-amber-400 to-amber-500 px-5 py-4 text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/20 ring-1 ring-white/20">
                            <FileText class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">{{ isStudent ? 'My Papers' : 'All Papers' }}</p>
                            <p class="text-xs text-white/75">View and manage papers</p>
                        </div>
                    </div>
                    <ChevronRight class="h-4 w-4 text-white/60 transition group-hover:translate-x-0.5 group-hover:text-white" />
                </Link>

                <Link v-if="isStudent" href="/subjects/join"
                    class="group flex items-center justify-between rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-700 px-5 py-4 text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/20 ring-1 ring-white/20">
                            <Plus class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">Join a Subject</p>
                            <p class="text-xs text-white/75">Enter a join code</p>
                        </div>
                    </div>
                    <ChevronRight class="h-4 w-4 text-white/60 transition group-hover:translate-x-0.5 group-hover:text-white" />
                </Link>

                <Link v-if="isTeacher" :href="reviewerJoinForm.url()"
                    class="group flex items-center justify-between rounded-xl bg-gradient-to-br from-[#212e70] to-indigo-800 px-5 py-4 text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/20 ring-1 ring-white/20">
                            <ShieldCheck class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">Join as Reviewer</p>
                            <p class="text-xs text-white/75">Use reviewer code and role</p>
                        </div>
                    </div>
                    <ChevronRight class="h-4 w-4 text-white/60 transition group-hover:translate-x-0.5 group-hover:text-white" />
                </Link>

                <Link v-if="isAdmin" href="/admin/users"
                    class="group flex items-center justify-between rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-700 px-5 py-4 text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/20 ring-1 ring-white/20">
                            <Users class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">User Management</p>
                            <p class="text-xs text-white/75">Approve users &amp; manage roles</p>
                        </div>
                    </div>
                    <ChevronRight class="h-4 w-4 text-white/60 transition group-hover:translate-x-0.5 group-hover:text-white" />
                </Link>
            </div>
            </div>
        </div>

        <!-- ── Recent papers ───────────────────────────────────────────────── -->
        <div class="px-6 pb-6">
        <Card class="overflow-hidden">
            <CardHeader class="border-b bg-muted/30 px-6 py-4">
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2 text-base font-semibold">
                        <FileText class="h-4 w-4 text-primary" />
                        Recent Papers
                    </CardTitle>
                    <Link :href="papersIndex.url()" class="flex items-center gap-1 text-sm text-primary hover:underline">
                        View all <ChevronRight class="h-3.5 w-3.5" />
                    </Link>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="recentPapers.length === 0" class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                        <FileText class="h-6 w-6 text-muted-foreground/50" />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">No papers yet</p>
                        <p class="text-xs text-muted-foreground/70">Papers will appear here once submitted.</p>
                    </div>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/20 text-left text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                <th class="px-6 py-3">Subject</th>
                                <th class="px-6 py-3">Team</th>
                                <th class="px-6 py-3">Score</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="paper in recentPapers" :key="paper.id" class="transition-colors hover:bg-muted/20">
                                <td class="px-6 py-3 font-medium">{{ paper.subject.title }}</td>
                                <td class="px-6 py-3 text-muted-foreground">{{ paper.team?.name ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span v-if="paper.final_score !== null" class="inline-flex items-center gap-1 font-semibold text-amber-600 dark:text-amber-400">
                                        <Star class="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                                        {{ paper.final_score }} / 100
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="getStatus(paper.visibility_status).class">
                                        {{ getStatus(paper.visibility_status).label }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-muted-foreground">{{ formatDate(paper.created_at) }}</td>
                                <td class="px-6 py-3">
                                    <Link :href="`/papers/${paper.id}`" class="font-medium text-primary hover:underline">View</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
        </div>
    </div>
</template>
