<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ClipboardCheck, ArrowLeft, Upload, FileUp, AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { computed, ref } from 'vue';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { store as rubricStore } from '@/actions/App/Http/Controllers/RubricController';

const props = defineProps<{
    subject: {
        id: number;
        title: string;
    };
    defensePeriods: Array<{
        id: number;
        name: string;
        type: string;
        rubric: { id: number; status: string } | null;
    }>;
}>();

const query = typeof window === 'undefined'
    ? new URLSearchParams()
    : new URLSearchParams(window.location.search);
const confirmedReplacementPeriodId = Number(query.get('defense_period_id'));
const hasConfirmedLockedReplacement = query.get('replace_locked') === '1'
    && props.defensePeriods.some((period) => period.id === confirmedReplacementPeriodId && period.rubric?.status === 'locked');
const initialPeriodId = hasConfirmedLockedReplacement
    ? confirmedReplacementPeriodId
    : (props.defensePeriods[0]?.id ?? null);

const form = useForm({
    defense_period_id: initialPeriodId as number | null,
    use_custom_period: 0,
    custom_period_name: '',
    replace_locked: 0,
    pdf: null as File | null,
});

const periodChoice = ref(initialPeriodId ? String(initialPeriodId) : 'custom');
const isCustomPeriod = computed(() => periodChoice.value === 'custom');
const selectedPeriod = computed(() => props.defensePeriods.find((period) => String(period.id) === periodChoice.value) ?? null);
const selectedPeriodIsLocked = computed(() => selectedPeriod.value?.rubric?.status === 'locked');
const canReplaceSelectedLockedRubric = computed(() =>
    hasConfirmedLockedReplacement
    && selectedPeriod.value?.id === confirmedReplacementPeriodId
    && selectedPeriod.value?.rubric?.status === 'locked',
);
const isDragging = ref(false);

function handleDrop(event: DragEvent) {
    isDragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file && file.type === 'application/pdf') {
        form.pdf = file;
    }
}

function submit() {
    form.use_custom_period = isCustomPeriod.value ? 1 : 0;
    form.defense_period_id = isCustomPeriod.value ? null : Number(periodChoice.value);
    form.replace_locked = !isCustomPeriod.value && canReplaceSelectedLockedRubric.value ? 1 : 0;
    form.post(rubricStore.url(props.subject.id));
}
</script>

<template>
    <Head title="Upload Rubric" />

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
                        <ClipboardCheck class="h-4 w-4" />
                    </div>
                    <div>
                        <CardTitle>Upload Rubric</CardTitle>
                        <CardDescription>Upload a PDF rubric for {{ subject.title }}</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="flex flex-col gap-5" enctype="multipart/form-data">
                    <div class="flex flex-col gap-2">
                        <Label for="defense_period_id">Defense Period <span class="text-destructive">*</span></Label>
                        <select
                            id="defense_period_id"
                            v-model="periodChoice"
                            :disabled="canReplaceSelectedLockedRubric"
                            class="h-10 rounded-md border bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            required
                        >
                            <option v-for="period in defensePeriods" :key="period.id" :value="String(period.id)">
                                {{ period.name }}{{ period.rubric ? period.rubric.status === 'locked' ? ' (locked)' : ' (replace existing rubric)' : '' }}
                            </option>
                            <option v-if="!canReplaceSelectedLockedRubric" value="custom">Custom defense period</option>
                        </select>
                        <div v-if="canReplaceSelectedLockedRubric" class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
                            <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            You confirmed replacement for this locked period. Uploading will replace the current PDF and extracted criteria, then reopen verification.
                        </div>
                        <div v-else-if="selectedPeriodIsLocked" class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
                            <AlertTriangle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            This period is locked. Open the current rubric and confirm Upload New PDF before replacing it.
                        </div>
                        <p class="text-xs text-muted-foreground">Midterm, Final, and custom defense periods can use different official rubrics.</p>
                        <p v-if="form.errors.defense_period_id" class="text-xs text-destructive">{{ form.errors.defense_period_id }}</p>
                        <p v-if="form.errors.replace_locked" class="text-xs text-destructive">{{ form.errors.replace_locked }}</p>
                    </div>

                    <div v-if="isCustomPeriod" class="flex flex-col gap-2 rounded-lg border bg-muted/30 p-3">
                        <Label for="custom_period_name">Custom Period Name <span class="text-destructive">*</span></Label>
                        <input
                            id="custom_period_name"
                            v-model="form.custom_period_name"
                            type="text"
                            maxlength="100"
                            placeholder="e.g. Proposal Defense, Demo Day, Re-defense"
                            class="h-10 rounded-md border bg-background px-3 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            required
                        />
                        <p class="text-xs text-muted-foreground">This creates a new period and adds it to the Evaluation Rounds table for every team.</p>
                        <p v-if="form.errors.custom_period_name" class="text-xs text-destructive">{{ form.errors.custom_period_name }}</p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="pdf">Rubric PDF <span class="text-destructive">*</span></Label>
                        <label
                            for="pdf"
                            class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors"
                            :class="isDragging
                                ? 'border-primary bg-primary/5'
                                : form.pdf
                                    ? 'border-green-400 bg-green-50 dark:border-green-600 dark:bg-green-950'
                                    : 'border-border hover:border-primary hover:bg-accent'"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop"
                        >
                            <div v-if="form.pdf" class="flex flex-col items-center gap-2">
                                <FileUp class="h-8 w-8 text-green-600" />
                                <p class="text-sm font-medium text-green-700 dark:text-green-300">{{ form.pdf.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ (form.pdf.size / 1024 / 1024).toFixed(2) }} MB</p>
                            </div>
                            <div v-else class="flex flex-col items-center gap-2">
                                <Upload class="h-8 w-8 text-muted-foreground" />
                                <p class="text-sm font-medium">Click to upload or drag & drop</p>
                                <p class="text-xs text-muted-foreground">PDF only, max 10 MB</p>
                            </div>
                            <input
                                id="pdf"
                                type="file"
                                accept=".pdf"
                                class="sr-only"
                                @change="form.pdf = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                        </label>
                        <p v-if="form.errors.pdf" class="text-xs text-destructive">{{ form.errors.pdf }}</p>
                    </div>

                    <div class="rounded-lg bg-blue-50 px-4 py-3 text-xs text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        <strong>What happens next?</strong> After uploading, the rubric structure will be extracted automatically. You can then review and edit the criteria before locking it for reviews.
                    </div>

                    <!-- Upload progress -->
                    <div v-if="form.progress" class="flex flex-col gap-1.5">
                        <div class="flex items-center justify-between text-xs text-muted-foreground">
                            <span>Uploading...</span>
                            <span>{{ form.progress.percentage }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-primary transition-all duration-300" :style="{ width: `${form.progress.percentage}%` }" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <Button variant="outline" as-child>
                            <Link :href="subjectShow.url(subject.id)">Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.pdf || (isCustomPeriod && !form.custom_period_name.trim()) || (selectedPeriodIsLocked && !canReplaceSelectedLockedRubric)"
                            class="gap-2"
                        >
                            <Upload class="h-4 w-4" />
                            {{ form.processing ? 'Uploading...' : canReplaceSelectedLockedRubric ? 'Upload New Rubric' : 'Upload Rubric' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
