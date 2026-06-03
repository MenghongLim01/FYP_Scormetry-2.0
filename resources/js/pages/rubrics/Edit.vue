<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck, Plus, Trash2, AlertTriangle, Scale } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';
import { show as rubricShow, update as rubricUpdate } from '@/actions/App/Http/Controllers/RubricController';

const props = defineProps<{
    rubric: {
        id: number;
        status: string;
        structure_json: Array<{ criteria: string; max_score: number; weight: number }> | null;
        subject: {
            id: number;
            title: string;
        };
    };
    scoringStarted: boolean;
}>();

const form = useForm({
    structure_json: props.rubric.structure_json ?? [
        { criteria: '', max_score: 4, weight: 0 },
    ],
    correction_reason: '',
    confirm_scoring_started_change: false,
});

const totalWeight = computed(() =>
    Math.round(form.structure_json.reduce((sum, item) => sum + (Number(item.weight) || 0), 0) * 100) / 100
)

const remainingWeight = computed(() => Math.max(0, Math.round((100 - totalWeight.value) * 100) / 100))

function maxWeightFor(index: number): number {
    const othersTotal = form.structure_json.reduce((sum, item, i) =>
        i === index ? sum : sum + (Number(item.weight) || 0), 0)
    return Math.max(0, Math.round((100 - othersTotal) * 100) / 100)
};

function addCriteria() {
    form.structure_json.push({ criteria: '', max_score: 4, weight: 0 });
}

function removeCriteria(index: number) {
    form.structure_json.splice(index, 1);
}

function normalizeWeights() {
    const total = form.structure_json.reduce((sum, item) => sum + (Number(item.weight) || 0), 0);
    if (total === 0 || form.structure_json.length === 0) return;

    let remaining = 100;
    form.structure_json.forEach((item, i) => {
        if (i === form.structure_json.length - 1) {
            item.weight = remaining;
        } else {
            item.weight = Math.round(((Number(item.weight) || 0) / total) * 100);
            remaining -= item.weight;
        }
    });
}

function submit() {
    form.patch(rubricUpdate.url(props.rubric.id));
}
</script>

<template>
    <Head title="Edit Rubric" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="rubricShow.url(rubric.id)">
                    <ArrowLeft class="h-4 w-4" />
                    Back to Rubric
                </Link>
            </Button>
        </div>

        <Card class="mx-auto w-full max-w-3xl">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                            <ClipboardCheck class="h-4 w-4" />
                        </div>
                        <div>
                            <CardTitle>{{ rubric.status === 'locked' ? 'Correct Locked Rubric' : 'Edit Rubric Structure' }}</CardTitle>
                            <CardDescription>{{ rubric.subject.title }}</CardDescription>
                        </div>
                    </div>
                    <span
                        :class="[
                            'text-sm font-semibold',
                            totalWeight === 100 ? 'text-green-600' : 'text-amber-600'
                        ]"
                    >
                        Total: {{ totalWeight }}%
                    </span>
                </div>
                <div v-if="totalWeight !== 100" class="mt-2 flex items-center justify-between rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="h-3.5 w-3.5 shrink-0" />
                        <span>
                            Weights must sum to exactly 100%.
                            <span v-if="totalWeight < 100" class="font-semibold">{{ remainingWeight }}% remaining.</span>
                            <span v-else class="font-semibold text-red-600 dark:text-red-400">{{ (totalWeight - 100).toFixed(2) }}% over limit!</span>
                        </span>
                    </div>
                    <Button
                        v-if="totalWeight > 0"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-6 gap-1 border-amber-300 text-xs text-amber-700 hover:bg-amber-100 dark:border-amber-800 dark:text-amber-300 dark:hover:bg-amber-900"
                        @click="normalizeWeights"
                    >
                        <Scale class="h-3 w-3" />
                        Auto-normalize
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="flex flex-col gap-6">
                    <div
                        v-if="rubric.status === 'locked'"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        <div class="flex items-start gap-2">
                            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                            <div>
                                <p class="font-semibold">Special correction mode</p>
                                <p class="mt-1 text-xs">This rubric is already locked. Any correction is saved to the audit history with your name, date, and optional reason.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Criteria rows -->
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border bg-primary text-left text-xs font-semibold uppercase tracking-wider text-primary-foreground">
                                    <th class="px-4 py-2.5">Criteria Name</th>
                                    <th class="px-4 py-2.5 w-32 text-center">Max Score</th>
                                    <th class="px-4 py-2.5 w-32 text-center">Weight (%)</th>
                                    <th class="px-4 py-2.5 w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(item, index) in form.structure_json" :key="index">
                                    <td class="px-4 py-2">
                                        <Input
                                            v-model="item.criteria"
                                            type="text"
                                            placeholder="e.g., Research Depth"
                                            class="h-8"
                                            required
                                        />
                                    </td>
                                    <td class="px-4 py-2">
                                        <Input
                                            v-model.number="item.max_score"
                                            type="number"
                                            min="1"
                                            class="h-8 text-center"
                                            required
                                        />
                                    </td>
                                    <td class="px-4 py-2">
                                        <Input
                                            v-model.number="item.weight"
                                            type="number"
                                            min="0"
                                            :max="maxWeightFor(index)"
                                            step="0.01"
                                            class="h-8 text-center"
                                            :class="{ 'border-red-400 focus-visible:ring-red-400': item.weight > maxWeightFor(index) }"
                                            required
                                        />
                                    </td>
                                    <td class="px-4 py-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 w-8 p-0 text-muted-foreground hover:text-destructive"
                                            @click="removeCriteria(index)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="form.errors.structure_json" class="text-xs text-destructive">{{ form.errors.structure_json }}</p>

                    <Button type="button" variant="outline" class="gap-2" @click="addCriteria">
                        <Plus class="h-4 w-4" />
                        Add Criteria Row
                    </Button>

                    <div v-if="rubric.status === 'locked'" class="flex flex-col gap-3 rounded-lg border bg-muted/20 p-4">
                        <div class="flex flex-col gap-1.5">
                            <Label for="correction_reason">Correction Reason <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <Input id="correction_reason" v-model="form.correction_reason" type="text" placeholder="e.g., Fixed extracted weight for Methodology" />
                            <p v-if="form.errors.correction_reason" class="text-xs text-destructive">{{ form.errors.correction_reason }}</p>
                        </div>
                        <label v-if="scoringStarted" class="flex items-start gap-2 text-sm">
                            <input v-model="form.confirm_scoring_started_change" type="checkbox" class="mt-1 rounded border-border" />
                            <span>Scoring already started. I confirm this correction should apply to the locked rubric.</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 border-t pt-4">
                        <Button variant="outline" as-child>
                            <Link :href="rubricShow.url(rubric.id)">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing || totalWeight !== 100 || (rubric.status === 'locked' && scoringStarted && !form.confirm_scoring_started_change)">
                            {{ form.processing ? 'Saving...' : rubric.status === 'locked' ? 'Save Correction' : 'Save Rubric Structure' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
