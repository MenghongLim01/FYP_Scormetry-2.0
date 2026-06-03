<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck, Pencil, Lock, CheckCircle, RefreshCw, Eye, AlertTriangle, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { useAuth } from '@/composables/useAuth';
import { show as subjectShow } from '@/actions/App/Http/Controllers/SubjectController';
import { create as rubricCreate, edit as rubricEdit, servePdf as rubricPdf } from '@/actions/App/Http/Controllers/RubricController';

const props = defineProps<{
    rubric: {
        id: number;
        pdf_path: string;
        status: string;
        structure_json: Array<{ criteria: string; max_score: number; weight: number }> | null;
        subject: {
            id: number;
            title: string;
            teacher: { id: number; name: string };
        };
        defense_period: { id: number; name: string } | null;
    };
}>();

const { isTeacherOrAdmin } = useAuth();

const showApproveDialog = ref(false);
const showRemoveDialog = ref(false);
const showReplaceDialog = ref(false);

function approveRubric() {
    router.post(`/rubrics/${props.rubric.id}/approve`, {}, {
        onSuccess: () => { showApproveDialog.value = false; },
    });
}

function removeRubric() {
    router.delete(`/rubrics/${props.rubric.id}`, {
        onSuccess: () => { showRemoveDialog.value = false; },
    });
}

function replaceRubricUploadUrl(): string {
    const query: Record<string, number> = { replace_locked: 1 };

    if (props.rubric.defense_period) {
        query.defense_period_id = props.rubric.defense_period.id;
    }

    return rubricCreate.url(props.rubric.subject.id, { query });
}

const statusConfig: Record<string, { color: string; label: string; description: string }> = {
    uploaded: {
        color: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950 dark:text-blue-300',
        label: 'Uploaded',
        description: 'PDF uploaded. AI extraction is processing the rubric structure.',
    },
    pending_verification: {
        color: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950 dark:text-amber-300',
        label: 'Pending Verification',
        description: 'AI has extracted criteria. Review the structure and approve to lock it for reviews.',
    },
    locked: {
        color: 'bg-green-50 text-green-700 border-green-200 dark:bg-green-950 dark:text-green-300',
        label: 'Locked',
        description: 'Rubric is locked and ready for reviews. Structure cannot be changed.',
    },
};

const currentStatus = computed(() => statusConfig[props.rubric.status] ?? statusConfig.uploaded);

const totalWeight = computed(() =>
    (props.rubric.structure_json ?? []).reduce((sum, item) => sum + item.weight, 0),
);

const totalMaxScore = computed(() =>
    (props.rubric.structure_json ?? []).reduce((sum, item) => sum + item.max_score, 0),
);

const statusSteps = ['uploaded', 'pending_verification', 'locked'] as const;
const currentStepIndex = computed(() => statusSteps.indexOf(props.rubric.status as typeof statusSteps[number]));
</script>

