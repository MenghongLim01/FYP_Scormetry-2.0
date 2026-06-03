<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpenText,
    ClipboardCheck,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const workflowSteps = [
    {
        title: 'Set up subjects',
        description: 'Create subjects, assign teachers, and invite students or reviewers in minutes.',
        icon: BookOpenText,
        color: 'from-blue-500 to-indigo-600',
        bg: 'bg-blue-50 dark:bg-blue-950/40',
        text: 'text-blue-600 dark:text-blue-400',
        num: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    },
    {
        title: 'Manage teams and papers',
        description: 'Track submissions, team membership, and publication status in one dashboard.',
        icon: Users,
        color: 'from-violet-500 to-purple-600',
        bg: 'bg-violet-50 dark:bg-violet-950/40',
        text: 'text-violet-600 dark:text-violet-400',
        num: 'bg-violet-100 text-violet-700 dark:bg-violet-900 dark:text-violet-300',
    },
    {
        title: 'Review with confidence',
        description: 'Apply rubrics, aggregate results, and keep scoring transparent for every submission.',
        icon: ClipboardCheck,
        color: 'from-emerald-500 to-teal-600',
        bg: 'bg-emerald-50 dark:bg-emerald-950/40',
        text: 'text-emerald-600 dark:text-emerald-400',
        num: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
    },
] as const;

const valuePillars = [
    {
        title: 'Role-aware access',
        description: 'Students, reviewers, and admins each get focused workflows.',
        gradient: 'from-blue-500 to-indigo-500',
        bg: 'bg-blue-50 dark:bg-blue-950/40',
        border: 'border-blue-200 dark:border-blue-800',
    },
    {
        title: 'Rubric-driven outcomes',
        description: 'Consistent scoring with a clear audit trail for each review.',
        gradient: 'from-violet-500 to-fuchsia-500',
        bg: 'bg-violet-50 dark:bg-violet-950/40',
        border: 'border-violet-200 dark:border-violet-800',
    },
    {
        title: 'Secure by design',
        description: 'Built-in approval flow, verification, and account protections.',
        gradient: 'from-emerald-500 to-teal-500',
        bg: 'bg-emerald-50 dark:bg-emerald-950/40',
        border: 'border-emerald-200 dark:border-emerald-800',
    },
] as const;

const stats = [
    { label: 'Subjects', value: '01', color: 'text-blue-600 dark:text-blue-400' },
    { label: 'Papers', value: '02', color: 'text-violet-600 dark:text-violet-400' },
    { label: 'Reviews', value: '03', color: 'text-emerald-600 dark:text-emerald-400' },
] as const;
</script>

