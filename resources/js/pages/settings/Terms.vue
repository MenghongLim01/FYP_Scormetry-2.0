<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Terms & Conditions', href: '/settings/terms' }] },
});

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
    <Head title="Terms & Conditions" />
    <h1 class="sr-only">Terms and Conditions</h1>

    <div class="space-y-8">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <FileText class="h-6 w-6" />
            </div>
            <div>
                <h2 class="text-xl font-semibold text-foreground">Terms &amp; Conditions</h2>
                <p class="mt-1 text-sm text-muted-foreground">Please read these terms carefully before using Scormetry 2.0.</p>
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
