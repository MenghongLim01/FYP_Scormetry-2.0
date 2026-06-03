<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { FileText, ArrowLeft, Upload, Users, CheckCircle, AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { computed, ref } from 'vue';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { store as paperStore } from '@/actions/App/Http/Controllers/PaperController';

type DefenseAttemptOption = {
    id: number;
    label: string;
    attempt_type: string;
    results_released_at: string | null;
    paper_upload_deadline_at: string | null;
    period: { id: number; name: string; type: string; sequence: number };
    papers: Array<{ id: number; file_path: string }>;
};

const props = defineProps<{
    subject: {
        id: number;
        title: string;
    };
    team: {
        id: number;
        name: string;
        members: Array<{ id: number; name: string }>;
        defense_attempts: DefenseAttemptOption[];
    } | null;
    selectedAttemptId: number | null;
}>();

const isDragging = ref(false);
const availableAttempts = computed(() =>
    [...(props.team?.defense_attempts ?? [])].sort((a, b) =>
        (a.period.sequence - b.period.sequence) || (a.id - b.id)),
);
const defaultAttemptId = computed(() =>
    availableAttempts.value.find((attempt) => attempt.id === props.selectedAttemptId)?.id
    ?? availableAttempts.value.find((attempt) => !attempt.results_released_at)?.id
    ?? availableAttempts.value[0]?.id
    ?? null,
);

const form = useForm({
    subject_id: props.subject.id,
    defense_attempt_id: defaultAttemptId.value,
    file: null as File | null,
});

function submit() {
    form.post(paperStore.url());
}

function handleDrop(e: DragEvent) {
    isDragging.value = false;
    const file = e.dataTransfer?.files[0];
    if (file && file.type === 'application/pdf') {
        form.file = file;
    }
}

function formatFileSize(bytes: number): string {
    return (bytes / 1024 / 1024).toFixed(2) + ' MB';
}

</script>

<template>
    <Head title="Submit Paper" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="subjectShow.url(subject.id)">
                    <ArrowLeft class="h-4 w-4" />
                    Back to {{ subject.title }}
                </Link>
            </Button>
        </div>

        <Card class="mx-auto w-full max-w-xl">
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                        <FileText class="h-4 w-4" />
                    </div>
                    <div>
                        <CardTitle>Submit Paper</CardTitle>
                        <CardDescription>Submit your paper for {{ subject.title }}</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="flex flex-col gap-5" enctype="multipart/form-data">
                    <div class="flex flex-col gap-2">
                        <Label>Team</Label>
                        <div v-if="team" class="rounded-lg border bg-muted/30 px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold">{{ team.name }}</p>
                                    <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Users class="h-3 w-3" />
                                        {{ team.members.map((m) => m.name).join(', ') }}
                                    </p>
                                </div>
                                <span class="rounded-md bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-950/40 dark:text-green-300">
                                    Assigned
                                </span>
                            </div>
                        </div>
                        <div v-else class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            <div class="flex items-start gap-2">
                                <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                                <div>
                                    <p class="font-medium">You are not assigned to a team yet.</p>
                                    <p class="mt-0.5 text-xs text-amber-700/90 dark:text-amber-200/90">
                                        Ask your teacher to add you to a team before submitting a paper.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="availableAttempts.length > 0" class="flex flex-col gap-2">
                        <Label for="defense_attempt_id">Evaluation Round <span class="text-destructive">*</span></Label>
                        <select
                            id="defense_attempt_id"
                            v-model.number="form.defense_attempt_id"
                            class="h-10 rounded-md border bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            required
                        >
                            <option v-for="attempt in availableAttempts" :key="attempt.id" :value="attempt.id">
                                {{ attempt.period.name }} — {{ attempt.label }}{{ attempt.papers.length ? ' (replace current PDF)' : '' }}
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Choose Midterm, Final, or Re-defense so the document is saved under the correct round.
                        </p>
                        <p v-if="form.errors.defense_attempt_id" class="text-xs text-destructive">{{ form.errors.defense_attempt_id }}</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="file">Paper PDF <span class="text-destructive">*</span></Label>
                        <label
                            for="file"
                            :class="[
                                'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-8 text-center transition-all',
                                isDragging ? 'border-primary bg-primary/5 scale-[1.01]' :
                                form.file ? 'border-green-500 bg-green-50 dark:bg-green-950/30' :
                                'border-border hover:border-primary hover:bg-accent'
                            ]"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop"
                        >
                            <CheckCircle v-if="form.file" class="h-8 w-8 text-green-600" />
                            <Upload v-else class="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p class="text-sm font-medium">
                                    {{ form.file ? form.file.name : isDragging ? 'Drop your PDF here' : 'Click to upload or drag & drop' }}
                                </p>
                                <p v-if="form.file" class="text-xs text-green-600">{{ formatFileSize(form.file.size) }}</p>
                                <p v-else class="text-xs text-muted-foreground">PDF only, max 20 MB</p>
                            </div>
                            <input
                                id="file"
                                type="file"
                                accept=".pdf"
                                class="sr-only"
                                @change="form.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                        </label>
                        <p v-if="form.errors.file" class="text-xs text-destructive">{{ form.errors.file }}</p>
                    </div>

                    <div v-if="form.progress" class="h-2 overflow-hidden rounded-full bg-muted">
                        <div class="h-full bg-primary transition-all" :style="{ width: `${form.progress.percentage}%` }" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <Button variant="outline" as-child>
                            <Link :href="subjectShow.url(subject.id)">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing || !form.file || !team" class="gap-2">
                            <Upload class="h-4 w-4" />
                            {{ form.processing ? 'Submitting...' : 'Submit Paper' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
