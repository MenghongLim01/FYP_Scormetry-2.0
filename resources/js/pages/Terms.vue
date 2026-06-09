<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

// Public, no-login terms of service (used for Google OAuth verification).
const sections = [
    { h: '1. Acceptance of Terms', p: 'By accessing or using Scormetry 2.0, you agree to these Terms & Conditions and to use the platform only for legitimate academic evaluation activities authorized by your institution.' },
    { h: '2. Accounts & Roles', p: 'You are responsible for the activity under your account and for keeping your credentials secure. Access to subjects, teams, documents, and scores is granted by role (instructor, judge/reviewer, student) and by assignment. You may not attempt to access records outside your authorized role.' },
    { h: '3. Acceptable Use', p: 'You agree not to upload unlawful, infringing, or malicious content; not to disrupt the service; and not to misuse documents or evaluation data. Student documents and review records are confidential and may only be used for their intended evaluation purpose.' },
    { h: '4. Academic Integrity', p: 'Rubrics, scores, and feedback recorded in Scormetry form part of an official evaluation. Locked rubrics and submitted reviews are protected; any unlock or override is logged. Tampering with evaluation records is prohibited.' },
    { h: '5. Intellectual Property', p: 'Students retain ownership of their submitted work. The institution retains the right to store and process evaluation records for academic administration. The Scormetry platform and its design remain the property of their respective owners.' },
    { h: '6. Availability', p: 'The platform is provided “as is.” While we aim for reliable service, we do not guarantee uninterrupted availability and are not liable for losses arising from downtime, data entry errors, or misuse.' },
    { h: '7. Changes', p: 'These terms may be updated. Continued use of the platform after changes constitutes acceptance of the revised terms.' },
];
</script>

<template>
    <Head title="Terms of Service" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="border-b border-border">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
                <Link href="/" class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <AppLogoIcon class="h-5 w-5 fill-current text-white" />
                    </div>
                    <span class="text-lg font-bold">Scormetry</span>
                </Link>
                <Link href="/privacy" class="text-sm text-muted-foreground hover:text-foreground">Privacy</Link>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-12">
            <h1 class="text-2xl font-bold tracking-tight">Terms of Service</h1>
            <p class="mt-2 text-sm text-muted-foreground">The terms governing use of Scormetry 2.0.</p>

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
