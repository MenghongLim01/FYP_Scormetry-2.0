<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ShieldCheck } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { computed } from 'vue';
import { index as subjectsIndex, joinAsReviewer as joinAsReviewerAction } from '@/actions/App/Http/Controllers/SubjectController';

const form = useForm({
    reviewer_code: '',
    committee_role: '',
    role_label: '',
});

const needsCustomLabel = computed(() => form.committee_role === 'custom');

function submit() {
    form.post(joinAsReviewerAction.url());
}
</script>

<template>
    <Head title="Join as Reviewer" />

    <div class="flex min-h-[calc(100vh-5rem)] flex-col gap-6 bg-slate-50/70 p-6 dark:bg-slate-950">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="subjectsIndex.url()">
                    <ArrowLeft class="h-4 w-4" />
                    Subjects
                </Link>
            </Button>
        </div>

        <Card class="mx-auto w-full max-w-xl overflow-hidden border-[#212e70]/10 shadow-lg shadow-[#212e70]/5">
            <CardHeader class="border-b bg-white px-7 py-6 dark:bg-background">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#212e70] text-white shadow-sm">
                        <ShieldCheck class="h-5 w-5" />
                    </div>
                    <div>
                        <CardTitle class="text-2xl">Join as Reviewer</CardTitle>
                        <CardDescription class="mt-1 text-base">
                            Enter the reviewer code shared by the subject owner and select your committee role.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="bg-white px-7 py-6 dark:bg-background">
                <form @submit.prevent="submit" class="flex flex-col gap-5">
                    <div class="flex flex-col gap-1.5">
                        <Label for="committee_role" class="text-sm font-semibold text-foreground">Committee Role <span class="text-destructive">*</span></Label>
                        <Select v-model="form.committee_role" required>
                            <SelectTrigger id="committee_role" class="h-12 w-full rounded-xl border-[#212e70]/15 bg-background px-4 text-base shadow-sm">
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl">
                                <SelectItem value="advisor">Advisor</SelectItem>
                                <SelectItem value="custom">Custom role</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">The FYP Instructor is the subject owner and is added automatically.</p>
                        <p v-if="form.errors.committee_role" class="text-xs text-destructive">{{ form.errors.committee_role }}</p>
                    </div>

                    <div v-if="needsCustomLabel" class="flex flex-col gap-1.5">
                        <Label for="role_label" class="text-sm font-semibold text-foreground">Role Label <span class="text-destructive">*</span></Label>
                        <Input
                            id="role_label"
                            v-model="form.role_label"
                            type="text"
                            placeholder="e.g. External Examiner"
                            maxlength="100"
                            class="h-12 rounded-xl border-[#212e70]/15 px-4 text-base shadow-sm"
                            required
                        />
                        <p v-if="form.errors.role_label" class="text-xs text-destructive">{{ form.errors.role_label }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="reviewer_code" class="text-sm font-semibold text-foreground">Reviewer Join Code <span class="text-destructive">*</span></Label>
                        <Input
                            id="reviewer_code"
                            v-model="form.reviewer_code"
                            type="text"
                            placeholder="e.g. XYZ789"
                            maxlength="8"
                            class="h-12 rounded-xl border-[#212e70]/15 text-center font-mono text-lg uppercase tracking-[0.28em] shadow-sm"
                            required
                        />
                        <p v-if="form.errors.reviewer_code" class="text-xs text-destructive">{{ form.errors.reviewer_code }}</p>
                    </div>

                    <Button type="submit" :disabled="form.processing || !form.committee_role || (needsCustomLabel && !form.role_label)" class="h-12 w-full gap-2 rounded-xl bg-[#212e70] text-base hover:bg-[#18235f]">
                        <ShieldCheck class="h-4 w-4" />
                        {{ form.processing ? 'Joining...' : 'Join as Reviewer' }}
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