<template>
    <Head title="Welcome" />

    <div class="relative min-h-svh overflow-hidden bg-background text-foreground">

        <!-- Background blobs -->
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -top-32 -left-20 h-96 w-96 rounded-full bg-blue-400/15 blur-3xl dark:bg-blue-400/20" />
            <div class="absolute top-1/3 -right-24 h-80 w-80 rounded-full bg-violet-400/15 blur-3xl dark:bg-violet-400/20" />
            <div class="absolute bottom-0 left-1/3 h-72 w-72 rounded-full bg-emerald-400/10 blur-3xl dark:bg-emerald-400/15" />
            <div class="absolute top-1/2 left-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-indigo-300/10 blur-3xl dark:bg-fuchsia-400/10" />
        </div>

        <div class="relative mx-auto flex min-h-svh w-full max-w-6xl flex-col px-6 py-6 lg:px-8 lg:py-8">

            <!-- Header -->
            <header class="flex items-center justify-between gap-4">
                <Link :href="'/'" class="group flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg border bg-card shadow-sm transition-colors group-hover:bg-accent">
                        <AppLogoIcon class="size-6 fill-current text-primary" />
                    </div>
                    <div class="space-y-0.5">
                        <p class="text-sm font-semibold tracking-tight">{{ $page.props.name }}</p>
                        <p class="text-xs text-muted-foreground">Final Year Project Management System</p>
                    </div>
                </Link>

                <nav class="flex items-center gap-2">
                    <Button v-if="$page.props.auth.user" as-child variant="outline" size="sm">
                        <Link :href="dashboard()">Dashboard</Link>
                    </Button>
                    <template v-else>
                        <Button as-child variant="ghost" size="sm">
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button v-if="canRegister" as-child size="sm" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 border-0">
                            <Link :href="register()">Register</Link>
                        </Button>
                    </template>
                </nav>
            </header>

            <!-- Main grid -->
            <main class="grid flex-1 items-center gap-6 py-10 lg:grid-cols-[minmax(0,1fr)_24rem] lg:gap-8 lg:py-12">

                <!-- Left: hero -->
                <section class="space-y-6 motion-safe:animate-in motion-safe:duration-700 motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-4">

                    <!-- Badge pill -->
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:border-blue-800 dark:bg-blue-950/60 dark:text-blue-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse" />
                        Scormetry Workflow Hub
                    </span>

                    <div class="space-y-4">
                        <h1 class="max-w-2xl text-4xl font-bold tracking-tight text-balance sm:text-5xl">
                            Coordinate
                            <span class="bg-gradient-to-r from-blue-600 via-violet-600 to-emerald-500 bg-clip-text text-transparent">subjects, teams,</span>
                            and reviews from one focused workspace.
                        </h1>
                        <p class="max-w-2xl text-base text-muted-foreground sm:text-lg">
                            {{ $page.props.name }} centralizes FYP operations so faculties and students can move from setup to evaluation with less friction and better visibility.
                        </p>
                    </div>

                    <!-- CTA buttons -->
                    <div class="flex flex-wrap items-center gap-3">
                        <Button v-if="$page.props.auth.user" as-child size="lg" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 border-0 shadow-lg shadow-blue-500/25">
                            <Link :href="dashboard()" class="inline-flex items-center gap-2">
                                Continue to dashboard
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </Button>
                        <template v-else>
                            <Button as-child size="lg" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 border-0 shadow-lg shadow-blue-500/25">
                                <Link :href="login()" class="inline-flex items-center gap-2">
                                    Start with login
                                    <ArrowRight class="h-4 w-4" />
                                </Link>
                            </Button>
                            <Button v-if="canRegister" as-child variant="outline" size="lg">
                                <Link :href="register()">Create account</Link>
                            </Button>
                        </template>
                    </div>

                    <!-- Value pillars -->
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div
                            v-for="pillar in valuePillars"
                            :key="pillar.title"
                            :class="['rounded-xl border p-4 shadow-sm backdrop-blur', pillar.bg, pillar.border]"
                        >
                            <div :class="['mb-1.5 h-1 w-8 rounded-full bg-gradient-to-r', pillar.gradient]" />
                            <p class="text-sm font-semibold">{{ pillar.title }}</p>
                            <p class="mt-1 text-xs leading-relaxed text-muted-foreground">{{ pillar.description }}</p>
                        </div>
                    </div>
                </section>

                <!-- Right: workflow + stats -->
                <section class="grid gap-4 motion-safe:animate-in motion-safe:delay-150 motion-safe:duration-700 motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-6">

                    <!-- Workflow card -->
                    <div class="rounded-2xl border border-border/60 bg-card/95 p-5 shadow-md backdrop-blur">
                        <div class="mb-4 flex items-center gap-2">
                            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow">
                                <ShieldCheck class="h-4 w-4" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold">How the flow works</p>
                                <p class="text-xs text-muted-foreground">A practical cycle from class setup to final grading.</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(step, index) in workflowSteps"
                                :key="step.title"
                                :class="['flex items-start gap-3 rounded-xl p-3', step.bg]"
                            >
                                <div :class="['mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold', step.num]">
                                    {{ index + 1 }}
                                </div>
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-1.5">
                                        <component :is="step.icon" :class="['h-3.5 w-3.5', step.text]" />
                                        <p class="text-sm font-medium">{{ step.title }}</p>
                                    </div>
                                    <p class="text-xs leading-relaxed text-muted-foreground">{{ step.description }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats card -->
                    <div class="rounded-2xl border border-border/60 bg-gradient-to-br from-muted/60 to-muted/20 p-5 shadow-sm backdrop-blur">
                        <div class="grid grid-cols-3 gap-3">
                            <div
                                v-for="stat in stats"
                                :key="stat.label"
                                class="flex flex-col items-center justify-center rounded-xl border border-border/60 bg-background/80 py-4 shadow-sm"
                            >
                                <p class="text-xs text-muted-foreground">{{ stat.label }}</p>
                                <p :class="['mt-1 text-2xl font-bold tracking-tight', stat.color]">{{ stat.value }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
