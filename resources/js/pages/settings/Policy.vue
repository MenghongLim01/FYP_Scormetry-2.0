<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Privacy Policy', href: '/settings/policy' }] },
});

const sections = [
    { h: '1. Information We Collect', p: 'We collect the information needed to run academic evaluations: your name and email (for your account), the subjects, teams, and roles you belong to, documents you upload, and the scores and feedback recorded during evaluations.' },
    { h: '2. How We Use It', p: 'Your information is used solely to operate the platform — authenticating you, controlling access to documents, running rubric-based scoring, sending notifications (e.g., document submitted, results released), and producing evaluation records for your institution.' },
    { h: '3. Document & Score Confidentiality', p: 'Student documents and manuscripts are stored privately and are visible only to the student’s team, the subject instructor, and judges assigned to that team. Reviews are hidden from students until results are released. Score overrides and review unlocks are logged for audit.' },
    { h: '4. Notifications', p: 'We send notifications by email and in-app to keep you informed of relevant events. These are tied to your account activity and role; we do not send marketing email.' },
    { h: '5. Data Retention', p: 'Evaluation records are retained for academic administration in line with your institution’s policy. You may request information about your data through your instructor or administrator.' },
    { h: '6. Security', p: 'Access is role-based and assignment-based. An optional email login code (one-time passcode) is available. We take reasonable measures to protect data, though no system can guarantee absolute security.' },
    { h: '7. Third Parties', p: 'AI-assisted rubric extraction may process the Defense Rubric file you upload. We do not sell personal data. Email delivery and hosting are handled by standard service providers under your institution’s arrangements.' },
];
</script>

<template>
    <Head title="Privacy Policy" />
    <h1 class="sr-only">Privacy Policy</h1>

    <div class="space-y-8">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <ShieldCheck class="h-6 w-6" />
            </div>
            <div>
                <h2 class="text-xl font-semibold text-foreground">Privacy Policy</h2>
                <p class="mt-1 text-sm text-muted-foreground">How Scormetry 2.0 collects, uses, and protects your information.</p>
            </div>
        </div>

        <div class="border-t border-border" />

        <div class="space-y-5">
            <section v-for="s in sections" :key="s.h">
                <h3 class="text-sm font-semibold text-foreground">{{ s.h }}</h3>
                <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ s.p }}</p>
            </section>
        </div>

        <p class="text-xs text-muted-foreground">Last updated: {{ new Date().getFullYear() }} · Paragon International University.</p>
    </div>
</template>
