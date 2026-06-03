<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Ban, ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { index as subjectsIndex } from '@/actions/App/Http/Controllers/SubjectController';

defineProps<{
    subject: { id: number; title: string };
}>();
</script>

<template>
    <Head title="Access Blocked" />

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
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                        <Ban class="h-4 w-4" />
                    </div>
                    <div>
                        <CardTitle>You have been removed from this classroom</CardTitle>
                        <CardDescription>
                            You cannot rejoin <span class="font-medium">{{ subject.title }}</span> using the classroom code.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="text-sm text-muted-foreground">
                If you believe this is a mistake, please contact the room owner.
            </CardContent>
        </Card>
    </div>
</template>