<template>
    <Head title="Rubric Details" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="subjectShow.url(rubric.subject.id)">
                    <ArrowLeft class="h-4 w-4" />
                    Back to {{ rubric.subject.title }}
                </Link>
            </Button>
        </div>

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                    <ClipboardCheck class="h-6 w-6" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-bold tracking-tight">Rubric — {{ rubric.subject.title }}</h1>
                        <span :class="['rounded-full border px-2.5 py-0.5 text-xs font-medium', currentStatus.color]">
                            {{ currentStatus.label }}
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">Managed by {{ rubric.subject.teacher.name }}</p>
                </div>
            </div>

            <div v-if="isTeacherOrAdmin" class="flex flex-wrap gap-2">
                <Button v-if="rubric.status !== 'locked'" variant="outline" size="sm" as-child>
                    <Link :href="rubricEdit.url(rubric.id)">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" />
                        Edit Structure
                    </Link>
                </Button>

                <!-- Replace locked rubric by uploading a new PDF -->
                <Dialog v-if="rubric.status === 'locked'" v-model:open="showReplaceDialog">
                    <DialogTrigger as-child>
                        <Button variant="outline" size="sm" class="gap-2">
                            <Upload class="h-3.5 w-3.5" />
                            Upload New Defense Rubric
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Upload New Defense Rubric?</DialogTitle>
                            <DialogDescription>
                                This keeps the current rubric record but replaces the locked Defense Rubric file and extracted criteria with a new upload. You must verify and lock the new rubric again before reviewers use it.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                            Use this only when you want a fresh Defense Rubric file instead of correcting the locked criteria manually.
                        </div>
                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Button as-child>
                                <Link :href="replaceRubricUploadUrl()">
                                    Confirm & Upload
                                </Link>
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <!-- Remove dialog -->
                <Dialog v-if="rubric.status !== 'locked'" v-model:open="showRemoveDialog">
                    <DialogTrigger as-child>
                        <Button variant="outline" size="sm" class="gap-2">
                            <RefreshCw class="h-3.5 w-3.5" />
                            Remove & Re-upload
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Remove Rubric</DialogTitle>
                            <DialogDescription>
                                This will delete the current Defense Rubric file and extracted structure. You will need to upload a new rubric file.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Button variant="destructive" @click="removeRubric">Remove Rubric</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <!-- Approve dialog -->
                <Dialog v-if="rubric.status === 'pending_verification'" v-model:open="showApproveDialog">
                    <DialogTrigger as-child>
                        <Button size="sm" class="gap-2">
                            <Lock class="h-3.5 w-3.5" />
                            Approve & Lock
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Approve & Lock Rubric</DialogTitle>
                            <DialogDescription>
                                Once locked, the rubric structure cannot be edited. Reviewers will use these criteria to score assigned team documents. Make sure the criteria and weights are correct.
                            </DialogDescription>
                        </DialogHeader>
                        <div v-if="totalWeight !== 100" class="flex items-center gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                            <AlertTriangle class="h-4 w-4 shrink-0" />
                            Weights total {{ totalWeight }}% — they should sum to 100%.
                        </div>
                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Button @click="approveRubric">Approve & Lock</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <!-- Status progress -->
        <div class="flex items-center gap-2">
            <div v-for="(step, idx) in statusSteps" :key="step" class="flex items-center gap-2">
                <div
                    class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-medium"
                    :class="idx <= currentStepIndex
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground'"
                >
                    <CheckCircle v-if="idx < currentStepIndex" class="h-4 w-4" />
                    <span v-else>{{ idx + 1 }}</span>
                </div>
                <span class="text-xs font-medium" :class="idx <= currentStepIndex ? 'text-foreground' : 'text-muted-foreground'">
                    {{ step === 'uploaded' ? 'Upload' : step === 'pending_verification' ? 'Verify' : 'Locked' }}
                </span>
                <div v-if="idx < statusSteps.length - 1" class="h-px w-8" :class="idx < currentStepIndex ? 'bg-primary' : 'bg-border'" />
            </div>
        </div>

        <p class="text-sm text-muted-foreground">{{ currentStatus.description }}</p>

        <!-- Criteria table -->
        <Card v-if="rubric.structure_json && rubric.structure_json.length > 0">
            <CardHeader class="border-b px-6 py-4">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-sm font-semibold">Rubric Criteria ({{ rubric.structure_json.length }})</CardTitle>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-muted-foreground">Total max: {{ totalMaxScore }}</span>
                        <span
                            :class="['text-xs font-medium', totalWeight === 100 ? 'text-green-600' : 'text-amber-600']"
                        >
                            Weight: {{ totalWeight }}%
                            <CheckCircle v-if="totalWeight === 100" class="ml-1 inline h-3.5 w-3.5" />
                        </span>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-primary text-left text-xs font-semibold uppercase tracking-wider text-primary-foreground">
                            <th class="px-6 py-3 w-12">#</th>
                            <th class="px-6 py-3">Criteria</th>
                            <th class="px-6 py-3 text-center w-28">Max Score</th>
                            <th class="px-6 py-3 text-center w-28">Weight</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(item, index) in rubric.structure_json" :key="index" class="transition-colors hover:bg-muted/70">
                            <td class="px-6 py-3 text-muted-foreground">{{ index + 1 }}</td>
                            <td class="px-6 py-3 font-medium">{{ item.criteria }}</td>
                            <td class="px-6 py-3 text-center">{{ item.max_score }}</td>
                            <td class="px-6 py-3 text-center">
                                <Badge variant="outline" class="text-xs">{{ item.weight }}%</Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-else class="flex flex-col items-center justify-center rounded-xl border border-dashed py-12 text-center">
            <ClipboardCheck class="mb-3 h-9 w-9 text-muted-foreground/40" />
            <p class="text-sm font-medium text-muted-foreground">No criteria extracted yet.</p>
            <p class="mt-1 text-xs text-muted-foreground/70">AI extraction is processing. This may take a moment.</p>
        </div>
    </div>
</template>
