<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BookOpen, ShieldCheck, FileText, Sparkles, Lock, BarChart3 } from 'lucide-vue-next';

defineOptions({
    layout: { breadcrumbs: [{ title: 'About Scormetry', href: '/settings/about' }] },
});

const features = [
    { icon: Lock, title: 'Secure document handling', body: 'Student documents and manuscripts live in controlled-access team rooms — judges only see the teams assigned to them.' },
    { icon: Sparkles, title: 'AI-assisted Dynamic Rubric', body: 'Upload the Defense Rubric file; AI drafts the criteria, the instructor verifies and locks it before scoring begins.' },
    { icon: BarChart3, title: 'Criterion-based scoring', body: 'Automatic weighted calculation across multiple judges removes the spreadsheet errors of manual scoring.' },
    { icon: ShieldCheck, title: 'Role-based access', body: 'Instructors, judges/reviewers, and students each see exactly what their role and assignments allow.' },
];
</script>

<template>
    <Head title="About Scormetry" />
    <h1 class="sr-only">About Scormetry</h1>

    <div class="space-y-8">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <BookOpen class="h-6 w-6" />
            </div>
            <div>
                <h2 class="text-xl font-semibold text-foreground">Scormetry 2.0</h2>
                <p class="mt-1 text-sm text-muted-foreground">AI Rubric Generation and Secure Document Management for Academic Evaluations.</p>
            </div>
        </div>

        <div class="border-t border-border" />

        <p class="text-sm leading-relaxed text-muted-foreground">
            Scormetry 2.0 is a centralized, web-based platform for running rubric-based academic evaluations —
            Final Year Project (FYP) defenses, thesis and capstone reviews, and any panel scoring against a fixed rubric.
            It replaces scattered documents and manual spreadsheets with one secure place to manage teams, schedule
            defenses, submit documents, score against a locked rubric, and release results.
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            <div v-for="f in features" :key="f.title" class="rounded-xl border bg-card p-4 shadow-sm">
                <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <component :is="f.icon" class="h-4.5 w-4.5" />
                </div>
                <p class="text-sm font-semibold text-foreground">{{ f.title }}</p>
                <p class="mt-1 text-xs leading-relaxed text-muted-foreground">{{ f.body }}</p>
            </div>
        </div>

        <div class="rounded-xl border bg-muted/30 p-4">
            <div class="flex items-center gap-2 text-sm font-semibold text-foreground">
                <FileText class="h-4 w-4 text-muted-foreground" />
                Version &amp; credits
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-y-1.5 text-xs">
                <dt class="text-muted-foreground">Version</dt><dd class="font-medium">2.0</dd>
                <dt class="text-muted-foreground">Institution</dt><dd class="font-medium">Paragon International University</dd>
                <dt class="text-muted-foreground">Faculty</dt><dd class="font-medium">Information &amp; Computer Technology</dd>
            </dl>
        </div>
    </div>
</template>
