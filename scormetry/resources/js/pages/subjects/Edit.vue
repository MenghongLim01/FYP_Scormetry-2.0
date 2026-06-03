<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { BookOpen, ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as subjectShow, update as subjectUpdate } from '@/actions/App/Http/Controllers/SubjectController';

const props = defineProps<{
    subject: {
        id: number;
        title: string;
        description: string | null;
        passing_score: number;
        require_approval: boolean;
    };
}>();

const form = useForm({
    title: props.subject.title,
    description: props.subject.description ?? '',
    passing_score: props.subject.passing_score,
    require_approval: props.subject.require_approval,
});

function submit() {
    form.patch(subjectUpdate.url(props.subject.id));
}
</script>

<template>
    <Head :title="`Edit ${subject.title}`" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="subjectShow.url(subject.id)">
                    <ArrowLeft class="h-4 w-4" />
                    Back
                </Link>
            </Button>
        </div>

        <Card class="mx-auto w-full max-w-xl">
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        <BookOpen class="h-4 w-4" />
                    </div>
                    <div>
                        <CardTitle>Edit Subject</CardTitle>
                        <CardDescription>Update details for {{ subject.title }}</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="flex flex-col gap-5">
                    <div class="flex flex-col gap-1.5">
                        <Label for="title">Subject Title <span class="text-destructive">*</span></Label>
                        <Input id="title" v-model="form.title" type="text" required />
                        <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="description">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex min-h-[80px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px]"
                        />
                        <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <Label for="passing_score">Passing Score (%) <span class="text-destructive">*</span></Label>
                        <Input id="passing_score" v-model.number="form.passing_score" type="number" min="0" max="100" required />
                        <p v-if="form.errors.passing_score" class="text-xs text-destructive">{{ form.errors.passing_score }}</p>
                    </div>

                    <div class="rounded-lg border bg-muted/30 px-4 py-3">
                        <div class="flex items-start gap-3">
                            <Checkbox id="require_approval" v-model="form.require_approval" class="mt-0.5" />
                            <div class="flex-1">
                                <Label for="require_approval" class="font-medium">Require approval before joining</Label>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    When enabled, users who join via code will be placed in a pending state until you approve them.
                                </p>
                            </div>
                        </div>
                        <p v-if="form.errors.require_approval" class="mt-2 text-xs text-destructive">{{ form.errors.require_approval }}</p>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <Button variant="outline" as-child>
                            <Link :href="subjectShow.url(subject.id)">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
