<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

// Public, no-login privacy policy (used for Google OAuth verification).
const sections = [
    { h: '1. Information We Collect', p: 'We collect the information needed to run academic evaluations: your name and email (for your account), the subjects, teams, and roles you belong to, documents you upload, and the scores and feedback recorded during evaluations.' },
    { h: '2. How We Use It', p: 'Your information is used solely to operate the platform — authenticating you, controlling access to documents, running rubric-based scoring, sending notifications (e.g., document submitted, results released), and producing evaluation records for your institution.' },
    { h: '3. Google Account & Calendar Access', p: 'When you sign in with Google we receive your name, email address, and profile to create or match your Scormetry account. If you choose to connect Google Calendar, we request the calendar.events scope only to create, update, and remove the calendar events for defense sessions you are assigned to. We never read your existing calendar events and never share this data with third parties. You can disconnect Google Calendar at any time, which stops all syncing.' },
    { h: '4. Document & Score Confidentiality', p: 'Student documents and manuscripts are stored privately and are visible only to the student’s team, the subject instructor, and judges assigned to that team. Reviews are hidden from students until results are released. Score overrides and review unlocks are logged for audit.' },
    { h: '5. Notifications', p: 'We send notifications by email and in-app to keep you informed of relevant events. These are tied to your account activity and role; we do not send marketing email.' },
    { h: '6. Data Retention', p: 'Evaluation records are retained for academic administration in line with your institution’s policy. You may request information about your data through your instructor or administrator.' },
    { h: '7. Security', p: 'Access is role-based and assignment-based. An optional email login code (one-time passcode) is available. We take reasonable measures to protect data, though no system can guarantee absolute security.' },
    { h: '8. Third Parties', p: 'AI-assisted rubric extraction may process the Defense Rubric file you upload. Email delivery and hosting are handled by standard service providers. We do not sell personal data.' },
    { h: '9. Contact', p: 'For any questions about this policy or your data, contact the developer at limmenghong678@gmail.com.' },
];
</script>

<template>
    <Head title="Privacy Policy" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                <Link href="/" class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <AppLogoIcon class="h-5 w-5 fill-current text-white" />
                    </div>
                    <span class="text-lg font-bold">Scormetry</span>
                </Link>
                <Link href="/terms" class="text-sm text-muted-foreground hover:text-foreground">Terms</Link>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-12">
            <h1 class="text-2xl font-bold tracking-tight">Privacy Policy</h1>
            <p class="mt-2 text-sm text-muted-foreground">How Scormetry 2.0 collects, uses, and protects your information.</p>

            <div class="mt-8 space-y-6">
                <section v-for="s in sections" :key="s.h">
                    <h2 class="text-sm font-semibold text-foreground">{{ s.h }}</h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ s.p }}</p>
                </section>
            </div>

            <p class="mt-10 border-t border-border pt-6 text-xs text-muted-foreground">
                Last updated: {{ new Date().getFullYear() }} · Scormetry · Paragon International University.
            </p>
        </main>
    </div>
</template>
