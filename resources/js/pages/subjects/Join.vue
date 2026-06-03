<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as subjectsIndex, join as subjectJoin } from '@/actions/App/Http/Controllers/SubjectController';

const form = useForm({ join_code: '' });

function submit() {
    form.post(subjectJoin.url());
}
</script>

<template>
    <Head title="Join Subject" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="subjectsIndex.url()">
                    <ArrowLeft class="h-4 w-4" />
                    Subjects
                </Link>
            </Button>
        </div>

        <Card class="mx-auto w-full max-w-md">
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        <KeyRound class="h-4 w-4" />
                    </div>
                    <div>
                        <CardTitle>Join a Subject</CardTitle>
                        <CardDescription>Enter the classroom code shared by your teacher.</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <Label for="join_code">Classroom Code</Label>
                        <Input
                            id="join_code"
                            v-model="form.join_code"
                            type="text"
                            placeholder="e.g. ABC123"
                            maxlength="8"
                            class="uppercase tracking-widest text-center text-lg font-mono"
                            required
                        />
                        <p v-if="form.errors.join_code" class="text-xs text-destructive">{{ form.errors.join_code }}</p>
                    </div>

                    <Button type="submit" :disabled="form.processing" class="w-full gap-2">
                        <KeyRound class="h-4 w-4" />
                        {{ form.processing ? 'Joining...' : 'Join Subject' }}
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
