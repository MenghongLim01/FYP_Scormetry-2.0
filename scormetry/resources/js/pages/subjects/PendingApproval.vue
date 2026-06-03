<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { index as subjectsIndex } from '@/actions/App/Http/Controllers/SubjectController';

defineProps<{
    subject: { id: number; title: string };
}>();
</script>

<template>
    <Head title="Waiting for Approval" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" as-child class="gap-1">
                <Link :href="subjectsIndex.url()">
                    <ArrowLeft class="h-4 w-4" />
                    Subjects
                </Link>
            </Button>
        </div>

        <Card class="mx-auto w-full max-w-lg">
            <CardHeader>
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                        <Clock class="h-4 w-4" />
                    </div>
                    <div>
                        <CardTitle>Waiting for teacher approval</CardTitle>
                        <CardDescription>
                            Your request to join <span class="font-medium">{{ subject.title }}</span> is pending.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="text-sm text-muted-foreground">
                You will get access once the room owner approves your request.
            </CardContent>
        </Card>
    </div>
</template>

